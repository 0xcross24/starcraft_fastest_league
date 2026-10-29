<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One message in a game's invalidation discussion. A player's latest agree /
 * dispute is their stance; whoever asked for the invalidation agrees.
 */
class InvalidationComment extends Model
{
    public const AGREE = 'agree';
    public const DISPUTE = 'dispute';

    protected $fillable = ['replay_id', 'user_id', 'stance', 'from_admin', 'body'];

    protected $casts = ['from_admin' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
