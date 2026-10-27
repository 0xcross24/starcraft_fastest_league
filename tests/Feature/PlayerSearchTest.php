<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['[SFL]Cross-', 'Wk`Cross-', 'Crossbow', 'flowcc', 'Flo_wer', 'AnthZ'] as $name) {
            User::factory()->create(['player_name' => $name]);
        }
    }

    private function suggest(string $q): array
    {
        return array_column($this->getJson(route('players.search', ['q' => $q]))->assertOk()->json(), 'name');
    }

    public function test_suggestions_match_anywhere_ignoring_case_with_prefix_matches_first(): void
    {
        $this->assertSame(['Crossbow', '[SFL]Cross-', 'Wk`Cross-'], $this->suggest('cross'));
        $this->assertSame(['Flo_wer', 'flowcc'], $this->suggest('FLO'));
    }

    public function test_suggestions_link_to_the_player_page(): void
    {
        $response = $this->getJson(route('players.search', ['q' => 'wk`']))->json();

        $this->assertSame([['name' => 'Wk`Cross-', 'url' => route('player', 'Wk`Cross-')]], $response);
        $this->get($response[0]['url'])->assertOk()->assertSee('Profile: Wk`Cross-', false);
    }

    public function test_like_wildcards_are_matched_literally(): void
    {
        $this->assertSame(['Flo_wer'], $this->suggest('_'));
        $this->assertSame([], $this->suggest('%'));
        $this->assertSame([], $this->suggest('!'));
    }

    public function test_an_exact_name_goes_straight_to_the_player(): void
    {
        $this->get(route('players.search', ['q' => ' anthz ']))->assertRedirect(route('player', 'AnthZ'));
    }

    public function test_otherwise_the_results_page_lists_the_matches(): void
    {
        $this->get(route('players.search', ['q' => 'cross']))
            ->assertOk()
            ->assertSeeInOrder(['Crossbow', '[SFL]Cross-', 'Wk`Cross-'])
            ->assertDontSee('AnthZ');

        $this->get(route('players.search', ['q' => 'nobody']))->assertOk()->assertSee('No players found.');
        $this->get(route('players.search'))->assertOk()->assertSee('Search players');
    }

    public function test_the_search_box_is_in_the_navigation(): void
    {
        $this->get('/rankings')->assertOk()->assertSee('action="'.route('players.search').'"', false);
    }
}
