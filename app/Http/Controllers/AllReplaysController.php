<?php

namespace App\Http\Controllers;

use App\Models\Replay;
use App\Models\Season;
use Illuminate\Http\Request;

class AllReplaysController extends Controller
{
    public function index(Request $request)
    {
        $seasons = Season::all();
        $seasonId = $request->input('season') ?: ($seasons->count() ? $seasons->max('id') : null);
        $format = $request->input('format', '2v2');

        $replays = Replay::where('season_id', $seasonId)
            ->where('format', $format)
            ->orderByDesc('created_at')
            ->get();

        return view('replays.all', compact('seasons', 'seasonId', 'format', 'replays'));
    }
}
