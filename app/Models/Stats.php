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

    /**
     * 1-based position in its season and format: higher elo first, ties by row
     * id (the rankings page order).
     */
    public function position(): int
    {
        return static::where('season_id', $this->season_id)
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
