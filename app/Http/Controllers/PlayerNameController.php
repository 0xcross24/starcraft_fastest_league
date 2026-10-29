<?php

namespace App\Http\Controllers;

use App\Models\PlayerName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets a logged-in user add the other in-game names they play under, so
 * replays recorded under any of them count toward the account's one rating.
 */
class PlayerNameController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validateWithBag('playerName', [
            'name' => PlayerName::rulesFor($user),
        ]);

        $playerName = PlayerName::addFor($user, $validated['name']);

        return redirect()->route('profile.edit')->with('status', 'username-added');
    }

    public function makeMain(Request $request, PlayerName $playerName): RedirectResponse
    {
        abort_unless($playerName->user_id === $request->user()->id, 403);
        if ($playerName->isRetired()) {
            return back()->with('error', 'Add that username back before making it your main name.');
        }

        $playerName->makeMain();

        return redirect()->route('profile.edit')->with('status', 'main-name-changed');
    }

    public function destroy(Request $request, PlayerName $playerName): RedirectResponse
    {
        abort_unless($playerName->user_id === $request->user()->id, 403);

        // The main name identifies the account and cannot be removed.
        if ($blocker = $playerName->removalBlocker()) {
            return back()->with('error', $blocker);
        }

        $playerName->remove();

        return redirect()->route('profile.edit')->with('status', 'username-removed');
    }
}
