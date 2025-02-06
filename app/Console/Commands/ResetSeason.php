<?php

namespace App\Console\Commands;

use App\Models\Season;
use App\Models\User;
use App\Models\Stats;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ResetSeason extends Command
{
    protected $signature = 'season:start';
    protected $description = 'Start a new season and reset player stats';

    public function handle()
    {
        // Get the last active season to get the latest 'id' (used as season number)
        $lastSeason = Season::where('is_active', true)->first();
        $seasonId = $lastSeason ? $lastSeason->id + 1 : 1;  // Use 'id' as the season number

        // Create a new season record with the new season_id and set it as active
        $season = Season::create([
            'id' => $seasonId,   // Use the incremented 'id' as the season number
            'is_active' => true,  // Set this season as active
            'created_at' => Carbon::now(), // Set the current timestamp for created_at
            'updated_at' => Carbon::now(), // Set the current timestamp for updated_at
        ]);

        // If there's an active season, deactivate it
        if ($lastSeason) {
            $lastSeason->update(['is_active' => false]);
        }

        // Loop through all users and reset their stats for the new season
        $users = User::all();

        foreach ($users as $user) {
            // Create a new record for the player in the new season with reset stats
            Stats::create([
                'user_id' => $user->id,
                'season_id' => $season->id,
                'elo' => 1000,  // Set the starting Elo (can be changed to a default value)
                'wins' => 0,     // Reset wins
                'losses' => 0,   // Reset losses
            ]);
        }

        $this->info('New season started!');
    }
}
