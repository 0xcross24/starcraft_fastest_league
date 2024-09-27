<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Season extends Model {
  use HasFactory;

  protected $table = 'seasons';
  protected $primaryKey = 'season_id';

  function map() {
    return $this->hasMany(Map::class);
  }

  function ranking() {
    return $this->hasMany(Ranking::class);
  }
}