<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A player in a game asking admins to invalidate it. One per player per game;
 * an admin approves (the game is invalidated) or rejects every open request
 * for the game at once.
 */
class InvalidationRequest extends Model
{
    public const REASONS = [
        'wrong_result' => 'Wrong result (winner recorded incorrectly)',
        'not_league' => 'Not a league game (practice / fun game)',
        'disconnect' => 'Disconnect, lag or game bug',
        'rule_violation' => 'Rule violation (cheating, banned strategy, wrong map)',
        'wrong_player' => 'Wrong player / smurf / shared account',
        'other' => 'Other',
    ];

    public const OPEN = 'open';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = ['replay_id', 'user_id', 'player_name_id', 'reason', 'justification', 'discord_thread_id'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function playerName()
    {
        return $this->belongsTo(PlayerName::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::OPEN);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    /**
     * The game's replay rows.
     *
     * @return Collection<int, Replay>
     */
    public function rows(): Collection
    {
        return Replay::where('replay_id', $this->replay_id)->orderBy('team')->get();
    }

    /**
     * $user's row in the game, or null if none of their names played in it.
     */
    public static function participantRow(User $user, string $replayId): ?Replay
    {
        return Replay::where('replay_id', $replayId)
            ->whereIn('player_name_id', $user->playerNames()->select('id'))
            ->first();
    }
}
