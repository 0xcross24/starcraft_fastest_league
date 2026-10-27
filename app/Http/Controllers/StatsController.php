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
        $activeSeason = $seasons->firstWhere('is_active', true);

        if ($seasons->isEmpty()) {
            return view('rankings', [
                'usersWithStats' => collect(),
                'seasons' => collect(),
                'activeSeasonId' => null,
                'noSeasonMessage' => 'No season has started',
            ]);
        }

        $activeSeasonId = $activeSeason ? $activeSeason->id : null;
        if (!$activeSeasonId) {
            return view('rankings', [
                'usersWithStats' => collect(),
                'seasons' => $seasons,
                'activeSeasonId' => null,
                'noSeasonMessage' => 'No season has started',
            ]);
        }

        // Retrieve users with their associated stats, grouped by season
        $usersWithStats = Stats::with('playerName')
            ->orderBy('season_id', 'asc')  // Group by season
            ->orderBy('elo', 'desc')       // Order by Elo in descending order
            ->orderBy('id')                // Tie-breaker for same Elo, matches profile/homepage
            ->get()
            ->groupBy('season_id'); // Group stats by season

        // Add Elo grade to each user
        foreach ($usersWithStats as $seasonId => $stats) {
            foreach ($stats as $stat) {
                $elo = $stat->elo ?? 1000;  // Default Elo to 1000 if not available
                $stat->elo_grade = $this->eloService->getEloGrade($elo);
            }
        }

        return view('rankings', compact('usersWithStats', 'seasons', 'activeSeasonId'));
    }

    /**
     * @param  int[]  $winners  player_name ids
     * @param  int[]  $losers  player_name ids
     */
    public function calculateElo(array $winners, array $losers, string $format)
    {
        // Retrieve the active season
        $activeSeason = Season::where('is_active', true)->first();
        if (!$activeSeason) {
            return ['error' => 'No active season found'];
        }

        $statsFor = fn(array $ids) => Stats::whereIn('player_name_id', $ids)
            ->where('season_id', $activeSeason->id)
            ->where('format', $format)
            ->get()
            ->keyBy('player_name_id');
        $winnerStats = $statsFor($winners);
        $loserStats = $statsFor($losers);

        // Elo before changes, and each player's change from the iCCup table
        $currentElo = $winnerStats->pluck('elo', 'player_name_id')->all() + $loserStats->pluck('elo', 'player_name_id')->all();
        $eloChanges = $this->eloService->rateGame(
            $winnerStats->pluck('elo', 'player_name_id')->all(),
            $loserStats->pluck('elo', 'player_name_id')->all(),
        );

        foreach ([[$winnerStats, 'wins'], [$loserStats, 'losses']] as [$team, $counter]) {
            foreach ($team as $id => $stats) {
                $stats->elo += $eloChanges[$id];
                $stats->{$counter} += 1;
                $stats->save();
            }
        }

        return [
            'currentElo' => $currentElo, // Elo before changes
            'eloChanges' => $eloChanges  // Elo rating adjustments
        ];
    }
}
