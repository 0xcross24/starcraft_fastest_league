<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Replay;
use App\Models\Stats;

class StatsController extends Controller {

    public function displayStats() {
        $user = Auth::user();

        $stats = $user->stats;

        return view('dashboard', compact('stats'));
    }

    public function updateStats($user_id)
    {
        // Find the id of the user_id 
        $user = User::where('id', $user_id)->first();

        // Find all replays where winning_team = 1 or 0 for user_id
        $replays = Replay::where('user_id', $user_id)->get();

        $stats = $user->stats()->firstOrCreate(
            ['user_id' => $user->id], // conditions to find existing stats
            ['wins' => 0, 'losses' => 0, 'elo' => 1000] // default values if creating new
        );

        foreach ($replays as $replay) {
            if ($replay->winning_team == 1) {
                $stats->increment('wins'); // Increment wins if the user was on the winning team
            } else {
                $stats->increment('losses'); // Increment losses if the user was on the losing team
            }
        }
    }
}
