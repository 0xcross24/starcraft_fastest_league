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
        $format = $request->query('format');

        $eloService = new EloService();

        $output = "Season ID: {$seasonId}\n";
        $output .= "Username: {$user->player_name}\n";

        if ($format) {
            // Single format requested
            $stats = Stats::where('user_id', $user->id)
                ->where('season_id', $seasonId)
                ->where('format', $format)
                ->first();

            if (!$stats) {
                return response("Error: Stats not found", 404)
                    ->header('Content-Type', 'text/plain');
            }

            $allStats = Stats::where('season_id', $seasonId)
                ->where('format', $format)
                ->orderByDesc('elo')
                ->orderBy('id')
                ->get();

            $rank = $allStats->search(fn($s) => $s->user_id == $user->id);
            $rank = $rank !== false ? $rank + 1 : "N/A";
            $grade = $eloService->getEloGrade($stats->elo);

            $output .= "{$format} → Elo: {$stats->elo}, Grade: {$grade}, Wins: {$stats->wins}, Losses: {$stats->losses}, Rank: {$rank}";
        } else {
            // All formats for this user/season
            $formats = Stats::where('user_id', $user->id)
                ->where('season_id', $seasonId)
                ->pluck('format');

            foreach ($formats as $fmt) {
                $stats = Stats::where('user_id', $user->id)
                    ->where('season_id', $seasonId)
                    ->where('format', $fmt)
                    ->first();

                $allStats = Stats::where('season_id', $seasonId)
                    ->where('format', $fmt)
                    ->orderByDesc('elo')
                    ->orderBy('id')
                    ->get();

                $rank = $allStats->search(fn($s) => $s->user_id == $user->id);
                $rank = $rank !== false ? $rank + 1 : "N/A";
                $grade = $eloService->getEloGrade($stats->elo);

                $output .= "{$fmt} → Elo: {$stats->elo}, Grade: {$grade}, Wins: {$stats->wins}, Losses: {$stats->losses}, Rank: {$rank}\n";
            }

            // Trim trailing newline
            $output = rtrim($output);
        }

        return response($output, 200)
            ->header('Content-Type', 'text/plain');
    }
}
