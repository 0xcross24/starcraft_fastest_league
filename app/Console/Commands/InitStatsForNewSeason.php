<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Season;

class InitStatsForNewSeason extends Command
{
    protected $signature = 'season:init-stats {season_id}';
    protected $description = 'Initialize stats for all player names for a new season';

    public function handle()
    {
        $seasonId = $this->argument('season_id');
        $season = Season::find($seasonId);
        if (!$season) {
            $this->error('Season not found.');
            return 1;
        }
        $count = 0;
        foreach (User::all() as $user) {
            // Only create if not already exists
            if (!$user->stats()->where('season_id', $seasonId)->exists()) {
                $user->ensureStats($season);
                $count++;
            }
        }
        $this->info("Initialized stats for $count accounts for season $seasonId.");
        return 0;
    }
}
