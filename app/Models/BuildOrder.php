<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * The embed player needs the bare video id, but both admin forms ask for a
     * full link, so accept either and pull the id out.
     *
     * Returns null when nothing usable is stored, so the view can leave the
     * player out rather than render a broken iframe.
     */
    protected function youtubeEmbedId(): Attribute
    {
        return Attribute::get(function (): ?string {
            $value = trim((string) $this->youtube_url);

            if ($value === '') {
                return null;
            }

            if (preg_match('~^[A-Za-z0-9_-]{11}$~', $value)) {
                return $value;
            }

            // youtu.be/ID, /embed/ID, /shorts/ID, watch?v=ID
            if (preg_match('~(?:youtu\.be/|/embed/|/shorts/|[?&]v=)([A-Za-z0-9_-]{11})~', $value, $matches)) {
                return $matches[1];
            }

            return null;
        });
    }
}
