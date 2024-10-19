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
      'replay_id',
      'player_name',
      'winning_team',
      'start_time',
      'replay_file',
      'team',
      'hash',
  ];

    function account() {
      return $this->hasMany(Account::class);
    }

    function map() {
      return $this->hasOne(Map::class);
    }
}
