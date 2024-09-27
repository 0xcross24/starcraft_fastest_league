<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ranking extends Model {
  use HasFactory;

  protected $table = 'rankings';
  protected $primaryKey = 'rank_id';

  function season() {
    return $this->belongsToOne(Season::class);
  }

  function account() {
    return $this->belongsToOne(Account::class);
  }
}