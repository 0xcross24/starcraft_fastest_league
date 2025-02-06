<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Season extends Model
{
  use HasFactory;

  protected $table = 'seasons';
  protected $primaryKey = 'id';
  protected $fillable = ['is_active'];
  public $timestamps = false; // Disable timestamps

  public function isActive()
  {
    return $this->is_active; // Returns the value of the 'is_active' column (true or false)
  }

  public function users()
  {
    return $this->hasMany(User::class);
  }

  public function stats()
  {
    return $this->hasMany(Stats::class);
  }

  public function replays()
  {
    return $this->hasMany(Replay::class);
  }
}
