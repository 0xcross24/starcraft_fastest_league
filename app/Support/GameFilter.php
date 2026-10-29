<?php

namespace App\Support;

use App\Models\PlayerName;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The date / player / race filters shared by the replay list, player pages
 * and the zip download, so a "Download all" zip holds exactly the listed games.
 *
 * Every condition is on individual replay rows (one per player per game), so
 * player + race together mean "games where that player played that race",
 * and race alone means "games with at least one player of that race".
 * Malformed values are ignored rather than rejected, since they come from a
 * page's query string.
 */
class GameFilter
{
    public const RACES = ['Zerg', 'Terran', 'Protoss'];

    /** The day a game was played (as in zip file names); upload time for old rows without one. */
    public const PLAYED_AT = 'COALESCE(start_time, created_at)';

    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?string $player = null,
        public readonly ?string $race = null,
        public readonly ?PlayerName $playerName = null,
    ) {}

    public static function fromRequest(Request $request, bool $withPlayer = true): self
    {
        $date = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
        [$from, $to] = [$date($request->query('from')), $date($request->query('to'))];
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $player = $withPlayer && is_string($request->query('player')) ? trim($request->query('player')) : '';
        $race = $request->query('race');

        return new self(
            from: $from,
            to: $to,
            player: $player !== '' ? $player : null,
            race: in_array($race, self::RACES, true) ? $race : null,
            playerName: $player !== '' ? PlayerName::where('name', $player)->first() : null,
        );
    }

    public function apply($query)
    {
        return $query
            ->when($this->from, fn($q) => $q->whereRaw(self::PLAYED_AT . ' >= ?', [$this->from . ' 00:00:00']))
            ->when($this->to, fn($q) => $q->whereRaw(self::PLAYED_AT . ' < ?', [Carbon::parse($this->to)->addDay()->format('Y-m-d') . ' 00:00:00']))
            // Any of the account's names; an unknown name matches nothing rather than being dropped
            ->when($this->player, fn($q) => $q->where('user_id', $this->playerName?->user_id ?? 0))
            ->when($this->race, fn($q) => $q->where('race', $this->race));
    }

    public function isActive(): bool
    {
        return (bool) ($this->from || $this->to || $this->player || $this->race);
    }

    /** Query-string values, for links and the zip download. */
    public function query(): array
    {
        return array_filter(['from' => $this->from, 'to' => $this->to, 'player' => $this->player, 'race' => $this->race]);
    }

    /** "Oct 1 – Oct 15, 2026", "from Oct 1, 2026", "Oct 6, 2026"... or null. */
    public function dateLabel(): ?string
    {
        $fmt = fn($d) => Carbon::parse($d)->format('M j, Y');

        return match (true) {
            $this->from && $this->to => $this->from === $this->to ? $fmt($this->from) : $fmt($this->from) . ' – ' . $fmt($this->to),
            (bool) $this->from => 'from ' . $fmt($this->from),
            (bool) $this->to => 'until ' . $fmt($this->to),
            default => null,
        };
    }
}
