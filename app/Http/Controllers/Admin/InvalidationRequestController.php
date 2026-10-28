<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvalidationRequest;
use App\Models\Replay;
use App\Services\DiscordTickets;
use App\Services\ReplayInvalidator;
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

        return view('admin.requests.index', compact('requests', 'rows', 'status'));
    }

    /**
     * Invalidate the game and close every open request for it.
     */
    public function approve(Request $request, InvalidationRequest $invalidationRequest, ReplayInvalidator $invalidator, DiscordTickets $tickets): RedirectResponse
    {
        $note = $this->note($request);
        $replayId = $invalidationRequest->replay_id;
        $count = $invalidator->invalidate(Replay::where('replay_id', $replayId)->get());
        $this->resolve($request, $invalidationRequest, InvalidationRequest::APPROVED, $note, $tickets);

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
    public function reject(Request $request, InvalidationRequest $invalidationRequest, DiscordTickets $tickets): RedirectResponse
    {
        $this->resolve($request, $invalidationRequest, InvalidationRequest::REJECTED, $this->note($request), $tickets);

        Log::info('admin: invalidation-request.reject', [
            'admin_id' => $request->user()->id,
            'admin' => $request->user()->player_name,
            'replay_id' => $invalidationRequest->replay_id,
            'request_id' => $invalidationRequest->id,
        ]);

        return back()->with('status', 'Request for game ' . substr($invalidationRequest->replay_id, 0, 8) . ' rejected; the game stays valid.');
    }

    private function note(Request $request): ?string
    {
        return trim((string) ($request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? '')) ?: null;
    }

    private function resolve(Request $request, InvalidationRequest $invalidationRequest, string $status, ?string $note, DiscordTickets $tickets): void
    {
        $open = InvalidationRequest::open()->where('replay_id', $invalidationRequest->replay_id)->get();
        foreach ($open as $ticket) {
            $ticket->forceFill([
                'status' => $status,
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ])->save();
        }

        // All requests for a game share one Discord post; announce once.
        if ($posted = $open->firstWhere('discord_thread_id')) {
            $tickets->resolved($posted, $request->user()->player_name);
        }
    }
}
