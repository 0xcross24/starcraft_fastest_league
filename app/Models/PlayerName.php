<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An in-game name owned by a user. A user can play under up to MAX_PER_USER
 * of them, and they all share the account's stats, elo and rank. The first one
 * is the name the user registered with.
 *
 * A removed name that has games is retired rather than deleted: its games stay
 * with the account, and nobody else can register it.
 */
class PlayerName extends Model
{
    public const MAX_PER_USER = 3;

    public const FORMATS = ['2v2', '3v3'];

    // No control characters, and none of < > " & / (the name is used in URLs).
    public const NAME_PATTERN = '/^[^\x00-\x1F\x7F<>"&\/]+$/';

    protected $fillable = ['user_id', 'name'];

    protected function casts(): array
    {
        return ['retired_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The account's stats: every name on an account shares them. */
    public function stats()
    {
        return $this->hasMany(Stats::class, 'user_id', 'user_id');
    }

    public function ensureStats(Season $season): void
    {
        $this->user->ensureStats($season);
    }

    public function replays()
    {
        return $this->hasMany(Replay::class);
    }

    /**
     * Case-insensitive lookup, matching how replay names are matched.
     */
    public function scopeNamed($query, string ...$names)
    {
        return $query->whereIn(DB::raw('LOWER(name)'), array_map('strtolower', $names));
    }

    public function scopeActive($query)
    {
        return $query->whereNull('retired_at');
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    /**
     * Validation for adding a name to $user's account. The account's own
     * retired names can be added back.
     */
    public static function rulesFor(User $user): array
    {
        return ['required', 'string', 'max:50', 'regex:' . self::NAME_PATTERN, function ($attribute, $value, $fail) use ($user) {
            $existing = static::named(trim($value))->first();
            if ($user->playerNames()->count() >= self::MAX_PER_USER) {
                $fail('An account can have at most ' . self::MAX_PER_USER . ' usernames.');
            } elseif ($existing && !($existing->user_id === $user->id && $existing->isRetired())) {
                $fail('That username is already registered.');
            }
        }];
    }

    /**
     * Add a validated name to $user (or bring back one they retired) and give
     * the account stats for the active season.
     */
    public static function addFor(User $user, string $name): self
    {
        return DB::transaction(function () use ($user, $name) {
            $playerName = static::named(trim($name))->where('user_id', $user->id)->first();
            if ($playerName) {
                $playerName->retired_at = null;
                $playerName->save();
            } else {
                $playerName = $user->allPlayerNames()->create(['name' => trim($name)]);
            }

            if ($season = Season::where('is_active', 1)->first()) {
                $user->ensureStats($season);
            }

            return $playerName;
        });
    }

    /**
     * Why this name cannot be removed, or null if it can.
     */
    public function removalBlocker(): ?string
    {
        if ($this->name === $this->user->player_name) {
            return 'The main username cannot be removed.';
        }

        return null;
    }

    /**
     * Take the name off its account. Points, wins and losses stay with the
     * account either way; a name with games is kept (retired) so they stay
     * linked to it, a name without games is deleted and free to register.
     */
    public function remove(): void
    {
        if ($this->replays()->exists()) {
            $this->retired_at = now();
            $this->save();
        } else {
            $this->delete();
        }
    }

    public static function isTaken(string $name): bool
    {
        return static::named($name)->exists();
    }
}
