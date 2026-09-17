<?php

namespace Tests\Feature;

use App\Models\BuildOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildOrderBranchingTest extends TestCase
{
    use RefreshDatabase;

    private function build(array $overrides = []): BuildOrder
    {
        return BuildOrder::create(array_merge([
            'title' => 'An opener',
            'race' => 'Terran',
            'matchup' => ['TvP'],
            'steps' => '9 Supply Depot',
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_only_openers_are_listed_at_the_top_level(): void
    {
        $opener = $this->build(['title' => '3 Rax 15 Gas Academy']);
        $this->build(['title' => '5 Rax Transition', 'parent_id' => $opener->id]);

        $response = $this->get(route('builds.index'))->assertOk();

        $this->assertSame(
            ['3 Rax 15 Gas Academy'],
            $response->viewData('builds')->pluck('title')->all()
        );
    }

    public function test_transitions_are_rendered_under_their_opener_collapsed(): void
    {
        $opener = $this->build(['title' => '3 Rax 15 Gas Academy']);
        $this->build(['title' => '5 Rax Transition', 'parent_id' => $opener->id]);

        // The nested rows ship with the page and are revealed by the caret,
        // so the title is present but the row starts hidden.
        $this->get(route('builds.index'))
            ->assertOk()
            ->assertSee('5 Rax Transition')
            ->assertSee('style="display: none"', false);
    }

    public function test_an_opener_without_transitions_has_no_disclosure_control(): void
    {
        $this->build(['title' => '2 Rax Bunker Rush']);

        $this->get(route('builds.index'))
            ->assertOk()
            ->assertSee('2 Rax Bunker Rush')
            ->assertDontSee('Show transitions from 2 Rax Bunker Rush');
    }

    public function test_a_transition_is_still_reachable_directly(): void
    {
        $opener = $this->build(['title' => '3 Rax 15 Gas Academy']);
        $transition = $this->build(['title' => '5 Rax Transition', 'parent_id' => $opener->id]);

        $this->get(route('builds.show', $transition->id))
            ->assertOk()
            ->assertSee('5 Rax Transition');
    }

    public function test_an_opener_lists_what_continues_from_it(): void
    {
        $opener = $this->build(['title' => '3 Rax 15 Gas Academy']);
        $this->build(['title' => 'Late Game Mech', 'parent_id' => $opener->id, 'position' => 2]);
        $this->build(['title' => '5 Rax Transition', 'parent_id' => $opener->id, 'position' => 1]);

        $response = $this->get(route('builds.show', $opener->id))->assertOk();

        $response->assertSee('5 Rax Transition');
        $response->assertSee('Late Game Mech');
        $response->assertSeeInOrder(['5 Rax Transition', 'Late Game Mech']);
    }

    public function test_transitions_are_ordered_by_position(): void
    {
        $opener = $this->build();
        $this->build(['title' => 'Third', 'parent_id' => $opener->id, 'position' => 3]);
        $this->build(['title' => 'First', 'parent_id' => $opener->id, 'position' => 1]);
        $this->build(['title' => 'Second', 'parent_id' => $opener->id, 'position' => 2]);

        $this->assertSame(
            ['First', 'Second', 'Third'],
            $opener->transitions()->pluck('title')->all()
        );
    }

    public function test_ancestors_run_from_nearest_parent_upwards(): void
    {
        $opener = $this->build(['title' => 'Opener']);
        $mid = $this->build(['title' => 'Mid', 'parent_id' => $opener->id]);
        $late = $this->build(['title' => 'Late', 'parent_id' => $mid->id]);

        $this->assertSame(['Mid', 'Opener'], $late->ancestors()->pluck('title')->all());
    }

    public function test_an_opener_still_gets_a_breadcrumb_back_to_the_listing(): void
    {
        $opener = $this->build(['title' => '3 Rax 15 Gas Academy']);

        $this->get(route('builds.show', $opener->id))
            ->assertOk()
            ->assertSee('Breadcrumb')
            ->assertSeeInOrder(['Build Orders', '3 Rax 15 Gas Academy']);
    }

    public function test_a_deep_transition_shows_its_whole_trail(): void
    {
        $opener = $this->build(['title' => '1 Gate Core Expand']);
        $mid = $this->build(['title' => 'Into Robo Reaver', 'parent_id' => $opener->id]);
        $late = $this->build(['title' => 'Late Game Carrier', 'parent_id' => $mid->id]);

        $this->get(route('builds.show', $late->id))
            ->assertOk()
            ->assertSeeInOrder(['1 Gate Core Expand', 'Into Robo Reaver', 'Late Game Carrier']);
    }

    public function test_an_admin_can_create_a_transition(): void
    {
        $opener = $this->build(['title' => 'Opener']);

        $this->actingAs($this->admin())
            ->post(route('builds.store'), [
                'title' => 'A transition',
                'race' => 'Terran',
                'matchup' => ['TvP'],
                'steps' => 'Add Barracks 4 and 5',
                'parent_id' => $opener->id,
                'phase' => 'Mid game',
                'position' => 1,
            ])
            ->assertRedirect(route('builds.index'));

        $created = BuildOrder::where('title', 'A transition')->firstOrFail();

        $this->assertSame($opener->id, $created->parent_id);
        $this->assertSame('Mid game', $created->phase);
        $this->assertSame(1, $created->position);
    }

    public function test_the_create_form_offers_a_parent_and_a_phase(): void
    {
        $this->build(['title' => 'An existing opener']);

        $this->actingAs($this->admin())
            ->get(route('builds.create'))
            ->assertOk()
            ->assertSee('Continues from')
            ->assertSee('None &mdash; this is an opener', false)
            ->assertSee('An existing opener')
            ->assertSee('Mid game');
    }

    public function test_the_edit_form_preselects_the_current_parent(): void
    {
        $opener = $this->build(['title' => 'The opener']);
        $transition = $this->build(['title' => 'The transition', 'parent_id' => $opener->id]);

        $this->actingAs($this->admin())
            ->get(route('builds.edit', $transition->id))
            ->assertOk()
            ->assertSee('value="' . $opener->id . '" selected', false);
    }

    public function test_the_edit_form_does_not_offer_the_build_itself_as_a_parent(): void
    {
        $build = $this->build(['title' => 'Only build']);

        $this->actingAs($this->admin())
            ->get(route('builds.edit', $build->id))
            ->assertOk()
            ->assertDontSee('value="' . $build->id . '"', false);
    }

    public function test_a_build_cannot_be_its_own_parent(): void
    {
        $build = $this->build();

        $this->actingAs($this->admin())
            ->put(route('builds.update', $build->id), [
                'title' => $build->title,
                'race' => 'Terran',
                'matchup' => ['TvP'],
                'steps' => 'x',
                'parent_id' => $build->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($build->fresh()->parent_id);
    }

    public function test_a_build_cannot_be_parented_onto_its_own_descendant(): void
    {
        $opener = $this->build(['title' => 'Opener']);
        $mid = $this->build(['title' => 'Mid', 'parent_id' => $opener->id]);
        $late = $this->build(['title' => 'Late', 'parent_id' => $mid->id]);

        $this->actingAs($this->admin())
            ->put(route('builds.update', $opener->id), [
                'title' => 'Opener',
                'race' => 'Terran',
                'matchup' => ['TvP'],
                'steps' => 'x',
                'parent_id' => $late->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($opener->fresh()->parent_id);
    }

    public function test_an_unknown_parent_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('builds.store'), [
                'title' => 'Orphan',
                'race' => 'Terran',
                'matchup' => ['TvP'],
                'steps' => 'x',
                'parent_id' => 9999,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(0, BuildOrder::where('title', 'Orphan')->count());
    }

    public function test_an_unknown_phase_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('builds.store'), [
                'title' => 'Bad phase',
                'race' => 'Terran',
                'matchup' => ['TvP'],
                'steps' => 'x',
                'phase' => 'Endgame',
            ])
            ->assertSessionHasErrors('phase');
    }

    public function test_deleting_a_build_with_transitions_is_refused(): void
    {
        $opener = $this->build(['title' => 'Opener']);
        $this->build(['title' => 'Transition', 'parent_id' => $opener->id]);

        $this->actingAs($this->admin())
            ->delete(route('builds.destroy', $opener->id))
            ->assertRedirect(route('builds.show', ['id' => $opener->id]))
            ->assertSessionHas('error');

        $this->assertSame(2, BuildOrder::count());
    }

    public function test_a_build_without_transitions_still_deletes(): void
    {
        $build = $this->build();

        $this->actingAs($this->admin())
            ->delete(route('builds.destroy', $build->id))
            ->assertRedirect(route('builds.index'));

        $this->assertSame(0, BuildOrder::count());
    }

    public function test_possible_parents_exclude_the_build_and_its_descendants(): void
    {
        $opener = $this->build(['title' => 'Opener']);
        $mid = $this->build(['title' => 'Mid', 'parent_id' => $opener->id]);
        $this->build(['title' => 'Late', 'parent_id' => $mid->id]);
        $other = $this->build(['title' => 'Unrelated']);

        $titles = BuildOrder::possibleParentsFor($opener)->pluck('title')->all();

        // Its own subtree is gone, leaving only the unrelated build.
        $this->assertSame(['Unrelated'], $titles);

        // A build with no descendants only loses itself.
        $forOther = BuildOrder::possibleParentsFor($other)->pluck('title')->all();

        $this->assertNotContains('Unrelated', $forOther);
        $this->assertContains('Opener', $forOther);
        $this->assertContains('Mid', $forOther);
        $this->assertContains('Late', $forOther);
    }

    public function test_a_new_build_may_choose_any_existing_parent(): void
    {
        $this->build(['title' => 'Opener']);
        $this->build(['title' => 'Another']);

        $this->assertSame(
            ['Another', 'Opener'],
            BuildOrder::possibleParentsFor(null)->pluck('title')->all()
        );
    }
}
