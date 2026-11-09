<?php

namespace Tests\Feature;

use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeasonDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function game(Season $season, PlayerName $name, string $id, string $at): void
    {
        Replay::create([
            'replay_id' => $id, 'player_name' => $name->name, 'winning_team' => 1,
            'replay_file' => 'x.rep', 'team' => '1', 'race' => 'Zerg', 'hash' => "h-$id",
            'apm' => 100, 'eapm' => 80, 'season_id' => $season->id, 'format' => '2v2',
            'points' => 10, 'user_id' => $name->user_id, 'player_name_id' => $name->id,
        ])->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    public function test_running_season_ends_at_the_next_quarterly_season_change(): void
    {
        $this->assertSame('2027-01-01 00:00:00', Season::nextScheduledStart(Carbon::parse('2026-11-09 15:00'))->toDateTimeString());
        $this->assertSame('2027-01-01 00:00:00', Season::nextScheduledStart(Carbon::parse('2026-12-31 23:59'))->toDateTimeString());
        $this->assertSame('2026-04-01 00:00:00', Season::nextScheduledStart(Carbon::parse('2026-01-01 00:00'))->toDateTimeString());
        $this->assertSame('2026-07-01 00:00:00', Season::nextScheduledStart(Carbon::parse('2026-05-31 12:00'))->toDateTimeString());

        $ended = new Season(['is_active' => 0, 'ends_at' => '2025-09-16 03:51:04']);
        $this->assertSame('2025-09-16', $ended->endsAt()->toDateString());
        $this->assertNull((new Season(['is_active' => 0]))->endsAt());
    }

    public function test_backfill_uses_first_and_last_games_and_leaves_the_running_season_open(): void
    {
        $old = Season::create(['is_active' => 0]);
        $current = Season::create(['is_active' => 1]);
        $name = User::factory()->create(['player_name' => 'A'])->primaryPlayerName();
        $this->game($old, $name, 'g1', '2025-08-27 00:56:51');
        $this->game($old, $name, 'g2', '2025-09-16 03:51:04');
        $this->game($current, $name, 'g3', '2026-10-22 01:42:17');
        $this->game($current, $name, 'g4', '2026-11-08 05:39:24');

        $migration = require database_path('migrations/2026_11_09_000001_add_dates_to_seasons_table.php');
        $migration->down();
        $migration->up();

        $old->refresh();
        $current->refresh();
        $this->assertSame('2025-08-27 00:56:51', $old->starts_at->toDateTimeString());
        $this->assertSame('2025-09-16 03:51:04', $old->ends_at->toDateTimeString());
        $this->assertSame('2026-10-22 01:42:17', $current->starts_at->toDateTimeString());
        $this->assertNull($current->ends_at);
    }

    public function test_starting_the_next_season_records_both_dates(): void
    {
        Http::fake();
        Carbon::setTestNow('2027-01-01 00:00:00');
        $old = Season::create(['is_active' => 1, 'starts_at' => '2026-10-22 01:42:17']);

        $this->artisan('season:start-next')->assertSuccessful();

        $this->assertSame('2027-01-01 00:00:00', $old->refresh()->ends_at->toDateTimeString());
        $new = Season::where('is_active', 1)->sole();
        $this->assertSame('2027-01-01 00:00:00', $new->starts_at->toDateTimeString());
        $this->assertSame('2027-04-01 00:00:00', $new->endsAt()->toDateTimeString());
    }

    public function test_pages_show_the_season_dates_and_days_left(): void
    {
        Carbon::setTestNow('2026-11-09 15:00:00');
        Season::create(['is_active' => 0, 'starts_at' => '2025-08-27 00:56:51', 'ends_at' => '2025-09-16 03:51:04']);
        Season::create(['is_active' => 1, 'starts_at' => '2026-10-22 01:42:17']);

        foreach (['/', '/rankings', '/replays'] as $url) {
            $this->get($url)->assertOk()
                ->assertSeeInOrder(['Oct 22, 2026', '–', 'Jan 1, 2027', 'ends in 52 days']);
        }

        $this->get('/rankings?season=1')->assertOk()
            ->assertSeeInOrder(['Aug 27, 2025', '–', 'Sep 16, 2025'])
            ->assertDontSee('ends in');
    }

    public function test_player_page_shows_the_season_dates(): void
    {
        Carbon::setTestNow('2026-12-31 20:00:00');
        $season = Season::create(['is_active' => 1, 'starts_at' => '2026-10-22 01:42:17']);
        $user = User::factory()->create(['player_name' => 'A']);
        $user->ensureStats($season);

        $this->get('/player/A')->assertOk()
            ->assertSeeInOrder(['Oct 22, 2026', 'Jan 1, 2027', 'ends today']);
    }
}
