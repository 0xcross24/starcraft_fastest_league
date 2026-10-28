<?php

namespace Tests\Feature;

use App\Http\Middleware\Authenticate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadAfterLoginTest extends TestCase
{
    use RefreshDatabase;

    private function logIn(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_a_guest_download_returns_to_the_page_and_starts_there_after_login(): void
    {
        $user = User::factory()->create();
        $zip = route('replays.download-zip', ['season' => 1, 'format' => '2v2'], false);

        $this->get($zip, ['referer' => url('/replays?season=1&format=2v2')])->assertRedirect(route('login'));

        // Back to the page, not to the file
        $this->logIn($user)->assertRedirect('/replays?season=1&format=2v2');
        $this->assertSame($zip, session(Authenticate::PENDING_DOWNLOAD));

        // That page starts the download once, then forgets it
        $this->get('/replays?season=1&format=2v2')->assertOk()->assertSee('window.location.href = ' . json_encode($zip, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), false);
        $this->assertNull(session(Authenticate::PENDING_DOWNLOAD));
        $this->get('/replays?season=1&format=2v2')->assertDontSee('window.location.href', false);
    }

    public function test_single_replay_and_map_downloads_work_the_same_way(): void
    {
        $user = User::factory()->create();
        $replay = route('replay.download', ['uuid' => 'abc'], false);

        $this->get($replay, ['referer' => url('/player/Someone?season=2')])->assertRedirect(route('login'));
        $this->logIn($user)->assertRedirect('/player/Someone?season=2');
        $this->assertSame($replay, session(Authenticate::PENDING_DOWNLOAD));

        $this->post('/logout');
        $map = route('maps.download', ['filename' => 'SFLClan.scm'], false);
        $this->get($map, ['referer' => url('/profile')])->assertRedirect(route('login'));
        $this->assertSame($map, session(Authenticate::PENDING_DOWNLOAD));
    }

    public function test_the_homepage_is_used_without_a_usable_referer(): void
    {
        $user = User::factory()->create();
        $zip = route('replays.download-zip', ['season' => 1], false);

        foreach ([[], ['referer' => 'https://evil.example/replays'], ['referer' => url('/login')]] as $headers) {
            $this->post('/logout');
            $this->get($zip, $headers)->assertRedirect(route('login'));
            $this->logIn($user)->assertRedirect(route('homepage', absolute: false));
        }

        // The homepage (guest layout) starts it too
        $this->get('/')->assertSee('window.location.href = ' . json_encode($zip, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), false);
    }

    public function test_guests_never_get_the_download_script(): void
    {
        $this->get(route('replays.download-zip', ['season' => 1]), ['referer' => url('/replays')]);

        // Wandering off instead of logging in must not bounce them back to the login page
        $this->get('/replays')->assertOk()->assertDontSee('window.location.href', false);
        $this->assertNotNull(session(Authenticate::PENDING_DOWNLOAD));
    }

    public function test_other_protected_pages_still_return_to_themselves(): void
    {
        $user = User::factory()->create();

        $this->get('/profile', ['referer' => url('/replays')])->assertRedirect(route('login'));
        $this->logIn($user)->assertRedirect('/profile');
        $this->assertNull(session(Authenticate::PENDING_DOWNLOAD));
    }

    public function test_downloads_are_never_cached_by_the_browser(): void
    {
        $season = \App\Models\Season::create(['is_active' => 1]);
        $dir = storage_path('app/public/uploads/season_' . $season->id);
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/nocache-test.rep", 'replay');
        $user = User::factory()->create();
        $name = $user->primaryPlayerName();
        \App\Models\Replay::create([
            'replay_id' => 'nc1', 'player_name' => $name->name, 'winning_team' => 1, 'replay_file' => 'nocache-test.rep',
            'team' => '1', 'race' => 'Zerg', 'hash' => 'h-nc1', 'apm' => 1, 'eapm' => 1, 'season_id' => $season->id,
            'format' => '2v2', 'points' => 0, 'user_id' => $user->id, 'player_name_id' => $name->id,
        ]);

        try {
            foreach (['/replay/download/nc1', route('replays.download-zip', ['season' => $season->id], false), route('maps.download', ['filename' => 'SFLClan.scm'], false)] as $url) {
                $response = $this->actingAs($user)->get($url);
                $this->assertTrue($response->headers->hasCacheControlDirective('no-store'), "{$url}: " . $response->headers->get('Cache-Control'));
                $this->assertFalse($response->headers->hasCacheControlDirective('public'), $url);
            }
        } finally {
            unlink("{$dir}/nocache-test.rep");
        }
    }
}
