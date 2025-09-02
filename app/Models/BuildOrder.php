<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildOrder extends Model
{
    protected $table = 'build_orders';
    protected $fillable = [
        'title',
        'description',
        'race',
        'matchup',
        'steps',
        'youtube_url',
    ];

    protected $casts = [
        'matchup' => 'array',
    ];
}
