<?php

namespace Tests\Feature;

use App\Models\BuildOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildOrderTest extends TestCase
{
    use RefreshDatabase;

    private function build(array $overrides = []): BuildOrder
    {
        return BuildOrder::create(array_merge([
            'title' => 'Two Gate Zealot',
            'description' => 'Standard opener.',
            'race' => 'Protoss',
            'matchup' => ['PvZ'],
            'steps' => "8 Pylon\n10 Gateway",
        ], $overrides));
    }

    public function test_guests_can_view_the_build_index(): void
    {
        $this->build();

        $this->get(route('builds.index'))
            ->assertOk()
            ->assertSee('Two Gate Zealot');
    }

    public function test_guests_can_view_a_single_build(): void
    {
        $build = $this->build();

        $this->get(route('builds.show', $build->id))
            ->assertOk()
            ->assertSee('Two Gate Zealot');
    }

    public function test_show_returns_404_for_a_missing_build(): void
    {
        $this->get(route('builds.show', 9999))->assertNotFound();
    }

    public function test_guests_are_redirected_to_login_from_the_create_form(): void
    {
        $this->get(route('builds.create'))->assertRedirect(route('login'));
    }

    public function test_non_admins_are_forbidden_from_the_create_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('builds.create'))
            ->assertForbidden();
    }

    public function test_admins_can_view_the_create_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('builds.create'))
            ->assertOk();
    }

    public function test_admins_can_store_a_build_order(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('builds.store'), [
                'title' => 'Nine Pool',
                'race' => 'Zerg',
                'matchup' => ['ZvP', 'ZvT'],
                'steps' => "9 Spawning Pool\n9 Overlord",
            ])
            ->assertRedirect(route('builds.index'));

        $build = BuildOrder::where('title', 'Nine Pool')->firstOrFail();

        $this->assertSame('Zerg', $build->race);
        $this->assertSame(['ZvP', 'ZvT'], $build->matchup);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('builds.store'), [])
            ->assertSessionHasErrors(['title', 'race', 'matchup', 'steps']);

        $this->assertSame(0, BuildOrder::count());
    }

    public function test_non_admins_cannot_store_a_build_order(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('builds.store'), [
                'title' => 'Sneaky',
                'race' => 'Terran',
                'matchup' => ['TvP'],
                'steps' => '8 Supply Depot',
            ])
            ->assertForbidden();

        $this->assertSame(0, BuildOrder::count());
    }

    public function test_admins_can_update_a_build_order(): void
    {
        $build = $this->build();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('builds.update', $build->id), [
                'title' => 'Two Gate Goon',
                'race' => 'Protoss',
                'matchup' => ['PvT'],
                'steps' => '8 Pylon',
            ])
            ->assertRedirect(route('builds.index'));

        $this->assertSame('Two Gate Goon', $build->fresh()->title);
    }

    public function test_admins_can_delete_a_build_order(): void
    {
        $build = $this->build();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('builds.destroy', $build->id))
            ->assertRedirect(route('builds.index'));

        $this->assertSame(0, BuildOrder::count());
    }

    public function test_non_admins_cannot_delete_a_build_order(): void
    {
        $build = $this->build();

        $this->actingAs(User::factory()->create())
            ->delete(route('builds.destroy', $build->id))
            ->assertForbidden();

        $this->assertSame(1, BuildOrder::count());
    }

    public function test_index_filters_by_race(): void
    {
        $this->build(['title' => 'Protoss Build', 'race' => 'Protoss']);
        $this->build(['title' => 'Zerg Build', 'race' => 'Zerg', 'matchup' => ['ZvP']]);

        $this->get(route('builds.index', ['race' => 'Zerg']))
            ->assertOk()
            ->assertSee('Zerg Build')
            ->assertDontSee('Protoss Build');
    }

    public function test_index_pub_filter_matches_on_matchup_rather_than_race(): void
    {
        $this->build(['title' => 'Pub Build', 'matchup' => ['PUB']]);
        $this->build(['title' => 'Ladder Build', 'matchup' => ['PvZ']]);

        $this->get(route('builds.index', ['race' => 'Pub']))
            ->assertOk()
            ->assertSee('Pub Build')
            ->assertDontSee('Ladder Build');
    }
}
