<?php

namespace App\Http\Controllers;

use App\Models\PlayerName;
use Illuminate\Http\Request;

class PlayerSearchController extends Controller
{
    /**
     * Player names containing the query, those starting with it first. The
     * nav box fetches this as JSON for its suggestions; submitting the form
     * goes straight to the player on an exact match, else to a results page.
     */
    public function index(Request $request)
    {
        $search = mb_substr(trim((string) $request->input('q')), 0, 50);
        $wantsJson = $request->expectsJson();

        $names = collect();
        if ($search !== '') {
            // '!' as the LIKE escape: a backslash means different things in
            // MySQL and sqlite string literals
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
            $names = PlayerName::query()
                ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orderByRaw("LOWER(name) LIKE ? ESCAPE '!' DESC", ["{$escaped}%"])
                ->orderByRaw('LOWER(name)')
                ->limit($wantsJson ? 8 : 50)
                ->pluck('name');
        }

        if ($wantsJson) {
            return response()->json($names->map(fn($name) => ['name' => $name, 'url' => route('player', $name)]));
        }

        $exact = $names->first(fn($name) => mb_strtolower($name) === mb_strtolower($search));
        if ($exact !== null) {
            return redirect()->route('player', $exact);
        }

        return view('players.search', compact('search', 'names'));
    }
}
