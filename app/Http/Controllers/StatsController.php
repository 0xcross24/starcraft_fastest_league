<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\Season;
use App\Models\Stats;
use App\Services\EloService;
Use Illuminate\Http\Request;

class StatsController extends Controller
{
    protected $eloService;

    public function __construct(EloService $eloService)
    {
        $this->eloService = $eloService;
    }

    public function displayAllRanking(Request $request)
    {
        $searchTerm = $request->input('search');
        $format = $request->input('format', '2v2');

        $seasons = Season::all();
        $activeSeason = $seasons->firstWhere('is_active', true);
        $activeSeasonId = $activeSeason?->id;

        if (!$activeSeasonId) {
            return view('rankings', [
                'usersWithStats' => collect(),
                'seasons' => $seasons,
                'activeSeasonId' => null,
                'noSeasonMessage' => 'No season has started',
            ]);
        }

        $statsQuery = Stats::with('user')
            ->where('season_id', $activeSeasonId)
            ->where('format', $format);

        if ($searchTerm) {
          $matchingUsers = User::search($searchTerm)->get()->pluck('id');
          $statsQuery->whereIn('user_id', $matchingUsers);
        }


        $filteredStats = $statsQuery
            ->orderByDesc('elo')
            ->orderBy('id')
            ->get();

        // Add elo_grade
        foreach ($filteredStats as $stat) {
            $stat->elo_grade = $this->eloService->getEloGrade($stat->elo ?? 1000);
        }

        $usersWithStats = collect([$activeSeasonId => $filteredStats]);

        return view('rankings', compact('usersWithStats', 'seasons', 'activeSeasonId', 'searchTerm'))->with('selectedSeasonId', $activeSeasonId);
    }

    public function searchAjax(Request $request)
    {
        $search = $request->input('search', '');
        $format = $request->input('format', '2v2');

        $activeSeason = Season::where('is_active', true)->first();
        if (!$activeSeason) {
            return response()->json(['error' => 'No active season'], 404);
        }

        // 🔎 Search users in Meilisearch
        $matchingUsers = User::search($search)->get()->pluck('id');

        // Grab stats for those users in the active season + format
        $stats = Stats::with('user')
            ->where('season_id', $activeSeason->id)
            ->where('format', $format)
            ->whereIn('user_id', $matchingUsers)
            ->orderByDesc('elo')
            ->get();

        // Add elo_grade for each result
        foreach ($stats as $stat) {
            $stat->elo_grade = $this->eloService->getEloGrade($stat->elo ?? 1000);
        }

        // Return the rendered Blade table
        return response()->json([
            'html' => view('partials.ranking-table', [
                'stats' => $stats,
                'format' => $format,
            ])->render()
        ]);
    }



    public function calculateElo(array $winners, array $losers, string $format)
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
                ->where('season_id', $activeSeason->id)
                ->where('format', $format)
                ->first();
            if ($stats) {
                $totalWinnerElo += $stats->elo;
            }
        }
        $averageWinnerElo = count($winners) > 0 ? $totalWinnerElo / count($winners) : 1000; // Default to 1000 if empty

        // Calculate the total Elo for losers
        foreach ($losers as $loserId) {
            $stats = Stats::where('user_id', $loserId)
                ->where('season_id', $activeSeason->id)
                ->where('format', $format)
                ->first();
            if ($stats) {
                $totalLoserElo += $stats->elo;
            }
        }
        $averageLoserElo = count($losers) > 0 ? $totalLoserElo / count($losers) : 1000; // Default to 1000 if empty

        // Update Elo ratings for winners and track changes
        foreach ($winners as $winnerId) {
            $stats = Stats::where('user_id', $winnerId)
                ->where('season_id', $activeSeason->id)
                ->where('format', $format)
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
                ->where('season_id', $activeSeason->id)
                ->where('format', $format)
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
