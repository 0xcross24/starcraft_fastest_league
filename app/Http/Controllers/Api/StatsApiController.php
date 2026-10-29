<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerName;
use App\Models\Stats;
use Illuminate\Http\Request;
use App\Services\EloService;
use Illuminate\Support\Facades\Log;

class StatsApiController extends Controller
{
    public function userStats(Request $request)
    {
        // Validate and sanitize inputs - allow most characters but block dangerous ones
        $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>"\&]+$/' // Block control chars, <, >, ", &
            ],
            'season_id' => 'nullable|integer|min:1|max:999',
            'format' => 'nullable|string|in:2v2,3v3',
        ]);

        $username = trim(strip_tags($request->query('username')));
        $seasonId = $request->query('season_id');
        $format = $request->query('format');

        // Log API call with IP and parameters
        Log::info('API /users called', [
            'ip' => $request->ip(),
            'x_forwarded_for' => $request->header('X-Forwarded-For'),
            'username' => $username,
            'season_id' => $seasonId,
            'format' => $format,
            'user_agent' => substr($request->header('User-Agent'), 0, 200), // Limit UA length
        ]);

        // Any of an account's names finds the account's stats
        $playerName = PlayerName::where('name', $username)->with('user')->first();
        if (!$playerName) {
            return response("User not found", 404)
                ->header('Content-Type', 'text/plain');
        }

        $seasonId = $seasonId ?: \App\Models\Season::max('id');
        $formatQuery = $format;
        $eloService = new EloService();

        // Start single-line output
        $output = "Season {$seasonId} | Username: {$playerName->name}";

        if ($formatQuery) {
            $formats = [$formatQuery];
        } else {
            $formats = Stats::where('user_id', $playerName->user_id)
                ->where('season_id', $seasonId)
                ->pluck('format')
                ->toArray();
        }

        foreach ($formats as $fmt) {
            $stats = Stats::where('user_id', $playerName->user_id)
                ->where('season_id', $seasonId)
                ->where('format', $fmt)
                ->first();

            if (!$stats) {
                $output .= " | {$fmt}: Stats not found";
                continue;
            }

            $rank = $stats->position() ?? 'N/A';
            $grade = $eloService->getEloGrade($stats->elo);

            // Append format info in single line
            $output .= " | {$fmt}: Elo: {$stats->elo}, Grade: {$grade}, Wins: {$stats->wins}, Losses: {$stats->losses}, Rank: {$rank}";
        }

        return response($output, 200)
            ->header('Content-Type', 'text/plain');
    }
}
