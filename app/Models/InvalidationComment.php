<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One message in a game's invalidation discussion. A player's latest agree /
 * dispute is their stance; whoever asked for the invalidation agrees. Rows
 * with an event record a decision or a reopening.
 */
class InvalidationComment extends Model
{
    public const AGREE = 'agree';
    public const DISPUTE = 'dispute';

    public const REOPENED = 'reopened';

    protected $fillable = ['replay_id', 'user_id', 'stance', 'from_admin', 'body', 'event'];

    protected $casts = ['from_admin' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
