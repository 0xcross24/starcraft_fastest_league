<?php

namespace App\Http\Controllers;

use App\Models\PlayerName;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lets a logged-in user add the other in-game names they play under, so
 * replays recorded under any of them count, each ranked separately.
 */
class PlayerNameController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validateWithBag('playerName', [
            'name' => ['required', 'string', 'max:50', 'regex:/^[^\x00-\x1F\x7F<>"&\/]+$/', function ($attribute, $value, $fail) use ($user) {
                if ($user->playerNames()->count() >= PlayerName::MAX_PER_USER) {
                    $fail('You can have at most ' . PlayerName::MAX_PER_USER . ' usernames.');
                } elseif (PlayerName::isTaken(trim($value))) {
                    $fail('That username is already registered.');
                }
            }],
        ]);

        $playerName = DB::transaction(function () use ($user, $validated) {
            $playerName = $user->playerNames()->create(['name' => trim($validated['name'])]);

            if ($season = Season::where('is_active', 1)->first()) {
                $playerName->ensureStats($season);
            }

            return $playerName;
        });

        return redirect()->route('profile.edit', ['name' => $playerName->id])
            ->with('status', 'username-added');
    }

    public function destroy(Request $request, PlayerName $playerName): RedirectResponse
    {
        abort_unless($playerName->user_id === $request->user()->id, 403);

        // The registered name is the login, and a name with games behind it
        // carries rated history, so neither can be removed.
        if ($playerName->name === $request->user()->player_name) {
            return back()->with('error', 'Your login username cannot be removed.');
        }
        if ($playerName->replays()->exists()) {
            return back()->with('error', 'A username with uploaded replays cannot be removed.');
        }

        $playerName->delete();

        return redirect()->route('profile.edit')->with('status', 'username-removed');
    }
}
