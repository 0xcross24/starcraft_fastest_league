<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\EloService;
use Illuminate\Support\Facades\Log;

class StatsApiController extends Controller
{
    public function userStats(Request $request)
    {
        $username = $request->query('username');
        // Log API call with IP and parameters
        Log::info('API /users called', [
            'ip' => $request->ip(),
            'username' => $username,
            'season_id' => $request->query('season_id'),
            'format' => $request->query('format'),
            'user_agent' => $request->header('User-Agent'),
        ]);
        if (!$username) {
            return response("Error: Missing username", 400)
                ->header('Content-Type', 'text/plain');
        }

        $user = User::where('player_name', $username)->first();
        if (!$user) {
            return response("User not found", 404)
                ->header('Content-Type', 'text/plain');
        }

        $seasonId = $request->query('season_id') ?: \App\Models\Season::max('id');
        $formatQuery = $request->query('format');
        $eloService = new EloService();

        // Start single-line output
        $output = "Season {$seasonId} | Username: {$user->player_name}";

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
                $output .= " | {$fmt}: Stats not found";
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

            // Append format info in single line
            $output .= " | {$fmt}: Elo: {$stats->elo}, Grade: {$grade}, Wins: {$stats->wins}, Losses: {$stats->losses}, Rank: {$rank}";
        }

        return response($output, 200)
            ->header('Content-Type', 'text/plain');
    }
}
