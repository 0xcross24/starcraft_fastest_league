<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /player/{name} looked the user up with first() and then read a property off
 * the result without checking it. An unknown name produced a 500 rather than a
 * 404, and did so on production from 2026-04-13 onwards - 178 logged
 * occurrences, and the only meaningful contents of laravel.log.
 *
 * The equivalent API endpoint already guarded this and returned 404, so the
 * fix brings the web route in line with it.
 */
class PlayerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_player_name_returns_404_not_500(): void
    {
        $this->get('/player/ThisPlayerDoesNotExist')->assertNotFound();
    }

    public function test_a_known_player_still_renders(): void
    {
        $user = User::factory()->create(['player_name' => 'TestPlayer']);

        $this->get('/player/'.$user->player_name)->assertOk();
    }

    public function test_numeric_rank_counts_players_with_more_elo(): void
    {
        $season = Season::factory()->create();

        $third = User::factory()->create(['player_name' => 'Third']);
        foreach ([6000, 5000, 4000] as $i => $elo) {
            Stats::create([
                'user_id' => $i === 2 ? $third->id : User::factory()->create()->id,
                'season_id' => $season->id,
                'format' => '2v2',
                'elo' => $elo,
            ]);
        }

        $this->get('/player/Third?season='.$season->id.'&format=2v2')
            ->assertOk()
            ->assertViewHas('numericRank', 3);
    }

    /**
     * Equal elo must still produce distinct consecutive positions - a shared
     * rank is not wanted here. The tie is settled by registration order, so
     * the result is stable rather than depending on how MySQL happens to
     * return rows.
     */
    public function test_players_on_equal_elo_take_consecutive_ranks_oldest_first(): void
    {
        $season = Season::factory()->create();

        $first = User::factory()->create(['player_name' => 'Earliest']);
        $second = User::factory()->create(['player_name' => 'Middle']);
        $third = User::factory()->create(['player_name' => 'Latest']);

        foreach ([$first, $second, $third] as $user) {
            Stats::create([
                'user_id' => $user->id,
                'season_id' => $season->id,
                'format' => '2v2',
                'elo' => 5000,
            ]);
        }

        $query = '?season='.$season->id.'&format=2v2';

        $this->get('/player/Earliest'.$query)->assertOk()->assertViewHas('numericRank', 1);
        $this->get('/player/Middle'.$query)->assertOk()->assertViewHas('numericRank', 2);
        $this->get('/player/Latest'.$query)->assertOk()->assertViewHas('numericRank', 3);
    }
}
