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
        $seasons = Season::all();

        // Retrieve users with their associated stats, grouped by season
        $usersWithStats = Stats::with('user') // Eager load user relationship
            ->orderBy('season_id', 'asc')  // Group by season
            ->orderBy('elo', 'desc')       // Order by Elo in descending order
            ->get()
            ->groupBy('season_id'); // Group stats by season

        // Add Elo grade to each user
        foreach ($usersWithStats as $seasonId => $stats) {
            foreach ($stats as $stat) {
                $elo = $stat->elo ?? 1000;  // Default Elo to 1000 if not available
                $stat->elo_grade = $this->eloService->getEloGrade($elo);
            }
        }

        return view('rankings', compact('usersWithStats', 'seasons'));
    }

    public function calculateElo(array $winners, array $losers)
    {
        // Retrieve the active season
        $activeSeason = Season::where('is_active', true)->first();
        if (!$activeSeason) {
            return ['error' => 'No active season found'];
        }

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
            $stats = Stats::where('user_id', $winnerId)
                ->where('season_id', $activeSeason->id) // Filter by active season
                ->first();
            if ($stats) {
                $totalWinnerElo += $stats->elo;
            }
        }
        $averageWinnerElo = count($winners) > 0 ? $totalWinnerElo / count($winners) : 1000; // Default to 1000 if empty

        // Calculate the total Elo for losers
        foreach ($losers as $loserId) {
            $stats = Stats::where('user_id', $loserId)
                ->where('season_id', $activeSeason->id) // Filter by active season
                ->first();
            if ($stats) {
                $totalLoserElo += $stats->elo;
            }
        }
        $averageLoserElo = count($losers) > 0 ? $totalLoserElo / count($losers) : 1000; // Default to 1000 if empty

        // Update Elo ratings for winners and track changes
        foreach ($winners as $winnerId) {
            $stats = Stats::where('user_id', $winnerId)
                ->where('season_id', $activeSeason->id) // Filter by active season
                ->first();
            if ($stats) {
                // Calculate expected score
                $expectedScore = 1 / (1 + pow(10, ($averageLoserElo - $stats->elo) / 400));
                $eloChange = $kFactor * (1 - $expectedScore);

                // Save current user's Elo before update
                $currentElo[$winnerId] = $stats->elo;

                // Update the user's Elo
                $stats->elo += $eloChange;
                $stats->wins += 1;
                $stats->save();

                // Store the Elo change
                $eloChanges[$winnerId] = $eloChange;
            }
        }

        // Update Elo ratings for losers and track changes
        foreach ($losers as $loserId) {
            $stats = Stats::where('user_id', $loserId)
                ->where('season_id', $activeSeason->id) // Filter by active season
                ->first();
            if ($stats) {
                // Calculate expected score
                $expectedScore = 1 / (1 + pow(10, ($averageWinnerElo - $stats->elo) / 400));
                $eloChange = $kFactor * (0 - $expectedScore);

                // Save current user's Elo before update
                $currentElo[$loserId] = $stats->elo;

                // Update the user's Elo
                $stats->elo += $eloChange;
                $stats->losses += 1;
                $stats->save();

                // Store the Elo change
                $eloChanges[$loserId] = $eloChange;
            }
        }

        return [
            'currentElo' => $currentElo, // Elo before changes
            'eloChanges' => $eloChanges  // Elo rating adjustments
        ];
    }
}
