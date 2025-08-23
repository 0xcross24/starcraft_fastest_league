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

        // Get all stats for the user, grouped by season and format
        $userStats = $user->stats()->get()->groupBy(function ($item) {
            return $item->season_id . '-' . $item->format;
        });

        // Get stats for the selected season/format
        $stats = $userStats[$seasonId . '-' . $format][0] ?? null;

        // Calculate rank for this user in this season/format
        $rank = null;
        if ($seasonId) {
            $allStats = \App\Models\Stats::where('season_id', $seasonId)
                ->where('format', $format)
                ->orderByDesc('elo')
                ->get();
            $rank = $allStats->search(function ($s) use ($user) {
                return $s->user_id == $user->id;
            });
            $rank = $rank !== false ? $rank + 1 : null;
        }

        return view('profile.edit', [
            'user' => $user,
            'seasons' => $seasons,
            'seasonId' => $seasonId,
            'format' => $format,
            'stats' => $stats,
            'rank' => $rank,
            'userStats' => $userStats,
        ]);
    }

    /**
     * Download official SFL map files.
     */
    public function downloadMap($filename)
    {
        $allowed = ['OP SFL-.scm', 'SFLClan.scm'];
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
