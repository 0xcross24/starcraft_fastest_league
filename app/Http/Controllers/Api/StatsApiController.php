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
            return response()->json(['error' => 'Missing username'], 400);
        }
        $user = User::where('player_name', $username)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $seasonId = $request->query('season_id');
        $format = $request->query('format');
        if (!$seasonId) {
            $seasonId = \App\Models\Season::max('id');
        }

        $eloService = new EloService();
        if ($format) {
            // Single format requested
            $stats = Stats::where('user_id', $user->id)
                ->where('season_id', $seasonId)
                ->where('format', $format)
                ->first();
            if (!$stats) {
                return response()->json(['error' => 'Stats not found'], 404);
            }
            $allStats = Stats::where('season_id', $seasonId)
                ->where('format', $format)
                ->orderByDesc('elo')
                ->orderBy('id')
                ->get();
            $rank = $allStats->search(function ($s) use ($user) {
                return $s->user_id == $user->id;
            });
            $rank = $rank !== false ? $rank + 1 : null;
            $grade = $eloService->getEloGrade($stats->elo);
            return response()->json([
                'username' => $user->player_name,
                'season_id' => $seasonId,
                'format' => $format,
                'elo' => $stats->elo,
                'grade' => $grade,
                'wins' => $stats->wins,
                'losses' => $stats->losses,
                'rank' => $rank,
            ]);
        } else {
            // All formats for this user/season
            $formats = Stats::where('user_id', $user->id)
                ->where('season_id', $seasonId)
                ->pluck('format');
            $result = [];
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
                $rank = $allStats->search(function ($s) use ($user) {
                    return $s->user_id == $user->id;
                });
                $rank = $rank !== false ? $rank + 1 : null;
                $grade = $eloService->getEloGrade($stats->elo);
                $result[$fmt] = [
                    'elo' => $stats->elo,
                    'grade' => $grade,
                    'wins' => $stats->wins,
                    'losses' => $stats->losses,
                    'rank' => $rank,
                ];
            }
            return response()->json([
                'username' => $user->player_name,
                'season_id' => $seasonId,
                'formats' => $result,
            ]);
        }
    }
}
