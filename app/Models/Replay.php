<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Replay extends Model
{
  use HasFactory;

  protected $table = 'replays';
  protected $primaryKey = 'id';
  protected $fillable = [
    'user_id',
    'replay_id',
    'player_name',
    'winning_team',
    'start_time',
    'replay_file',
    'team',
    'race',
    'hash',
    'apm',
    'eapm',
    'points',
    'season_id',
  ];

  function user()
  {
    return $this->hasMany(User::class);
  }

  public function season()
  {
    return $this->belongsTo(Season::class);
  }
}
