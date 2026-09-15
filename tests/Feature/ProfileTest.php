<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/profile')
            ->assertOk();
    }

    public function test_email_can_be_updated_and_resets_verification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['email' => 'test@example.com'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_email_must_be_unique(): void
    {
        $existing = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch('/profile', ['email' => $existing->email])
            ->assertSessionHasErrors('email');
    }

    public function test_guests_cannot_view_the_profile_page(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }
}
