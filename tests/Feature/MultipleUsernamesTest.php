<?php

namespace Tests\Feature;

use App\Http\Controllers\ReplayController;
use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One login can own up to three in-game names. A replay under any of them
 * counts toward the account's one set of stats, elo and rank.
 */
class MultipleUsernamesTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create(['is_active' => 1]);
    }

    private function addName(User $user, string $name)
    {
        return $this->actingAs($user)->post(route('player-names.store'), ['name' => $name]);
    }

    public function test_a_new_user_owns_their_registered_name(): void
    {
        $user = User::factory()->create(['player_name' => 'Main']);

        $this->assertSame(['Main'], $user->playerNames->pluck('name')->all());
    }

    public function test_a_user_can_add_a_name_and_it_gets_stats_for_the_active_season(): void
    {
        $user = User::factory()->create(['player_name' => 'Main']);

        $this->addName($user, 'Smurf')->assertRedirect();

        $smurf = PlayerName::where('name', 'Smurf')->firstOrFail();
        $this->assertSame($user->id, $smurf->user_id);
        $this->assertSame(2, $smurf->stats()->where('season_id', $this->season->id)->count());
    }

    public function test_a_user_cannot_have_more_than_three_names(): void
    {
        $user = User::factory()->create();
        $this->addName($user, 'Two');
        $this->addName($user, 'Three');

        $this->addName($user, 'Four')->assertSessionHasErrorsIn('playerName', 'name');

        $this->assertSame(3, $user->playerNames()->count());
    }

    public function test_a_name_owned_by_anyone_cannot_be_added_again_in_any_case(): void
    {
        User::factory()->create(['player_name' => 'Taken']);
        $user = User::factory()->create();

        $this->addName($user, 'tAKEN')->assertSessionHasErrorsIn('playerName', 'name');
    }

    public function test_registration_rejects_a_name_someone_added_to_their_account(): void
    {
        $this->addName(User::factory()->create(), 'Smurf');
        auth()->logout();

        $this->post('/register', [
            'player_name' => 'smurf',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('player_name');
    }

    public function test_an_unused_extra_name_can_be_removed(): void
    {
        $user = User::factory()->create();
        $this->addName($user, 'Smurf');
        $smurf = PlayerName::where('name', 'Smurf')->first();

        $this->actingAs($user)->delete(route('player-names.destroy', $smurf))->assertRedirect();

        $this->assertNull(PlayerName::find($smurf->id));
    }

    public function test_the_main_name_cannot_be_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete(route('player-names.destroy', $user->primaryPlayerName()))->assertSessionHas('error');

        $this->assertSame(1, $user->playerNames()->count());
    }

    public function test_a_user_cannot_remove_someone_elses_name(): void
    {
        $owner = User::factory()->create();
        $this->addName($owner, 'Smurf');

        $this->actingAs(User::factory()->create())
            ->delete(route('player-names.destroy', PlayerName::where('name', 'Smurf')->first()))
            ->assertForbidden();
    }

    /**
     * Upload goes through store() directly: building a real .rep is not
     * practical, and everything past parsing works off this array.
     */
    private function storeReplay(array $team1, array $team2)
    {
        $player = fn($name, $team, $won) => [
            'Name' => $name, 'Team' => $team, 'IsWinner' => $won, 'Race' => 'Zerg',
            'StartTime' => now()->toIso8601String(), 'APM' => 100, 'EAPM' => 80,
        ];

        return app(ReplayController::class)->store(
            [
                'Team1' => array_map(fn($n) => $player($n, 1, true), $team1),
                'Team2' => array_map(fn($n) => $player($n, 2, false), $team2),
            ],
            '/tmp/test.rep',
            uniqid(),
            'test.rep',
            ['Header' => ['Frames' => 24 * 300]],
        );
    }

    private function elo(string $name): int
    {
        return PlayerName::where('name', $name)->first()
            ->stats()->where('season_id', $this->season->id)->where('format', '2v2')->value('elo');
    }

    /** Main (account) plus three opponents, all with stats. */
    private function players(): User
    {
        $user = User::factory()->create(['player_name' => 'Main']);
        $user->ensureStats($this->season);
        $this->addName($user, 'Smurf');
        foreach (['A', 'B', 'C'] as $name) {
            User::factory()->create(['player_name' => $name])->ensureStats($this->season);
        }
        auth()->logout();

        return $user;
    }

    public function test_a_replay_under_any_name_rates_the_account(): void
    {
        $user = $this->players();

        $this->storeReplay(['smurf', 'A'], ['B', 'C']);

        $this->assertSame(1100, $this->elo('Smurf'));
        $this->assertSame(1100, $this->elo('Main'));
        $this->assertSame(2, Stats::where('user_id', $user->id)->count()); // one row per format

        $replay = Replay::where('player_name', 'smurf')->firstOrFail();
        $this->assertSame($user->id, $replay->user_id);
        $this->assertSame(PlayerName::where('name', 'Smurf')->value('id'), $replay->player_name_id);
        $this->assertSame(100, $replay->points);
        $this->assertSame(1100, $replay->elo_after);
    }

    public function test_games_under_different_names_add_up_on_one_record(): void
    {
        $user = $this->players();

        $this->storeReplay(['Main', 'A'], ['B', 'C']);
        $this->storeReplay(['Smurf', 'B'], ['A', 'C']);

        $stats = Stats::where('user_id', $user->id)->where('format', '2v2')->sole();
        $this->assertSame([2, 0], [$stats->wins, $stats->losses]);
        $this->assertGreaterThan(1100, $stats->elo);
    }

    public function test_removing_a_name_with_games_keeps_points_and_games_and_locks_the_name(): void
    {
        $user = $this->players();
        $this->storeReplay(['Smurf', 'A'], ['B', 'C']);
        $smurf = PlayerName::where('name', 'Smurf')->first();

        $this->actingAs($user)->delete(route('player-names.destroy', $smurf))->assertRedirect();

        $this->assertSame(['Main'], $user->playerNames()->pluck('name')->all());
        $this->assertTrue($smurf->fresh()->isRetired());
        $this->assertSame(1100, $this->elo('Main'));
        $stats = Stats::where('user_id', $user->id)->where('format', '2v2')->sole();
        $this->assertSame([1, 0], [$stats->wins, $stats->losses]);
        $this->assertSame($user->id, Replay::where('player_name', 'Smurf')->value('user_id'));

        // Nobody else can take it, and new games under it are not rated
        $this->addName(User::factory()->create(), 'smurf')->assertSessionHasErrorsIn('playerName', 'name');
        auth()->logout();
        $this->post('/register', ['player_name' => 'Smurf', 'email' => 'n@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('player_name');
        $this->storeReplay(['Smurf', 'A'], ['B', 'C']);
        $this->assertStringStartsWith('Missing player(s): smurf', session('error'));

        // Its old games still lead to this account
        $this->get('/player/Smurf')->assertRedirect(route('player', ['user' => 'Main']));
    }

    public function test_an_account_can_add_back_a_name_it_removed(): void
    {
        $user = $this->players();
        $this->storeReplay(['Smurf', 'A'], ['B', 'C']);
        $smurf = PlayerName::where('name', 'Smurf')->first();
        $this->actingAs($user)->delete(route('player-names.destroy', $smurf));

        $this->addName($user, 'Smurf')->assertSessionHasNoErrors();

        $this->assertSame(['Main', 'Smurf'], $user->playerNames()->pluck('name')->all());
        $this->assertSame($smurf->id, PlayerName::where('name', 'Smurf')->value('id'));
    }

    public function test_one_account_cannot_appear_twice_in_a_replay(): void
    {
        $user = User::factory()->create(['player_name' => 'Main']);
        $this->addName($user, 'Smurf');
        User::factory()->create(['player_name' => 'B']);
        User::factory()->create(['player_name' => 'C']);

        $this->storeReplay(['Main', 'B'], ['Smurf', 'C']);

        $this->assertStringStartsWith('These names belong to the same account', session('error'));
        $this->assertSame(0, Replay::count());
    }

    public function test_any_name_opens_the_account_page_with_all_its_games(): void
    {
        $user = $this->players();
        $this->storeReplay(['Main', 'A'], ['B', 'C']);
        $this->storeReplay(['Smurf', 'B'], ['A', 'C']);
        $query = ['season' => $this->season->id, 'format' => '2v2'];

        $this->get(route('player', ['user' => 'smurf'] + $query))->assertNotFound(); // names are exact in URLs
        $this->get(route('player', ['user' => 'Smurf'] + $query))->assertRedirect(route('player', ['user' => 'Main'] + $query));

        $this->get(route('player', ['user' => 'Main'] + $query))->assertOk()
            ->assertViewHas('stats', fn($s) => $s->wins === 2)
            ->assertViewHas('numericRank', 1)
            ->assertViewHas('resultCounts', fn($c) => $c->sum() === 2)
            ->assertSee('Also plays as Smurf');
    }

    public function test_profile_shows_the_accounts_stats(): void
    {
        $user = $this->players();
        $this->storeReplay(['Smurf', 'A'], ['B', 'C']);

        $this->actingAs($user)->get('/profile?format=2v2')->assertOk()
            ->assertViewHas('stats', fn($s) => $s->elo === 1100 && $s->wins === 1)
            ->assertViewHas('rank', 1);
    }
}
