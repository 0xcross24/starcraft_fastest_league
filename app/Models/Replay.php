<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Replay extends Model
{
    use HasFactory;

    protected $table = 'replays';
    protected $primaryKey = 'replay_id';

    function account() {
      return $this->hasMany(Account::class);
    }

    function map() {
      return $this->hasOne(Map::class);
    }
}
