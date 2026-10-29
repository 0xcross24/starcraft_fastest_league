<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accounts that haven't played a game in a season and format stay off its
 * rankings, and don't take up rank positions, until they do.
 */
class RankingsHideUnplayedTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create(['is_active' => 1]);
    }

    private function account(string $name, int $elo, int $wins, int $losses): User
    {
        $user = User::factory()->create(['player_name' => $name]);
        $user->ensureStats($this->season);
        $user->stats()->where('format', '2v2')->update(compact('elo', 'wins', 'losses'));

        return $user;
    }

    public function test_rankings_and_homepage_only_list_accounts_that_played(): void
    {
        $this->account('NewGuy', 1000, 0, 0);
        $this->account('Loser', 1000, 1, 2); // same elo as NewGuy, registered later
        $this->account('Winner', 1200, 2, 0);

        $this->get('/rankings?format=2v2')->assertOk()
            ->assertSeeInOrder(['Winner', 'Loser'])
            ->assertDontSee('NewGuy');
        $this->get('/')->assertOk()->assertSee('Loser')->assertDontSee('NewGuy');
        $this->get('/rankings?format=3v3')->assertOk()->assertSee('No games played yet.');
    }

    public function test_an_account_is_unranked_until_its_first_game_and_others_skip_it(): void
    {
        $new = $this->account('NewGuy', 1000, 0, 0);
        $loser = $this->account('Loser', 1000, 1, 2);
        $query = '?season=' . $this->season->id . '&format=2v2';

        $this->get('/player/NewGuy' . $query)->assertOk()
            ->assertViewHas('numericRank', null)->assertSee('Unranked');
        $this->get('/player/Loser' . $query)->assertOk()->assertViewHas('numericRank', 1);
        $this->actingAs($new)->get('/profile?format=2v2')->assertOk()->assertViewHas('rank', null)->assertSee('Unranked');
        $this->get('/api/users?username=Loser&format=2v2')->assertOk()->assertSee('Rank: 1');

        $new->stats()->where('format', '2v2')->update(['wins' => 1, 'elo' => 1100]);
        $this->get('/player/NewGuy' . $query)->assertViewHas('numericRank', 1);
        $this->assertSame(2, Stats::where('user_id', $loser->id)->where('format', '2v2')->first()->position());
    }
}
