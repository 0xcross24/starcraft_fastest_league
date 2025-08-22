<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Stats;
use App\Models\Season;

class InitStatsForNewSeason extends Command
{
    protected $signature = 'season:init-stats {season_id}';
    protected $description = 'Initialize stats for all users for a new season';

    public function handle()
    {
        $seasonId = $this->argument('season_id');
        $season = Season::find($seasonId);
        if (!$season) {
            $this->error('Season not found.');
            return 1;
        }
        $users = User::all();
        $count = 0;
        foreach ($users as $user) {
            // Only create if not already exists
            if (!Stats::where('user_id', $user->id)->where('season_id', $seasonId)->exists()) {
                Stats::create([
                    'user_id' => $user->id,
                    'season_id' => $seasonId,
                    'elo' => 1000,
                    'wins' => 0,
                    'losses' => 0,
                    'format' => '2v2', // or null, or loop for both 2v2/3v3 if needed
                ]);
                Stats::create([
                    'user_id' => $user->id,
                    'season_id' => $seasonId,
                    'elo' => 1000,
                    'wins' => 0,
                    'losses' => 0,
                    'format' => '3v3',
                ]);
                $count++;
            }
        }
        $this->info("Initialized stats for $count users for season $seasonId.");
        return 0;
    }
}
