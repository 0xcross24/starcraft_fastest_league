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
    'elo_after',
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

  /**
   * Rows that count. Invalidated games stay in the table as history (and in
   * the admin portal) but are hidden from the public lists.
   */
  public function scopeValid($query)
  {
    return $query->where('is_invalid', false);
  }

  public const GAMES_PER_PAGE = 20;

  /**
   * One page of games (distinct replay_id) from $query, newest first. Each
   * game has one row per player, so paging rows directly would split games.
   */
  public static function gamesPage($query): \Illuminate\Contracts\Pagination\LengthAwarePaginator
  {
    return $query->select('replay_id')
      ->selectRaw('MAX(created_at) as uploaded_at')
      ->groupBy('replay_id')
      ->orderByDesc('uploaded_at')
      ->orderByDesc('replay_id')
      ->paginate(self::GAMES_PER_PAGE)
      ->withQueryString();
  }

  /** Points as shown in game tables: +100, -50, 0 (N/A if unknown). */
  public function signedPoints(): string
  {
    if ($this->points === null) {
      return 'N/A';
    }

    return ($this->points > 0 ? '+' : '') . $this->points;
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
