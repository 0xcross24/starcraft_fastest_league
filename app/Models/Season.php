<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

class Season extends Model
{
  use HasFactory;

  protected $table = 'seasons';
  protected $primaryKey = 'id';
  protected $fillable = ['is_active', 'starts_at', 'ends_at'];
  public $timestamps = false; // Disable timestamps

  protected $casts = [
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
  ];

  /**
   * When this season ends: its recorded end, or for the running season the
   * next scheduled season change (1 Jan/Apr/Jul/Oct, see bootstrap/app.php).
   */
  public function endsAt(): ?Carbon
  {
    return $this->ends_at ?? ($this->is_active ? self::nextScheduledStart() : null);
  }

  public static function nextScheduledStart(?Carbon $from = null): Carbon
  {
    return ($from ?? Carbon::now())->copy()->startOfQuarter()->addQuarterNoOverflow();
  }

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
