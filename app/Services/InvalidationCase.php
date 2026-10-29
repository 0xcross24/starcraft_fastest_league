<?php

namespace App\Services;

use App\Models\InvalidationComment;
use App\Models\InvalidationRequest;
use App\Models\Replay;
use App\Models\User;
use App\Notifications\InvalidationUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Everything about one game's invalidation requests: who played, where each
 * player stands, the discussion, and closing it (by an admin, or on its own
 * once every player in the game agrees).
 */
class InvalidationCase
{
    public function __construct(
        private DiscordTickets $tickets,
        private ReplayInvalidator $invalidator,
    ) {}

    /** Accounts that played in the game. */
    public function participants(string $replayId): Collection
    {
        return User::whereIn('id', Replay::where('replay_id', $replayId)->whereNotNull('user_id')->select('user_id'))->get();
    }

    /**
     * Each participant's stance: 'agree' (asked, or agreed), 'dispute', or
     * null (hasn't said). Keyed by user id.
     */
    public function stances(string $replayId): Collection
    {
        $requesters = InvalidationRequest::where('replay_id', $replayId)->pluck('user_id')->all();
        $latest = InvalidationComment::where('replay_id', $replayId)
            ->whereNotNull('stance')
            ->orderBy('id')
            ->pluck('stance', 'user_id'); // later rows overwrite earlier ones

        return $this->participants($replayId)->mapWithKeys(fn(User $u) => [
            $u->id => $latest[$u->id] ?? (in_array($u->id, $requesters, true) ? InvalidationComment::AGREE : null),
        ]);
    }

    public function comments(string $replayId): Collection
    {
        return InvalidationComment::with('user')->where('replay_id', $replayId)->orderBy('id')->get();
    }

    public function isOpen(string $replayId): bool
    {
        return InvalidationRequest::open()->where('replay_id', $replayId)->exists();
    }

    /**
     * A new request was saved: post it to Discord and tell the other players.
     */
    public function opened(InvalidationRequest $request): void
    {
        $request->update(['discord_thread_id' => $this->tickets->open($request)]);
        $this->notify($request->replay_id, $request->user, 'opened', $request->justification, $request->reasonLabel());
        $this->autoApproveIfEveryoneAgrees($request->replay_id);
    }

    /**
     * A player in the game (or an admin) adds to the discussion.
     */
    public function comment(User $author, string $replayId, ?string $stance, ?string $body, bool $fromAdmin = false): InvalidationComment
    {
        $comment = InvalidationComment::create([
            'replay_id' => $replayId,
            'user_id' => $author->id,
            'stance' => $fromAdmin ? null : $stance,
            'from_admin' => $fromAdmin,
            'body' => $body,
        ]);

        $this->tickets->comment($this->threadId($replayId), $author->player_name, $comment);
        $this->notify($replayId, $author, $fromAdmin ? 'admin_reply' : ($stance ?? 'comment'), $body);

        if (!$fromAdmin) {
            $this->autoApproveIfEveryoneAgrees($replayId);
        }

        return $comment;
    }

    /**
     * Approve (invalidate the game) or reject, closing every open request for
     * it. $admin is null when the players agreed and it closed on its own.
     */
    public function resolve(string $replayId, string $status, ?User $admin, ?string $note): int
    {
        $count = 0;
        if ($status === InvalidationRequest::APPROVED) {
            $count = $this->invalidator->invalidate(Replay::where('replay_id', $replayId)->get());
        }

        $open = InvalidationRequest::open()->where('replay_id', $replayId)->get();
        foreach ($open as $ticket) {
            $ticket->forceFill([
                'status' => $status,
                'resolved_by' => $admin?->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ])->save();
        }

        // All requests for a game share one Discord post; announce once.
        if ($posted = $open->firstWhere('discord_thread_id')) {
            $this->tickets->resolved($posted, $admin?->player_name ?? 'all players agreed');
        }
        if ($open->isNotEmpty()) {
            $this->notify($replayId, $admin, $status, $note);
        }

        return $count;
    }

    private function autoApproveIfEveryoneAgrees(string $replayId): void
    {
        $stances = $this->stances($replayId);
        if (!$this->isOpen($replayId) || $stances->isEmpty() || $stances->contains(fn($s) => $s !== InvalidationComment::AGREE)) {
            return;
        }

        $count = $this->resolve($replayId, InvalidationRequest::APPROVED, null, 'Every player in the game agreed.');
        Log::info('invalidation: auto-approved, all players agreed', ['replay_id' => $replayId, 'rows' => $count]);
    }

    private function threadId(string $replayId): ?string
    {
        return InvalidationRequest::where('replay_id', $replayId)->whereNotNull('discord_thread_id')->value('discord_thread_id');
    }

    /**
     * Email every player in the game except whoever caused the update. Mail
     * trouble is logged and never stops the request.
     */
    private function notify(string $replayId, ?User $author, string $event, ?string $body, ?string $reason = null): void
    {
        $recipients = $this->participants($replayId)->reject(fn(User $u) => $author && $u->is($author));
        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new InvalidationUpdate($replayId, $event, $author?->player_name, $body, $reason));
        } catch (\Throwable $e) {
            Log::error('invalidation: email failed', ['replay_id' => $replayId, 'event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
