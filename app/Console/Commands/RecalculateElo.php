<?php

namespace App\Console\Commands;

use App\Models\PlayerName;
use App\Models\Season;
use App\Models\Stats;
use App\Services\EloRecalculator;
use Illuminate\Console\Command;

class RecalculateElo extends Command
{
    protected $signature = 'elo:recalculate {season?* : Season ids (default: all)} {--dry-run : Show the new ratings without saving}';
    protected $description = 'Re-rate seasons from their stored games with the current points table.';

    public function handle(EloRecalculator $recalculator): int
    {
        $seasonIds = $this->argument('season') ?: Season::orderBy('id')->pluck('id')->all();
        $names = PlayerName::pluck('name', 'id');

        foreach ($seasonIds as $seasonId) {
            $before = Stats::where('season_id', $seasonId)->get()->keyBy(fn($s) => "{$s->player_name_id}|{$s->format}");
            $result = $recalculator->recalculate((int) $seasonId, write: !$this->option('dry-run'));

            $this->info("Season $seasonId: {$result['games']} games");
            $rows = collect($result['stats'])->map(function ($new, $key) use ($before, $names) {
                [$id, $format] = explode('|', $key, 2);
                $old = $before[$key] ?? null;

                return [$format, $names[$id] ?? "#$id", $old?->elo, $new['elo'], "{$new['wins']}-{$new['losses']}"];
            })->sortBy([[0, 'asc'], [3, 'desc']])->values()->all();
            $this->table(['Format', 'Name', 'Old ELO', 'New ELO', 'W-L'], $rows);
        }

        $this->line($this->option('dry-run') ? 'Dry run: nothing saved.' : 'Saved.');

        return 0;
    }
}
