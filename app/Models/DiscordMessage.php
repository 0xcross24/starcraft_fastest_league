<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordMessage extends Model
{
    protected $fillable = ['key', 'message_id', 'via', 'format', 'hash', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];
}
