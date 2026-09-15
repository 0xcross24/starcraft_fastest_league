<?php

namespace Tests\Feature\Auth;

use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_registration_without_an_active_season_creates_nothing(): void
    {
        $this->post('/register', [
            'player_name' => 'Orphan',
            'email' => 'orphan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $this->assertNull(User::where('player_name', 'Orphan')->first());
        $this->assertSame(0, User::count());
        $this->assertSame(0, Stats::count());
    }

    public function test_a_failure_creating_stats_rolls_back_the_user(): void
    {
        Season::create(['is_active' => 1]);

        // Force the second write inside the transaction to fail.
        Schema::drop('stats');

        try {
            $this->post('/register', [
                'player_name' => 'Rollback',
                'email' => 'rollback@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
        } catch (\Throwable) {
            // The failure itself is not what is under test.
        }

        $this->assertSame(0, User::where('player_name', 'Rollback')->count());
    }
}
