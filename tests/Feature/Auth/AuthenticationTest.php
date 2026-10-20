<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('homepage', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_email_is_matched_regardless_of_case(): void
    {
        $user = User::factory()->create(['email' => 'player@example.com']);

        $this->post('/login', ['email' => 'Player@Example.COM', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_player_name_no_longer_logs_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->player_name, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->post('/login', ['player_name' => $user->player_name, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
