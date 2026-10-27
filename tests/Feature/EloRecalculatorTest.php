<?php

namespace Tests\Feature;

use App\Http\Controllers\ReplayController;
use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use App\Services\ReplayInvalidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * elo:recalculate replays a season's stored games and must land exactly where
 * uploading them one by one under the current table would have.
 */
class EloRecalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create(['is_active' => 1]);
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $name) {
            User::factory()->create(['player_name' => $name])->primaryPlayerName()->ensureStats($this->season);
        }
    }

    private function game(array $winners, array $losers, int $n): string
    {
        $row = fn($name, $team, $won) => ['Name' => $name, 'Team' => $team, 'IsWinner' => $won, 'Race' => 'Zerg',
            'StartTime' => now()->toIso8601String(), 'APM' => 1, 'EAPM' => 1];
        $teams = ['Team1' => [], 'Team2' => []];
        foreach ($winners as $name) $teams['Team1'][] = $row($name, 1, true);
        foreach ($losers as $name) $teams['Team2'][] = $row($name, 2, false);

        app(ReplayController::class)->store($teams, "/tmp/$n.rep", "hash-$n", "$n.rep", ['Header' => ['Frames' => 24 * 300]]);

        return Replay::latest('id')->value('replay_id');
    }

    private function snapshot(): array
    {
        return [
            Stats::orderBy('id')->get(['elo', 'wins', 'losses'])->toArray(),
            Replay::orderBy('id')->get(['points', 'elo_after'])->toArray(),
        ];
    }

    public function test_recalculating_matches_rating_each_game_as_it_was_uploaded(): void
    {
        $this->game(['A', 'B'], ['C', 'D'], 1);
        $this->game(['A', 'C'], ['B', 'D'], 2);
        $this->game(['D', 'B'], ['A', 'C'], 3);
        $this->game(['A', 'B', 'C'], ['D', 'E', 'F'], 4);
        $this->game(['C', 'D'], ['A', 'B'], 5);
        $expected = $this->snapshot();
        $this->assertNotEquals(1000, Stats::where('format', '2v2')->max('elo'));

        // Stand-in for ratings left by the old Elo formula
        Stats::query()->update(['elo' => 1234, 'wins' => 9, 'losses' => 9]);
        Replay::query()->update(['points' => 17, 'elo_after' => 999]);

        $this->artisan('elo:recalculate')->assertSuccessful();

        $this->assertSame($expected, $this->snapshot());
    }

    public function test_invalidated_games_are_skipped(): void
    {
        $bad = $this->game(['A', 'B'], ['C', 'D'], 1);
        $this->game(['C', 'D'], ['A', 'B'], 2);
        app(ReplayInvalidator::class)->invalidate(Replay::where('replay_id', $bad)->get());

        $this->artisan('elo:recalculate')->assertSuccessful();

        // Only game 2 counts, rated from 1000: C and D +100, A and B -50
        $elo = fn($n) => PlayerName::where('name', $n)->first()->stats()->where('format', '2v2')->first();
        $this->assertSame([1100, 1, 0], [(int) $elo('C')->elo, $elo('C')->wins, $elo('C')->losses]);
        $this->assertSame([950, 0, 1], [(int) $elo('A')->elo, $elo('A')->wins, $elo('A')->losses]);
        $this->assertSame(4, Replay::where('replay_id', $bad)->where('points', 0)->whereNull('elo_after')->count());
    }

    public function test_dry_run_saves_nothing_and_other_seasons_are_left_alone(): void
    {
        $this->game(['A', 'B'], ['C', 'D'], 1);
        Stats::query()->update(['elo' => 1234]);

        $this->artisan('elo:recalculate', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(1234, (int) Stats::max('elo'));

        $other = Season::create(['is_active' => 0]);
        PlayerName::where('name', 'A')->first()->ensureStats($other);
        Stats::where('season_id', $other->id)->update(['elo' => 4321]);

        $this->artisan('elo:recalculate', ['season' => [$this->season->id]])->assertSuccessful();
        $this->assertSame(1100, (int) PlayerName::where('name', 'A')->first()->stats()
            ->where('season_id', $this->season->id)->where('format', '2v2')->value('elo'));
        $this->assertSame(4321, (int) Stats::where('season_id', $other->id)->value('elo'));
    }
}
