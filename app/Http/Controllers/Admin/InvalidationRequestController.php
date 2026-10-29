<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvalidationRequest;
use App\Models\Replay;
use App\Services\InvalidationCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InvalidationRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', InvalidationRequest::OPEN);

        $requests = InvalidationRequest::with(['user', 'playerName', 'resolver'])
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $rows = Replay::whereIn('replay_id', $requests->pluck('replay_id')->unique())
            ->orderBy('team')
            ->get()
            ->groupBy('replay_id');

        $case = app(InvalidationCase::class);
        $discussions = $requests->pluck('replay_id')->unique()->mapWithKeys(fn($id) => [$id => [
            'stances' => $case->stances($id),
            'comments' => $case->comments($id),
            'players' => $case->playedNames($id),
        ]]);

        return view('admin.requests.index', compact('requests', 'rows', 'status', 'discussions'));
    }

    /**
     * Invalidate the game and close every open request for it.
     */
    public function approve(Request $request, InvalidationRequest $invalidationRequest, InvalidationCase $case): RedirectResponse
    {
        $replayId = $invalidationRequest->replay_id;
        $count = $case->resolve($replayId, InvalidationRequest::APPROVED, $request->user(), $this->note($request));

        Log::info('admin: replay.invalidate', [
            'admin_id' => $request->user()->id,
            'admin' => $request->user()->player_name,
            'replay_id' => $replayId,
            'rows' => $count,
            'request_id' => $invalidationRequest->id,
        ]);

        return back()->with('status', 'Game ' . substr($replayId, 0, 8) . ' invalidated; its ELO and wins/losses were reversed.');
    }

    /**
     * Keep the game and close every open request for it.
     */
    public function reject(Request $request, InvalidationRequest $invalidationRequest, InvalidationCase $case): RedirectResponse
    {
        $note = $this->note($request);
        try {
            $case->resolve($invalidationRequest->replay_id, InvalidationRequest::REJECTED, $request->user(), $note);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Log::info('admin: invalidation-request.reject', [
            'admin_id' => $request->user()->id,
            'admin' => $request->user()->player_name,
            'replay_id' => $invalidationRequest->replay_id,
            'request_id' => $invalidationRequest->id,
        ]);

        return back()->with('status', 'Request for game ' . substr($invalidationRequest->replay_id, 0, 8) . ' rejected; the game stays valid.');
    }

    /**
     * Put a decided request back to open. Reopening an approval brings the
     * game back until it is decided again.
     */
    public function reopen(Request $request, InvalidationRequest $invalidationRequest, InvalidationCase $case): RedirectResponse
    {
        try {
            $case->reopen($invalidationRequest->replay_id, $request->user(), byAdmin: true);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Log::info('admin: invalidation-request.reopen', [
            'admin_id' => $request->user()->id,
            'admin' => $request->user()->player_name,
            'replay_id' => $invalidationRequest->replay_id,
        ]);

        return back()->with('status', 'Request for game ' . substr($invalidationRequest->replay_id, 0, 8) . ' reopened.');
    }

    /**
     * An admin replies in the game's discussion (shown to the players, emailed, and posted on Discord).
     */
    public function reply(Request $request, string $replayId, InvalidationCase $case): RedirectResponse
    {
        abort_unless(InvalidationRequest::where('replay_id', $replayId)->exists(), 404);
        $body = trim($request->validate(['body' => ['required', 'string', 'max:2000']])['body']);

        $case->comment($request->user(), $replayId, null, $body, fromAdmin: true);
        Log::info('admin: invalidation-request.reply', ['admin_id' => $request->user()->id, 'replay_id' => $replayId]);

        return back()->with('status', 'Reply posted.');
    }

    private function note(Request $request): ?string
    {
        return trim((string) ($request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? '')) ?: null;
    }
}
