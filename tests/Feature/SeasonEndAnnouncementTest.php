<?php

namespace Tests\Feature;

use App\Models\PlayerName;
use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeasonEndAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.discord_webhook_url' => 'https://discord.test/api/webhooks/1/x']);
        Http::fake();
        $this->season = Season::create(['is_active' => 1]);
    }

    private function player(string $name, int $elo, int $wins, int $losses, string $format = '2v2'): void
    {
        $playerName = User::factory()->create(['player_name' => $name])->primaryPlayerName();
        $playerName->ensureStats($this->season);
        $playerName->stats()->where('format', $format)->update(compact('elo', 'wins', 'losses'));
    }

    private function standingsLines(array $payload, string $format): array
    {
        $field = collect($payload['embeds'][0]['fields'])->firstWhere('name', "🏆 {$format} Top 6");

        return $field ? explode("\n", $field['value']) : [];
    }

    public function test_one_message_lists_the_top_six_who_played_then_the_new_season(): void
    {
        foreach ([['A', 1400, 9, 1], ['B', 1300, 7, 3], ['C', 1200, 5, 5], ['D', 1150, 4, 4],
                  ['E', 1100, 3, 3], ['F', 1050, 2, 2], ['G', 1020, 1, 1]] as [$n, $elo, $w, $l]) {
            $this->player($n, $elo, $w, $l);
        }
        $this->player('NoGames', 1000, 0, 0); // still on 1000, never played: left out

        $this->artisan('season:start-next')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $payload = $request->data();
            $lines = $this->standingsLines($payload, '2v2');

            return count($payload['embeds']) === 2
                && $payload['embeds'][0]['title'] === 'Season 1 Final Standings'
                && str_contains($payload['embeds'][1]['description'], 'Season 2 has begun')
                && count($lines) === 6
                && str_starts_with($lines[0], '🥇 **A** — 1400 ELO')
                && str_contains($lines[0], '9-1')
                && str_starts_with($lines[3], '`#4` **D**')
                && ! str_contains(implode("\n", $lines), '**G**')
                && ! str_contains(implode("\n", $lines), 'NoGames')
                // Nobody played 3v3, so it has no section
                && $this->standingsLines($payload, '3v3') === [];
        });

        $this->assertFalse((bool) $this->season->fresh()->is_active);
        $this->assertTrue((bool) Season::find(2)->is_active);
    }

    public function test_names_are_escaped_so_discord_does_not_format_them(): void
    {
        $this->player('Wk`Cross-', 1200, 3, 1);
        $this->player('Clan_[SFL]', 1100, 2, 2);

        $this->artisan('season:start-next')->assertSuccessful();

        Http::assertSent(function (Request $request) {
            $lines = $this->standingsLines($request->data(), '2v2');

            return str_contains($lines[0], '**Wk\`Cross\-**')
                && str_contains($lines[1], '**Clan\_\[SFL\]**');
        });
    }

    public function test_a_season_with_no_games_only_announces_the_new_season(): void
    {
        $this->player('Idle', 1000, 0, 0);

        $this->artisan('season:start-next')->assertSuccessful();

        Http::assertSent(fn(Request $request) => count($request->data()['embeds']) === 1
            && str_contains($request->data()['embeds'][0]['title'], 'New Season'));
    }

    public function test_preview_prints_the_message_without_changing_or_sending_anything(): void
    {
        $this->player('A', 1400, 9, 1);

        $this->artisan('season:start-next', ['--preview' => true])
            ->expectsOutputToContain('Season 1 Final Standings')
            ->expectsOutputToContain('Preview only')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(1, Season::count());
        $this->assertTrue((bool) $this->season->fresh()->is_active);
    }
}
