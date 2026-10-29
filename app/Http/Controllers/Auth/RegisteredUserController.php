<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlayerName;
use App\Models\User;
use App\Models\Season;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'player_name' => ['required', 'string', 'max:50', 'regex:' . PlayerName::NAME_PATTERN, function ($attribute, $value, $fail) {
                if (PlayerName::isTaken($value)) {
                    $fail('That player name is already taken.');
                }
            }],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $currentSeason = Season::where('is_active', 1)->first();

        if (!$currentSeason) {
            return redirect()->back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'No active season found. Please activate a season.');
        }

        $user = DB::transaction(function () use ($request, $currentSeason) {
            $user = User::create([
                'player_name' => trim($request->player_name),
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $user->ensureStats($currentSeason);

            return $user;
        });

        event(new Registered($user));

        // Do not log in the user after registration. Redirect to login page instead.
        return redirect(route('login'));
    }
}
