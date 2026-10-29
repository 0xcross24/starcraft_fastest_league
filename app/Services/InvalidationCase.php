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
        private EloRecalculator $recalculator,
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

    /** The name each account played under in this game, keyed by user id. */
    public function playedNames(string $replayId): Collection
    {
        return Replay::where('replay_id', $replayId)->whereNotNull('user_id')->pluck('player_name', 'user_id');
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
     * Players can post while a request is open, and after a rejection (which
     * reopens it). An approved game is invalidated and stays closed.
     */
    public function acceptsPosts(string $replayId): bool
    {
        $statuses = InvalidationRequest::where('replay_id', $replayId)->pluck('status');

        return $statuses->contains(InvalidationRequest::OPEN)
            || ($statuses->isNotEmpty() && $statuses->every(fn($s) => $s === InvalidationRequest::REJECTED));
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
        // A player posting on a rejected request sends it back to the admins
        if (!$fromAdmin && !$this->isOpen($replayId)) {
            $this->reopen($replayId, $author);
        }

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
     * An admin changing an earlier decision reopens it first.
     */
    public function resolve(string $replayId, string $status, ?User $admin, ?string $note): int
    {
        if ($admin && !$this->isOpen($replayId)) {
            $this->reopen($replayId, $admin, byAdmin: true);
        }

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

        if ($open->isNotEmpty()) {
            InvalidationComment::create(['replay_id' => $replayId, 'user_id' => $admin?->id, 'from_admin' => (bool) $admin, 'event' => $status, 'body' => $note]);
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

    /**
     * Put a decided request back in front of the admins. A player can reopen
     * a rejection; an admin can reopen either decision, and reopening an
     * approval brings the game back (it counts again and its season is
     * re-rated) until it is decided again.
     *
     * @throws \RuntimeException if the game can't be brought back
     */
    public function reopen(string $replayId, User $by, bool $byAdmin = false): void
    {
        $decided = InvalidationRequest::where('replay_id', $replayId)->where('status', '!=', InvalidationRequest::OPEN)->get();
        if ($decided->isEmpty()) {
            return;
        }

        if ($decided->contains('status', InvalidationRequest::APPROVED)) {
            $this->restoreGame($replayId);
        }

        foreach ($decided as $ticket) {
            $ticket->forceFill(['status' => InvalidationRequest::OPEN, 'resolved_by' => null, 'resolved_at' => null, 'resolution_note' => null])->save();
        }

        $event = InvalidationComment::create(['replay_id' => $replayId, 'user_id' => $by->id, 'from_admin' => $byAdmin, 'event' => InvalidationComment::REOPENED]);
        $this->tickets->comment($this->threadId($replayId), $by->player_name, $event);
        if ($byAdmin) {
            $this->notify($replayId, $by, 'reopened', null);
        }
        Log::info('invalidation: reopened', ['replay_id' => $replayId, 'user_id' => $by->id, 'admin' => $byAdmin]);
    }

    /**
     * Undo an invalidation: the rows count again and the season is re-rated
     * from its games, so every later game's points stay right.
     */
    private function restoreGame(string $replayId): void
    {
        $rows = Replay::where('replay_id', $replayId)->get();
        $hash = $rows->first()?->hash;
        // Invalidated games can be uploaded again (Replay::alreadyUploaded); bringing
        // this copy back as well would count the game twice.
        if ($hash && Replay::where('hash', $hash)->where('replay_id', '!=', $replayId)->where('is_invalid', false)->exists()) {
            throw new \RuntimeException('This game was uploaded again after it was invalidated, so it cannot be brought back.');
        }

        Replay::where('replay_id', $replayId)->update(['is_invalid' => false]);
        foreach ($rows->pluck('season_id')->unique() as $seasonId) {
            $this->recalculator->recalculate($seasonId);
        }
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
