<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuildOrder extends Model
{
    use SoftDeletes;

    protected $table = 'build_orders';
    protected $fillable = [
        'seed_key',
        'seed_hash',
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
