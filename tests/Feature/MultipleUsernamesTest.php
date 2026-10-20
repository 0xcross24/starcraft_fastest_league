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
 * One login can own up to three in-game names. A replay counts for whichever
 * name appears in it, and each name has its own stats, elo and rank.
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

    public function test_the_login_name_and_names_with_replays_cannot_be_removed(): void
    {
        $user = User::factory()->create();
        $this->addName($user, 'Smurf');
        $smurf = PlayerName::where('name', 'Smurf')->first();
        Replay::create([
            'replay_id' => 'r1', 'player_name' => 'Smurf', 'winning_team' => 1,
            'replay_file' => 'x', 'team' => '1', 'race' => 'Zerg', 'hash' => 'h',
            'apm' => 1, 'eapm' => 1, 'season_id' => $this->season->id, 'format' => '2v2',
            'user_id' => $user->id, 'player_name_id' => $smurf->id,
        ]);

        $this->actingAs($user)->delete(route('player-names.destroy', $user->primaryPlayerName()));
        $this->actingAs($user)->delete(route('player-names.destroy', $smurf));

        $this->assertSame(2, $user->playerNames()->count());
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

    public function test_a_replay_rates_the_name_that_played_not_the_login_name(): void
    {
        $user = User::factory()->create(['player_name' => 'Main']);
        $user->primaryPlayerName()->ensureStats($this->season);
        $this->addName($user, 'Smurf');
        foreach (['A', 'B', 'C'] as $name) {
            User::factory()->create(['player_name' => $name])->primaryPlayerName()->ensureStats($this->season);
        }

        $this->storeReplay(['smurf', 'A'], ['B', 'C']);

        $this->assertSame(1050, $this->elo('Smurf'));
        $this->assertSame(1000, $this->elo('Main'));

        $replay = Replay::where('player_name', 'smurf')->firstOrFail();
        $this->assertSame($user->id, $replay->user_id);
        $this->assertSame(PlayerName::where('name', 'Smurf')->value('id'), $replay->player_name_id);
        $this->assertSame(50, $replay->points);
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

    public function test_player_page_shows_the_selected_names_own_stats_and_rank(): void
    {
        $user = User::factory()->create(['player_name' => 'Main']);
        $this->addName($user, 'Smurf');
        auth()->logout();
        Stats::where('player_name_id', PlayerName::where('name', 'Smurf')->value('id'))
            ->where('format', '2v2')->update(['elo' => 1500]);
        Stats::create(['user_id' => $user->id, 'season_id' => $this->season->id, 'format' => '2v2', 'elo' => 1200]);

        $query = '?season='.$this->season->id.'&format=2v2';

        $this->get('/player/Smurf'.$query)->assertOk()
            ->assertViewHas('numericRank', 1)
            ->assertViewHas('stats', fn($s) => $s->elo === 1500)
            ->assertSee('Main'); // the dropdown lists the account's other names
        $this->get('/player/Main'.$query)->assertOk()
            ->assertViewHas('numericRank', 2)
            ->assertViewHas('stats', fn($s) => $s->elo === 1200);
    }

    public function test_profile_dropdown_selects_which_names_stats_to_show(): void
    {
        $user = User::factory()->create(['player_name' => 'Main']);
        $user->primaryPlayerName()->ensureStats($this->season);
        $this->addName($user, 'Smurf');
        $smurf = PlayerName::where('name', 'Smurf')->first();
        $smurf->stats()->where('format', '2v2')->update(['elo' => 1500]);

        $this->actingAs($user)->get('/profile?name='.$smurf->id.'&format=2v2')->assertOk()
            ->assertViewHas('playerName', fn($n) => $n->is($smurf))
            ->assertViewHas('stats', fn($s) => $s->elo === 1500)
            ->assertViewHas('rank', 1);

        $this->actingAs($user)->get('/profile?format=2v2')->assertOk()
            ->assertViewHas('stats', fn($s) => $s->elo === 1000)
            ->assertViewHas('rank', 2);
    }
}
