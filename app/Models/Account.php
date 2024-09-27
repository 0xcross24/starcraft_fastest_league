<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model {
  use HasFactory;

  protected $table = 'accounts';
  protected $primaryKey = 'account_id';
  protected $fillable = ['name'];
  public $timestamps = false;

  public function user()
  {
    return $this->belongsToOne(User::class);
  }

  public function replay()
  {
    return $this->hasMany(Replay::class);
  }
}