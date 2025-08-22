<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Stats extends Model
{

    public $timestamps = false; // Disable timestamps
    protected $fillable = ['user_id', 'season_id', 'wins', 'losses', 'elo', 'format'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
