<?php

namespace App\Http\Controllers;

use App\Models\InvalidationRequest;
use App\Models\InvalidationComment;
use App\Services\InvalidationCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class InvalidationRequestController extends Controller
{
    /**
     * A player in a valid game asks for it to be invalidated: the request is
     * saved, then posted to Discord as a ticket.
     */
    public function store(Request $request, string $replayId, InvalidationCase $case): RedirectResponse
    {
        $user = $request->user();
        $row = InvalidationRequest::participantRow($user, $replayId);
        abort_unless($row, 403, 'Only players in this game can ask for it to be invalidated.');

        $data = $request->validateWithBag('invalidation', [
            'reason' => ['required', Rule::in(array_keys(InvalidationRequest::REASONS))],
            'justification' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        if ($row->is_invalid) {
            return $this->backWith($replayId, 'This game is already invalid.');
        }
        if (InvalidationRequest::where('replay_id', $replayId)->where('user_id', $user->id)->exists()) {
            return $this->backWith($replayId, 'You already asked for this game to be invalidated.');
        }

        $ticket = InvalidationRequest::create([
            'replay_id' => $replayId,
            'user_id' => $user->id,
            'player_name_id' => $row->player_name_id,
            'reason' => $data['reason'],
            'justification' => trim($data['justification']),
        ]);
        $case->opened($ticket);

        Log::info('invalidation request', ['id' => $ticket->id, 'user_id' => $user->id, 'replay_id' => $replayId, 'discord' => (bool) $ticket->fresh()->discord_thread_id]);

        return $this->backWith($replayId, 'Request sent. The other players can agree or dispute it on the game page, and the admins decide.');
    }

    /**
     * A player in the game agrees, disputes or comments on an open request.
     */
    public function comment(Request $request, string $replayId, InvalidationCase $case): RedirectResponse
    {
        $user = $request->user();
        abort_unless(InvalidationRequest::participantRow($user, $replayId), 403, 'Only players in this game can take part.');

        $data = $request->validateWithBag('discussion', [
            'stance' => ['nullable', Rule::in([InvalidationComment::AGREE, InvalidationComment::DISPUTE])],
            // A dispute or a plain comment needs words; agreeing doesn't
            'body' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn() => $request->input('stance') !== InvalidationComment::AGREE)],
        ]);

        if (!$case->acceptsPosts($replayId)) {
            return redirect()->route('games.show', $replayId)->with('error', 'This game was already invalidated.');
        }

        $case->comment($user, $replayId, $data['stance'] ?? null, trim((string) ($data['body'] ?? '')) ?: null);

        return redirect()->route('games.show', $replayId)->with('status', 'Posted.');
    }

    /**
     * Back to the list, with a message x-invalidation-request shows on that game.
     */
    private function backWith(string $replayId, string $message): RedirectResponse
    {
        return back()->with('invalidation_request', ['replay_id' => $replayId, 'message' => $message]);
    }
}
