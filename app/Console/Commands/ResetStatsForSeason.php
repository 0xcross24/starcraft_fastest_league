<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Stats;

class ResetStatsForSeason extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage: php artisan season:reset-stats {season_id}
     *
     * @var string
     */
    protected $signature = 'season:reset-stats {season_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all stats (elo, wins, losses) for a given season to default values (elo=1000, wins=0, losses=0)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $seasonId = $this->argument('season_id');
        $stats = Stats::where('season_id', $seasonId)->get();
        $count = 0;
        foreach ($stats as $stat) {
            $stat->elo = 1000;
            $stat->wins = 0;
            $stat->losses = 0;
            $stat->save();
            $count++;
        }
        $this->info("Reset stats for {$count} users for season {$seasonId}.");
    }
}
