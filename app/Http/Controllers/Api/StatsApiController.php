<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\EloService;

class StatsApiController extends Controller
{
    public function userStats(Request $request)
    {
        $username = $request->query('username');
        if (!$username) {
            return response("Error: Missing username", 400)
                ->header('Content-Type', 'text/plain');
        }

        $user = User::where('player_name', $username)->first();
        if (!$user) {
            return response("Error: User not found", 404)
                ->header('Content-Type', 'text/plain');
        }

        $seasonId = $request->query('season_id') ?: \App\Models\Season::max('id');
        $formatQuery = $request->query('format');
        $eloService = new EloService();

        $output = "Season ID: {$seasonId}\n";
        $output .= "Username: {$user->player_name}\n";

        if ($formatQuery) {
            $formats = [$formatQuery];
        } else {
            $formats = Stats::where('user_id', $user->id)
                ->where('season_id', $seasonId)
                ->pluck('format')
                ->toArray();
        }

        foreach ($formats as $fmt) {
            $stats = Stats::where('user_id', $user->id)
                ->where('season_id', $seasonId)
                ->where('format', $fmt)
                ->first();

            if (!$stats) {
                $output .= "{$fmt} -> Stats not found\n";
                continue;
            }

            $allStats = Stats::where('season_id', $seasonId)
                ->where('format', $fmt)
                ->orderByDesc('elo')
                ->orderBy('id')
                ->get();

            $rank = $allStats->search(fn($s) => $s->user_id == $user->id);
            $rank = $rank !== false ? $rank + 1 : "N/A";
            $grade = $eloService->getEloGrade($stats->elo);

            // Safe arrow and plain ASCII
            $output .= "{$fmt} -> Elo: {$stats->elo}, Grade: {$grade}, Wins: {$stats->wins}, Losses: {$stats->losses}, Rank: {$rank}\n";
        }

        // Trim trailing newline
        $output = rtrim($output);

        return response($output, 200)
            ->header('Content-Type', 'text/plain');
    }
}
