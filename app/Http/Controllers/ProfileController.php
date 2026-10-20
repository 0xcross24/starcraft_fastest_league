<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $seasons = \App\Models\Season::all();
        $seasonId = $request->input('season') ?? ($seasons->count() ? $seasons->max('id') : null);
        $format = $request->input('format', '2v2');

        // Each player name has its own stats; the dropdown picks which one.
        $playerNames = $user->playerNames;
        $playerName = $playerNames->firstWhere('id', (int) $request->input('name'))
            ?? $user->primaryPlayerName();

        $stats = $playerName
            ? $playerName->stats()->where('season_id', $seasonId)->where('format', $format)->first()
            : null;

        // Calculate rank for this name in this season/format (match rankings page order for ties)
        $rank = null;
        if ($stats) {
            $allStats = \App\Models\Stats::where('season_id', $seasonId)
                ->where('format', $format)
                ->orderByDesc('elo')
                ->orderBy('id') // secondary sort for tie-breaker, matches rankings page
                ->pluck('id');
            $rank = $allStats->search($stats->id);
            $rank = $rank !== false ? $rank + 1 : null;
        }

        return view('profile.edit', [
            'user' => $user,
            'seasons' => $seasons,
            'seasonId' => $seasonId,
            'format' => $format,
            'stats' => $stats,
            'rank' => $rank,
            'playerNames' => $playerNames,
            'playerName' => $playerName,
        ]);
    }

    /**
     * Download official SFL map files.
     */
    public function downloadMap($filename)
    {
        $allowed = ['OP SFL-.scm', 'SFLClan.scm', 'WkClan  Styler 빠른무한(Ob).scm'];
        if (!in_array($filename, $allowed)) {
            abort(404);
        }
        $filePath = storage_path('app/public/maps/' . $filename);
        if (!file_exists($filePath)) {
            abort(404);
        }
        return response()->download($filePath);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
}
