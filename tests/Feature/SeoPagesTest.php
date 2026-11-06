<?php

namespace Tests\Feature;

use App\Http\Controllers\ReplayController;
use App\Models\BuildOrder;
use App\Models\Replay;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdSense rejected the site for thin content: every page was titled "SFL",
 * none had a description, every templated page was indexable and there was
 * no About / Contact / Privacy page. These pin the fixes.
 */
class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.name' => 'SFL']); // as in production; the clone has no .env
    }

    private function title(string $html): string
    {
        preg_match('#<title>(.*?)</title>#s', $html, $m);

        return html_entity_decode(trim($m[1] ?? ''));
    }

    private function games(User $user, int $count): void
    {
        $season = Season::first() ?? Season::factory()->create();
        $name = $user->playerNames()->first();
        for ($i = 0; $i < $count; $i++) {
            Replay::create([
                'replay_id' => "g{$user->id}-$i", 'player_name' => $name->name, 'winning_team' => 1,
                'replay_file' => 'x.rep', 'team' => '1', 'race' => 'Zerg', 'hash' => "h{$user->id}-$i",
                'apm' => 100, 'eapm' => 80, 'season_id' => $season->id, 'format' => '2v2',
                'points' => 100, 'user_id' => $user->id, 'player_name_id' => $name->id,
            ]);
        }
    }

    public function test_content_pages_have_distinct_titles_and_descriptions(): void
    {
        Season::factory()->create();
        $titles = [];
        foreach (['/', '/rankings', '/replays', '/builds', '/rules', '/about', '/contact', '/privacy', '/login'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $titles[$url] = $this->title($html);
            $this->assertStringContainsString('<meta name="description" content="', $html, $url);
        }

        $this->assertSame(count($titles), count(array_unique($titles)), json_encode($titles));
        $this->assertSame('About SFL - StarCraft Fastest League | SFL', $titles['/about']);
    }

    public function test_build_page_title_and_description_come_from_the_build(): void
    {
        $build = BuildOrder::create([
            'title' => '9 Spawning Pool Speed Lings', 'description' => 'Early pressure.',
            'race' => 'Zerg', 'matchup' => ['PPZ', 'PZ'], 'steps' => "9 Pool",
        ]);

        $html = $this->get("/builds/{$build->id}")->assertOk()->getContent();

        $this->assertSame('9 Spawning Pool Speed Lings - Zerg Build Order | SFL', $this->title($html));
        $this->assertStringContainsString('Zerg build order for PPZ, PZ in StarCraft: Brood War team Fastest. Early pressure.', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url("/builds/{$build->id}").'">', $html);
        $this->assertStringNotContainsString('noindex', $html);
    }

    public function test_templated_and_account_pages_are_noindexed(): void
    {
        $season = Season::factory()->create();

        $this->get('/login')->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/register')->assertSee('noindex', false);
        $this->games(User::factory()->create(['player_name' => 'Someone']), 1);
        $this->get('/games/g'.User::where('player_name', 'Someone')->value('id').'-0')
            ->assertOk()->assertSee('noindex', false);
        // Any filtered or paged variant of an indexed page
        $this->get('/replays?season_id='.$season->id)->assertSee('noindex', false);
        $this->get('/builds?race=Zerg')->assertSee('noindex', false);
        $this->get('/builds')->assertDontSee('noindex', false);
    }

    public function test_player_profiles_are_indexed_only_once_they_have_enough_games(): void
    {
        $new = User::factory()->create(['player_name' => 'Newcomer']);
        $regular = User::factory()->create(['player_name' => 'Regular']);
        $this->games($new, ReplayController::INDEXABLE_PLAYER_GAMES - 1);
        $this->games($regular, ReplayController::INDEXABLE_PLAYER_GAMES);

        $this->get('/player/Newcomer')->assertOk()->assertSee('noindex', false);
        $this->get('/player/Regular')->assertOk()->assertDontSee('noindex', false)
            ->assertSee('<title>Regular - Player Profile | SFL</title>', false);
    }

    public function test_sitemap_lists_content_pages_and_builds_only(): void
    {
        $build = BuildOrder::create(['title' => 'X', 'race' => 'Terran', 'matchup' => ['PT'], 'steps' => 'a']);
        $user = User::factory()->create(['player_name' => 'Regular']);
        $this->games($user, 12);

        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
        $xml = simplexml_load_string($response->getContent());
        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }

        foreach (['/', '/about', '/rules', '/builds', '/rankings', '/contact', '/privacy', "/builds/{$build->id}"] as $path) {
            $this->assertContains(url($path), $locs);
        }
        $this->assertEmpty(array_filter($locs, fn($l) => str_contains($l, '/player/') || str_contains($l, '/games/')));
    }

    public function test_footer_links_the_trust_pages(): void
    {
        Season::factory()->create();
        $this->get('/')->assertOk()
            ->assertSee('href="'.route('about').'"', false)
            ->assertSee('href="'.route('contact').'"', false)
            ->assertSee('href="'.route('privacy').'"', false);

        $this->get('/contact')->assertSee('cr0ss24881@gmail.com');
        $this->get('/privacy')->assertSee('Third-party vendors, including Google, use cookies');
    }

    public function test_analytics_tag_only_when_configured(): void
    {
        $this->get('/about')->assertDontSee('googletagmanager.com', false);

        config(['services.google_analytics.id' => 'G-TEST123']);
        $this->get('/about')->assertSee('googletagmanager.com/gtag/js?id=G-TEST123', false);
    }
}
