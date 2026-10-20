<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An in-game name owned by a user. A user can own up to MAX_PER_USER of them,
 * and each one has its own stats, elo and rank. The first one is the name the
 * user registered (and logs in) with.
 */
class PlayerName extends Model
{
    public const MAX_PER_USER = 3;

    public const FORMATS = ['2v2', '3v3'];

    protected $fillable = ['user_id', 'name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stats()
    {
        return $this->hasMany(Stats::class);
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

    public static function isTaken(string $name): bool
    {
        return static::named($name)->exists();
    }

    /**
     * Make sure this name has a stats row for every format in the season.
     */
    public function ensureStats(Season $season): void
    {
        foreach (self::FORMATS as $format) {
            Stats::firstOrCreate(
                ['player_name_id' => $this->id, 'season_id' => $season->id, 'format' => $format],
                ['user_id' => $this->user_id, 'wins' => 0, 'losses' => 0, 'elo' => 1000],
            );
        }
    }
}
