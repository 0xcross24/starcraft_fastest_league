<?php

namespace App\Http\Controllers;

use App\Models\InvalidationRequest;
use App\Services\DiscordTickets;
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
    public function store(Request $request, string $replayId, DiscordTickets $tickets): RedirectResponse
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
        $ticket->update(['discord_thread_id' => $tickets->open($ticket)]);

        Log::info('invalidation request', ['id' => $ticket->id, 'user_id' => $user->id, 'replay_id' => $replayId, 'discord' => (bool) $ticket->discord_thread_id]);

        return $this->backWith($replayId, 'Request sent to the admins. The decision will show here once it is reviewed.');
    }

    /**
     * Back to the list, with a message x-invalidation-request shows on that game.
     */
    private function backWith(string $replayId, string $message): RedirectResponse
    {
        return back()->with('invalidation_request', ['replay_id' => $replayId, 'message' => $message]);
    }
}
