<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Replay;

class AuditUserStats extends Command
{
    protected $signature = 'audit:user-stats';
    protected $description = 'Audit user stats against replays';

    public function handle()
    {
        // Fetch all users with their stats
        $users = User::with('stats')->get();

        foreach ($users as $user) {
            if ($user->stats) {
                // Count wins and losses from replays
                $wins = Replay::where('user_id', $user->id)->where('winning_team', 1)->count();
                $losses = Replay::where('user_id', $user->id)->where('winning_team', 0)->count();

                // Check if stats differ from the counted values
                if ($user->stats->wins !== $wins || $user->stats->losses !== $losses) {
                    // Update the stats in the database
                    $user->stats->update(['wins' => $wins, 'losses' => $losses]);

                    // Log the update
                    $this->info("Updated stats for user ID {$user->id}: Wins - {$wins}, Losses - {$losses}");
                }
            } else {
                $this->info("$user->player_name has not played any games");
            }
        }
    }
}

