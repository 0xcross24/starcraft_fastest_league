<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Stats;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        // Get the current active season
        $season = Season::where('is_active', 1)->first();
        if (!$season) {
            return view('homepage', [
                'season' => null,
                'top2v2' => collect(),
                'top3v3' => collect(),
            ]);
        }

        // Get top 5 for 2v2 and 3v3 for the current season
        $top2v2 = Stats::with('user')
            ->where('season_id', $season->id)
            ->where('format', '2v2')
            ->orderByDesc('elo')
            ->limit(5)
            ->get();
        $top3v3 = Stats::with('user')
            ->where('season_id', $season->id)
            ->where('format', '3v3')
            ->orderByDesc('elo')
            ->limit(5)
            ->get();

        // Get 5 most recent replays (optionally filter by current season)
        $recentReplays = \App\Models\Replay::with('season')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Get the 5 most recent replay_ids and group all players for each replay

        // MySQL does not allow orderBy with distinct, so use a subquery to get the latest 5 replay_ids
        $recentReplayIds = \App\Models\Replay::select('replay_id', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->unique('replay_id')
            ->take(3)
            ->pluck('replay_id');

        $recentReplayGroups = collect();
        foreach ($recentReplayIds as $replayId) {
            $group = \App\Models\Replay::with('season')
                ->where('replay_id', $replayId)
                ->orderBy('team')
                ->get();
            if ($group->count()) {
                $recentReplayGroups->push($group);
            }
        }

        return view('homepage', [
            'season' => $season,
            'top2v2' => $top2v2,
            'top3v3' => $top3v3,
            'recentReplays' => $recentReplays,
            'recentReplayGroups' => $recentReplayGroups,
        ]);
    }
}
