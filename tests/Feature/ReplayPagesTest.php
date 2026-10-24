<?php

namespace Tests\Feature;

use App\Http\Controllers\ReplayController;
use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Models\User;
use App\Services\ReplayInvalidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "New ELO" used to be worked out in the view by adding up the points of the
 * games on the page, so anyone with games elsewhere showed the wrong number.
 * It is now stored per replay row as elo_after, and lists are paged by game.
 */
class ReplayPagesTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create(['is_active' => 1]);
    }

    private function makeName(string $name): PlayerName
    {
        $playerName = User::factory()->create(['player_name' => $name])->primaryPlayerName();
        $playerName->ensureStats($this->season);

        return $playerName;
    }

    /** One stored game: [winnerA, winnerB] beat [loserA, loserB]. */
    private function game(string $id, array $winners, array $losers, int $points = 10, ?string $at = null): void
    {
        foreach ([[$winners, 1, $points], [$losers, 2, -$points]] as [$team, $teamNo, $pts]) {
            foreach ($team as $playerName) {
                Replay::create([
                    'replay_id' => $id, 'player_name' => $playerName->name, 'winning_team' => $teamNo === 1 ? 1 : 0,
                    'replay_file' => 'x.rep', 'team' => (string) $teamNo, 'race' => 'Zerg', 'hash' => "h-$id",
                    'apm' => 100, 'eapm' => 80, 'season_id' => $this->season->id, 'format' => '2v2',
                    'points' => $pts, 'user_id' => $playerName->user_id, 'player_name_id' => $playerName->id,
                ])->forceFill(['created_at' => $at ?? now(), 'updated_at' => $at ?? now()])->save();
            }
        }
    }

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_10_23_000001_add_elo_after_to_replays.php');
        $migration->down();
        $migration->up();
    }

    public function test_upload_stores_each_players_elo_after_the_game(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $row = fn($n, $team, $won) => ['Name' => $n, 'Team' => $team, 'IsWinner' => $won, 'Race' => 'Zerg',
            'StartTime' => now()->toIso8601String(), 'APM' => 1, 'EAPM' => 1];

        app(ReplayController::class)->store(
            ['Team1' => [$row('A', 1, true), $row('B', 1, true)], 'Team2' => [$row('C', 2, false), $row('D', 2, false)]],
            '/tmp/x.rep', 'hash-1', 'x.rep', ['Header' => ['Frames' => 24 * 300]],
        );

        $this->assertSame(['A' => 1050, 'B' => 1050, 'C' => 950, 'D' => 950],
            Replay::orderBy('player_name')->pluck('elo_after', 'player_name')->all());
    }

    public function test_invalidating_a_game_clears_its_elo(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('g1', [$a, $b], [$c, $d]);
        $this->runBackfill();

        app(ReplayInvalidator::class)->invalidate(Replay::where('replay_id', 'g1')->get());

        $this->assertSame([null], Replay::pluck('elo_after')->unique()->values()->all());
    }

    public function test_backfill_builds_each_names_running_elo_in_upload_order(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('g1', [$a, $b], [$c, $d], 10, '2026-01-01 10:00:00');
        $this->game('g2', [$c, $d], [$a, $b], 30, '2026-01-01 11:00:00');
        $this->game('g3', [$a, $c], [$b, $d], 5, '2026-01-01 12:00:00');
        Replay::where('replay_id', 'g2')->update(['is_invalid' => true, 'points' => 0]);

        $this->runBackfill();

        $elo = fn($game, $name) => Replay::where('replay_id', $game)->where('player_name', $name)->value('elo_after');
        $this->assertSame(1010, $elo('g1', 'A'));
        $this->assertNull($elo('g2', 'A'));          // invalidated game carries no ELO
        $this->assertSame(1015, $elo('g3', 'A'));    // 1000 + 10 + 0 + 5
        $this->assertSame(1005, $elo('g3', 'B'));    // 1000 + 10 + 0 - 5
    }

    public function test_replay_list_is_paged_by_game(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        for ($i = 1; $i <= 25; $i++) {
            $this->game(sprintf('game-%02d', $i), [$a, $b], [$c, $d], 1, now()->subMinutes(100 - $i)->toDateTimeString());
        }

        $page1 = $this->get('/replays?season=1&format=2v2')->assertOk();
        $this->assertSame(20, substr_count($page1->getContent(), 'Download Replay'));
        $page1->assertSee('game-25')->assertDontSee('game-05')->assertSee('page=2');

        $page2 = $this->get('/replays?season=1&format=2v2&page=2')->assertOk();
        $this->assertSame(5, substr_count($page2->getContent(), 'Download Replay'));
        $page2->assertSee('game-05')->assertDontSee('game-25');
    }

    public function test_player_page_shows_other_players_real_elo_not_a_partial_sum(): void
    {
        [$me, $mate, $opp1, $opp2, $other] = array_map(fn($n) => $this->makeName($n), ['Me', 'Mate', 'Opp1', 'Opp2', 'Other']);
        // Opp1 wins big in a game Me is not in...
        $this->game('elsewhere', [$opp1, $other], [$mate, $opp2], 40, '2026-01-01 10:00:00');
        // ...then loses to Me.
        $this->game('mine', [$me, $mate], [$opp1, $opp2], 10, '2026-01-01 11:00:00');
        $this->runBackfill();

        $page = $this->get('/player/Me?season=1&format=2v2')->assertOk();

        // Opp1: 1000 + 40 - 10 = 1030. Summing only Me's games gave 990.
        $page->assertSeeInOrder(['Opp1', 'Zerg', '1030']);
        $page->assertDontSee('>990<', false);
    }

    public function test_invalidated_games_are_hidden_from_public_lists_but_not_from_admins(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('kept-game', [$a, $b], [$c, $d], 10, '2026-01-01 10:00:00');
        $this->game('voided-game', [$a, $b], [$c, $d], 10, '2026-01-01 11:00:00');
        app(ReplayInvalidator::class)->invalidate(Replay::where('replay_id', 'voided-game')->get());

        foreach (['/replays?season=1&format=2v2', '/player/A?season=1&format=2v2', '/'] as $url) {
            $this->get($url)->assertOk()->assertSee('kept-gam')->assertDontSee('voided-g');
        }

        $this->actingAs(User::factory()->admin()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/replays')
            ->assertOk()->assertSee('voided-g');
    }

    public function test_profile_shows_its_own_team_first_with_win_or_loss(): void
    {
        [$me, $mate, $opp1, $opp2] = array_map(fn($n) => $this->makeName($n), ['Me', 'Mate', 'Opp1', 'Opp2']);
        // Me is on team 2 in both games: lost the first, won the second
        $this->game('lost-game', [$opp1, $opp2], [$me, $mate], 10, '2026-01-01 10:00:00');
        $this->game('won-game', [$me, $mate], [$opp1, $opp2], 10, '2026-01-01 11:00:00');
        Replay::where('replay_id', 'won-game')->update(['team' => \DB::raw("CASE team WHEN '1' THEN '2' ELSE '1' END")]);
        $me->ensureStats($this->season);

        $page = $this->get('/player/Me?season=1&format=2v2')->assertOk()->assertDontSee('Team 1')->assertDontSee('Team 2');

        // Header: result, this player first, teammate, vs, opponents, points; then the tables
        $page->assertSeeInOrder(['WIN', 'Me', 'Mate', 'vs', 'Opp1', 'Opp2', '+10', 'won-gam', 'Me', 'Mate', 'Opp1', 'Opp2',
                                 'LOST', 'Me', 'Mate', 'vs', 'Opp1', 'Opp2', '-10', 'lost-ga', 'Me', 'Mate', 'Opp1', 'Opp2']);
        $page->assertDontSee('Opponents');
        // The profile's own name is never a link to the page you're on; others are
        $page->assertDontSee(route('player', ['user' => 'Me']), false)
            ->assertSee(route('player', ['user' => 'Mate']), false)
            ->assertSee(route('player', ['user' => 'Opp1']), false);
        $page->assertSee('All (2)')->assertSee('Wins (1)')->assertSee('Lost (1)');

        // Filters: only the losses (and to whom), only the wins
        $this->get('/player/Me?season=1&format=2v2&result=lost')->assertOk()
            ->assertSee('lost-ga')->assertDontSee('won-gam');
        $this->get('/player/Me?season=1&format=2v2&result=win')->assertOk()
            ->assertSee('won-gam')->assertDontSee('lost-ga');

        // The Replays page keeps neutral Team 1 / Team 2
        $this->get('/replays?season=1&format=2v2')->assertSee('Team 1')->assertSee('Team 2');
    }
}
