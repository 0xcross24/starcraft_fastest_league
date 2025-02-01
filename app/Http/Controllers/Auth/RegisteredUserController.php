<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Stats;
use App\Models\Season;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'player_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'player_name' => $request->player_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $currentSeason = Season::where('is_active', 1)->first();

        if (!$currentSeason) {
            return redirect()->back()->with('error', 'No active season found. Please activate a season.');
        }

        Stats::create([
            'user_id' => $user->id,
            'wins' => 0,
            'losses' => 0,
            'elo' => 1000, // Add any default values for the stats columns
            'season_id' => $currentSeason->id,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('homepage', absolute: false));
    }
}
