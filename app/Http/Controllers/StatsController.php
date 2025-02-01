<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\Season;
use App\Models\Stats;
use App\Services\EloService;

class StatsController extends Controller
{
    protected $eloService;

    public function __construct(EloService $eloService)
    {
        $this->eloService = $eloService;
    }

    public function displayAllRanking()
    {
        // Retrieve all users with their associated stats, ordered by Elo in descending order
        $usersWithStats = User::with('stats')
            ->join('stats', 'users.id', '=', 'stats.user_id') // Join with stats table
            ->orderBy('stats.elo', 'desc') // Order by Elo in descending order
            ->get();

        foreach ($usersWithStats as $user) {
            $elo = $user->stats->elo;
            $user->elo_grade = $this->eloService->getEloGrade($elo);
        }

        $seasons = Season::all();

        return view('rankings', compact('usersWithStats', 'seasons'));
    }

    public function calculateElo(array $winners, array $losers)
    {
        // Define the K-factor
        $kFactor = 100;

        // Initialize variables for total Elo ratings
        $totalWinnerElo = 0;
        $totalLoserElo = 0;

        // Initialize an array to store Elo changes
        $eloChanges = [];
        $currentElo = [];

        // Calculate the total Elo for winners
        foreach ($winners as $winnerId) {
            $stats = Stats::where('user_id', $winnerId)->first(); // Find the stats record by user ID
            if ($stats) {
                $totalWinnerElo += $stats->elo; // Sum up Elo ratings
            }
        }
        $averageWinnerElo = count($winners) > 0 ? $totalWinnerElo / count($winners) : 0; // Average Elo for winners

        // Calculate the total Elo for losers
        foreach ($losers as $loserId) {
            $stats = Stats::where('user_id', $loserId)->first(); // Find the stats record by user ID
            if ($stats) {
                $totalLoserElo += $stats->elo; // Sum up Elo ratings
            }
        }
        $averageLoserElo = count($losers) > 0 ? $totalLoserElo / count($losers) : 0; // Average Elo for losers

        // Update Elo ratings for winners and track changes
        foreach ($winners as $winnerId) {
            $stats = Stats::where('user_id', $winnerId)->first();
            if ($stats) {
                // Calculate expected score
                $expectedScore = 1 / (1 + pow(10, ($averageLoserElo - $stats->elo) / 400));
                $eloChange = $kFactor * (1 - $expectedScore);
                // Save current user's Elo before update
                $currentElo[] = $stats->elo;
                // Update the user's Elo
                $stats->elo += $eloChange;
                $stats->wins += 1;
                $stats->save(); // Save the updated Elo rating in the Stats table

                // Store the Elo change
                $eloChanges[$winnerId] = $eloChange;
            }
        }

        // Update Elo ratings for losers and track changes
        foreach ($losers as $loserId) {
            $stats = Stats::where('user_id', $loserId)->first();
            if ($stats) {
                // Calculate expected score
                $expectedScore = 1 / (1 + pow(10, ($averageWinnerElo - $stats->elo) / 400));
                $eloChange = $kFactor * (0 - $expectedScore);
                // Save current user's Elo before update
                $currentElo[] = $stats->elo;
                // Update the user's Elo
                $stats->elo += $eloChange;
                $stats->losses += 1;
                $stats->save(); // Save the updated Elo rating in the Stats table

                // Store the Elo change
                $eloChanges[$loserId] = $eloChange;
            }
        }

        $response = [
            'currentElo' => $currentElo, // Current Elo before changes for each player
            'eloChanges' => $eloChanges // Elo changes for each player
        ];

        return $response; // Return the Elo changes for each player
    }
}
