<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Stats extends Model {
    
    public $timestamps = false; // Disable timestamps
    protected $fillable = ['user_id', 'wins', 'losses', 'elo'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
