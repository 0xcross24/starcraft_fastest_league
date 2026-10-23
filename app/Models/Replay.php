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
    'player_name_id',
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
    'format',
    'is_invalid',
  ];

  /**
   * Whether this game is already on record. Rows an admin invalidated don't
   * count, so the corrected upload of that game can go through; the invalid
   * rows stay as history.
   */
  public static function alreadyUploaded(string $fingerprint): bool
  {
    return static::where('hash', $fingerprint)->where('is_invalid', false)->exists();
  }

  function user()
  {
    return $this->hasMany(User::class);
  }

  public function playerName()
  {
    return $this->belongsTo(PlayerName::class);
  }

  public function season()
  {
    return $this->belongsTo(Season::class);
  }
}
