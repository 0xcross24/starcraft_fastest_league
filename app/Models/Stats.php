<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Stats extends Model
{

    public $timestamps = false; // Disable timestamps
    // One row per account, season and format: every name on the account counts toward it.
    protected $fillable = ['user_id', 'season_id', 'wins', 'losses', 'elo', 'format'];

    /** Rows that have played at least one game: the only ones ranked. */
    public function scopeRanked($query)
    {
        return $query->whereRaw('wins + losses > 0');
    }

    public function hasPlayed(): bool
    {
        return $this->wins + $this->losses > 0;
    }

    /**
     * 1-based position in its season and format among accounts that have
     * played: higher elo first, ties by row id (the rankings page order).
     * Null until this account has played a game there.
     */
    public function position(): ?int
    {
        if (!$this->hasPlayed()) {
            return null;
        }

        return static::ranked()->where('season_id', $this->season_id)
            ->where('format', $this->format)
            ->where(fn($q) => $q->where('elo', '>', $this->elo)
                ->orWhere(fn($tie) => $tie->where('elo', $this->elo)->where('id', '<', $this->id)))
            ->count() + 1;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
