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

        // One set of stats per account, whichever of its names played
        $playerNames = $user->playerNames;
        $stats = $user->stats()->where('season_id', $seasonId)->where('format', $format)->first();
        $rank = $stats?->position();

        return view('profile.edit', [
            'user' => $user,
            'seasons' => $seasons,
            'seasonId' => $seasonId,
            'format' => $format,
            'stats' => $stats,
            'rank' => $rank,
            'playerNames' => $playerNames,
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
