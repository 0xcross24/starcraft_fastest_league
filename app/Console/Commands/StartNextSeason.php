<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Season;
use App\Models\User;
use App\Models\Stats;

class StartNextSeason extends Command
{
    protected $signature = 'season:start-next';
    protected $description = 'End the current season and start the next one.';

    public function handle()
    {
        // End the current active season
        $current = Season::where('is_active', 1)->first();
        if ($current) {
            $current->is_active = 0;
            $current->save();
        }

        // Create the next season
        $lastSeason = Season::orderByDesc('id')->first();
        $nextSeasonId = $lastSeason ? $lastSeason->id + 1 : 1;
        $season = new Season();
        $season->id = $nextSeasonId;
        $season->is_active = 1;
        $season->save();

        // For each user, create stats for 2v2 and 3v3 for the new season
        $users = User::all();
        $formats = ['2v2', '3v3'];
        foreach ($users as $user) {
            foreach ($formats as $format) {
                Stats::create([
                    'wins' => 0,
                    'losses' => 0,
                    'elo' => 1000,
                    'format' => $format,
                    'user_id' => $user->id,
                    'season_id' => $nextSeasonId,
                ]);
            }
        }

        $this->info("Season {$nextSeasonId} started. Previous season ended. Stats reset for all users and formats.");
        return 0;
    }
}
