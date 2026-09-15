<?php

namespace Tests\Feature\Auth;

use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Season::create(['is_active' => 1]);

        $response = $this->post('/register', [
            'player_name' => 'TestPlayer',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Registration intentionally does not log the user in.
        $this->assertGuest();
        $response->assertRedirect(route('login'));

        $user = User::where('player_name', 'TestPlayer')->firstOrFail();

        $this->assertSame(2, Stats::where('user_id', $user->id)->count());
    }

    public function test_registration_without_an_active_season_still_creates_the_user(): void
    {
        $this->post('/register', [
            'player_name' => 'Orphan',
            'email' => 'orphan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        // Documents current behaviour: the user row is created before the
        // active-season check, so a failed registration leaves a user with
        // no stats rather than rolling back.
        $user = User::where('player_name', 'Orphan')->first();

        $this->assertNotNull($user);
        $this->assertSame(0, Stats::where('user_id', $user->id)->count());
    }
}
