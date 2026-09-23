<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class BuildOrder extends Model
{
    use SoftDeletes;

    public const PHASES = ['Opener', 'Mid game', 'Late game'];

    /**
     * Depth is capped so a breadcrumb or a descendant walk cannot run away if a
     * cycle ever reaches the database despite the guards.
     */
    private const MAX_DEPTH = 10;

    protected $table = 'build_orders';
    protected $fillable = [
        'seed_key',
        'seed_hash',
        'seed_version',
        'parent_id',
        'title',
        'description',
        'race',
        'phase',
        'position',
        'matchup',
        'steps',
        'youtube_url',
    ];

    protected $casts = [
        'matchup' => 'array',
        'position' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function scopeOpeners(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Nearest parent first, so a breadcrumb reverses it.
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;
        $depth = 0;

        while ($current && $depth < self::MAX_DEPTH) {
            $ancestors->push($current);
            $current = $current->parent;
            $depth++;
        }

        return $ancestors;
    }

    /**
     * Every build below this one. Used to keep a build from being re-parented
     * onto its own descendant, which would detach the subtree into a cycle.
     */
    public function descendantIds(): array
    {
        $ids = [];
        $frontier = [$this->id];
        $depth = 0;

        while ($frontier !== [] && $depth < self::MAX_DEPTH) {
            $frontier = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
            $depth++;
        }

        return $ids;
    }

    /**
     * The builds this one may continue from: anything except itself and its own
     * descendants.
     */
    public static function possibleParentsFor(?self $build)
    {
        $query = static::query()->orderBy('race')->orderBy('title');

        if ($build && $build->exists) {
            $query->whereNotIn('id', array_merge([$build->id], $build->descendantIds()));
        }

        return $query->get();
    }

    /**
     * The embed player needs the bare video id, but both admin forms ask for a
     * full link, so accept either and pull the id out.
     *
     * Returns null when nothing usable is stored, so the view can leave the
     * player out rather than render a broken iframe.
     */
    /**
     * Steps as display HTML, so a run of "- " lines becomes a real bulleted
     * list instead of showing the dashes.
     *
     * Builds are plain text in the database and there is no separate notes
     * field, so the convention is a block of timings, a blank line, then
     * "Notes:" and its bullets. Everything that is not a bullet keeps its own
     * line breaks.
     *
     * Every line is escaped before any markup is added, so nothing an admin
     * types can inject HTML. That is what makes this safe to print unescaped.
     *
     * The markup carries no classes. Tailwind only scans the content paths in
     * tailwind.config.js, and app/ is not among them, so a class named here
     * would be dropped from the build the moment no Blade file happened to use
     * it too. The show view styles these tags instead.
     */
    protected function stepsHtml(): Attribute
    {
        return Attribute::get(function (): string {
            $html = '';
            $text = [];
            $items = [];

            $flushText = function () use (&$text, &$html): void {
                // Blank lines at either end would otherwise show as a gap next
                // to the list, which already carries its own spacing.
                while ($text !== [] && trim($text[0]) === '') {
                    array_shift($text);
                }
                while ($text !== [] && trim(end($text)) === '') {
                    array_pop($text);
                }

                if ($text === []) {
                    return;
                }

                $html .= '<p>'.e(implode("
", $text)).'</p>';
                $text = [];
            };

            $flushList = function () use (&$items, &$html): void {
                if ($items === []) {
                    return;
                }

                $html .= '<ul>';
                foreach ($items as $item) {
                    $html .= '<li>'.e($item).'</li>';
                }
                $html .= '</ul>';
                $items = [];
            };

            foreach (preg_split('/\R/', (string) $this->steps) as $line) {
                if (preg_match('/^\s*-\s+(.*)$/', $line, $matches)) {
                    $flushText();
                    $items[] = rtrim($matches[1]);
                    continue;
                }

                $flushList();
                $text[] = rtrim($line);
            }

            $flushText();
            $flushList();

            return $html;
        });
    }

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
