<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Map extends Model {
  use HasFactory;

  protected $table = 'maps';
  protected $primaryKey = 'map_id';

  function season() {
    return $this->belongsToMany(Season::class);
  }

  function replay() {
    return $this->belongsToMany(Replay::class);
  }
}