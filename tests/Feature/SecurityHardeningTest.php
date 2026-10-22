<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const TUNNEL = '192.168.1.177';

    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/_test/ip', fn() => response()->json([
            'ip' => request()->ip(),
            'secure' => request()->isSecure(),
        ]));
    }

    public function test_the_tunnel_is_trusted_to_report_the_client_ip_and_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::TUNNEL])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->get('/_test/ip')
            ->assertJson(['ip' => '203.0.113.9', 'secure' => true]);
    }

    public function test_anyone_else_cannot_spoof_their_ip_or_scheme(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->get('/_test/ip')
            ->assertJson(['ip' => '198.51.100.7', 'secure' => false]);
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->withServerVariables(['REMOTE_ADDR' => self::TUNNEL])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_password_reset_requests_are_rate_limited_per_client(): void
    {
        $from = fn(string $ip) => $this->withServerVariables(['REMOTE_ADDR' => self::TUNNEL])
            ->withHeaders(['X-Forwarded-For' => $ip]);

        for ($i = 0; $i < 5; $i++) {
            $from('203.0.113.1')->post('/forgot-password', ['email' => "x{$i}@example.com"]);
        }

        $from('203.0.113.1')->post('/forgot-password', ['email' => 'y@example.com'])->assertStatus(429);
        // A different visitor is unaffected: limits are per real IP, not global.
        $from('203.0.113.2')->post('/forgot-password', ['email' => 'y@example.com'])->assertStatus(302);
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = new User(['player_name' => 'Sneaky', 'email' => 's@example.com', 'password' => 'x', 'role' => 'admin']);

        $this->assertNull($user->role);
    }

    public function test_registration_rejects_names_with_markup_characters(): void
    {
        Season::create(['is_active' => 1]);

        $this->post('/register', [
            'player_name' => '<script>x',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('player_name');

        $this->assertSame(0, User::count());
    }
}
