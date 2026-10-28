<?php

namespace Tests\Feature;

use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameFilterTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    /** @var array<string, PlayerName> */
    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create(['is_active' => 1]);
        foreach (['Alice', 'Bob', 'Carl', 'Dan', 'Eve'] as $n) {
            $this->p[$n] = User::factory()->create(['player_name' => $n])->primaryPlayerName();
        }
        // id => [date, [name => race] (first two won)]
        $this->game('g1', '2026-10-01 12:00:00', ['Alice' => 'Zerg', 'Bob' => 'Terran', 'Carl' => 'Protoss', 'Dan' => 'Protoss']);
        $this->game('g2', '2026-10-05 12:00:00', ['Alice' => 'Protoss', 'Carl' => 'Protoss', 'Bob' => 'Terran', 'Dan' => 'Protoss']);
        $this->game('g3', '2026-10-09 12:00:00', ['Bob' => 'Protoss', 'Eve' => 'Protoss', 'Carl' => 'Protoss', 'Dan' => 'Protoss']);
        $this->p['Alice']->ensureStats($this->season);
    }

    private function game(string $id, string $start, array $players): void
    {
        $k = 0;
        foreach ($players as $name => $race) {
            $won = $k++ < 2;
            Replay::create([
                'replay_id' => $id, 'player_name' => $name, 'winning_team' => $won ? 1 : 0, 'start_time' => $start,
                'replay_file' => "{$id}.rep", 'team' => $won ? '1' : '2', 'race' => $race, 'hash' => "h-{$id}", 'apm' => 1, 'eapm' => 1,
                'season_id' => $this->season->id, 'format' => '2v2', 'points' => 0,
                'user_id' => $this->p[$name]->user_id, 'player_name_id' => $this->p[$name]->id,
            ]);
        }
    }

    /** Replay ids listed on a page, in order. */
    private function listed(string $url): array
    {
        preg_match_all('/class="js-zip-pick [^"]*" value="([^"]+)"/', $this->get($url)->assertOk()->getContent(), $m);
        sort($m[1]);

        return $m[1];
    }

    public function test_replay_list_filters_by_date_player_and_race(): void
    {
        $base = '/replays?season=1&format=2v2';
        $this->assertSame(['g1', 'g2', 'g3'], $this->listed($base));
        $this->assertSame(['g1', 'g2'], $this->listed("{$base}&from=2026-10-01&to=2026-10-05"));
        $this->assertSame(['g1', 'g2'], $this->listed("{$base}&from=2026-10-05&to=2026-10-01"), 'reversed dates are swapped');
        $this->assertSame(['g3'], $this->listed("{$base}&from=2026-10-06"));
        $this->assertSame(['g1', 'g2'], $this->listed("{$base}&player=Alice"));
        $this->assertSame(['g1'], $this->listed("{$base}&race=Zerg"), 'any player of that race');
        $this->assertSame(['g2'], $this->listed("{$base}&player=Alice&race=Protoss"), 'that player as that race');
        $this->assertSame([], $this->listed("{$base}&player=Nobody"));
        // Junk values are ignored, not errors
        $this->assertSame(['g1', 'g2', 'g3'], $this->listed("{$base}&from=yesterday&race=Robot"));
    }

    public function test_summary_line_download_link_and_tabs_follow_the_filters(): void
    {
        $page = $this->get('/replays?season=1&format=2v2&from=2026-10-01&to=2026-10-05&player=Alice&race=Protoss');

        $page->assertSeeInOrder(['1 game', 'Season 1', '2V2', 'Oct 1, 2026 – Oct 5, 2026', 'Player: Alice as Protoss', 'Clear filters'])
            ->assertSee('href="?season=1&amp;format=2v2"', false)
            ->assertSee('Download filtered (1) .zip')
            ->assertSee(e(route('replays.download-zip', ['season' => 1, 'format' => '2v2', 'from' => '2026-10-01', 'to' => '2026-10-05', 'player' => 'Alice', 'race' => 'Protoss'])), false)
            // Switching format keeps the filters
            ->assertSee('?' . e(http_build_query(['season' => 1, 'format' => '3v3', 'from' => '2026-10-01', 'to' => '2026-10-05', 'player' => 'Alice', 'race' => 'Protoss'])), false)
            // The form is open and filled in
            ->assertSee('value="2026-10-01"', false)->assertSee('<option value="Protoss" selected', false);

        $this->get('/replays?season=1&format=2v2&race=Zerg')->assertSee('Race: Zerg');
        $this->get('/replays?season=1&format=2v2&player=Nobody')->assertSee('(not found)', false)
            ->assertSee('with these filters');

        $plain = $this->get('/replays?season=1&format=2v2');
        $plain->assertSee('3 games')->assertDontSee('Clear filters')->assertSee('Download all (3) .zip');
    }

    public function test_player_page_filters_by_date_and_own_race(): void
    {
        $base = '/player/Alice?season=1&format=2v2';
        $this->assertSame(['g1', 'g2'], $this->listed($base));
        $this->assertSame(['g1'], $this->listed("{$base}&race=Zerg"));
        $this->assertSame(['g2'], $this->listed("{$base}&from=2026-10-02"));

        $page = $this->get("{$base}&race=Zerg");
        $page->assertSee('Alice')->assertSee('All (1)')->assertSee('Wins (1)')->assertSee('Lost (0)')
            ->assertSee('Download 2v2 (1) .zip')
            ->assertSee(e(route('replays.download-zip', ['season' => 1, 'format' => '2v2', 'player' => 'Alice', 'race' => 'Zerg'])), false)
            ->assertSee('Download all formats (1) .zip')
            // Result tabs keep the filter
            ->assertSee('?' . e(http_build_query(['season' => 1, 'format' => '2v2', 'result' => 'win', 'race' => 'Zerg'])), false)
            ->assertDontSee('name="player"', false);
    }
}
