<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Season;
use App\Models\Stats;
use App\Models\HistoricalStat;

class ResetSeason extends Command
{
    protected $signature = 'season:reset';
    protected $description = 'End the current season, archive stats, and reset for the new season';

    public function handle()
    {
        // Get the current season (assuming the latest season is the current one)
        $currentSeason = Season::latest()->first();
        if (!$currentSeason) {
            $this->error("No active season found.");
            return;
        }

        // Archive current season stats
        Stats::where('season_id', $currentSeason->season_id)->each(function ($stat) {
            HistoricalStat::create([
                'user_id' => $stat->user_id,
                'season_id' => $stat->season_id,
                'wins' => $stat->wins,
                'losses' => $stat->losses,
                'elo' => $stat->elo,
                'archived_at' => now(),
            ]);
        });

        // Reset stats for the new season
        Stats::where('season_id', $currentSeason->season_id)->update([
            'wins' => 0,
            'losses' => 0,
            'elo' => 1000, // Default Elo rating
        ]);

        // Create a new season
        $newSeason = Season::create([
            'name' => 'Season ' . ($currentSeason->season_id + 1),
            'start_date' => now(),
        ]);

        // Update all user stats to associate with the new season
        Stats::where('season_id', $currentSeason->season_id)->update([
            'season_id' => $newSeason->season_id,
        ]);

        $this->info("Season reset successful. New season started: {$newSeason->name}");
    }
}
