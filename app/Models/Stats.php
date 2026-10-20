<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Stats extends Model
{

    public $timestamps = false; // Disable timestamps
    protected $fillable = ['user_id', 'player_name_id', 'season_id', 'wins', 'losses', 'elo', 'format'];

    protected static function booted(): void
    {
        // Callers that only know the user get the account's registered name.
        static::creating(function (Stats $stats) {
            if (! $stats->player_name_id && $stats->user_id) {
                $stats->player_name_id = User::find($stats->user_id)?->primaryPlayerName()?->id;
            }
        });
    }

    public function playerName()
    {
        return $this->belongsTo(PlayerName::class);
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
