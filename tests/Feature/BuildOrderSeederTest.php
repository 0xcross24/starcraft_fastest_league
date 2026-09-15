<?php

namespace Tests\Feature;

use App\Models\BuildOrder;
use App\Models\User;
use Database\Seeders\BuildOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildOrderSeederTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'protoss-storm-drop-pub';
    private const TITLE = 'Protoss Storm Drop Build';

    public function test_it_seeds_the_build_orders(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('seed_key', self::KEY)->firstOrFail();

        $this->assertSame(self::TITLE, $build->title);
        $this->assertSame('Protoss', $build->race);
        $this->assertSame(['PUB'], $build->matchup);
        $this->assertStringContainsString('7 Pylon', $build->steps);
        $this->assertStringContainsString('Templar Archive', $build->steps);
    }

    public function test_running_it_again_does_not_duplicate(): void
    {
        $this->seed(BuildOrderSeeder::class);
        $this->seed(BuildOrderSeeder::class);

        $this->assertSame(1, BuildOrder::where('seed_key', self::KEY)->count());
    }

    public function test_running_it_again_preserves_edits(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('seed_key', self::KEY)->firstOrFail();
        $build->update([
            'title' => 'Renamed by an admin',
            'steps' => 'Edited by an admin',
            'youtube_url' => 'https://example.com/vod',
        ]);

        $this->seed(BuildOrderSeeder::class);

        $build->refresh();

        $this->assertSame('Renamed by an admin', $build->title);
        $this->assertSame('Edited by an admin', $build->steps);
        $this->assertSame('https://example.com/vod', $build->youtube_url);
        $this->assertSame(1, BuildOrder::count());
    }

    public function test_a_build_deleted_in_the_admin_ui_is_not_reseeded(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('seed_key', self::KEY)->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('builds.destroy', $build->id))
            ->assertRedirect(route('builds.index'));

        $this->seed(BuildOrderSeeder::class);

        $this->assertSame(0, BuildOrder::count());
        $this->assertSame(1, BuildOrder::withTrashed()->count());
    }

    public function test_a_build_removed_from_the_seeder_is_deleted(): void
    {
        $this->seed(BuildOrderSeeder::class);
        $this->assertSame(1, BuildOrder::count());

        // Stands in for a build being taken out of the seeder's list.
        BuildOrder::where('seed_key', self::KEY)->update(['seed_key' => 'retired-build']);

        $this->seed(BuildOrderSeeder::class);

        $this->assertNull(BuildOrder::where('seed_key', 'retired-build')->first());
        $this->assertNotNull(BuildOrder::withTrashed()->where('seed_key', 'retired-build')->first());
    }

    public function test_builds_created_by_hand_are_never_touched(): void
    {
        $manual = BuildOrder::create([
            'title' => 'Hand written build',
            'race' => 'Zerg',
            'matchup' => ['ZvP'],
            'steps' => '9 Pool',
        ]);

        $this->seed(BuildOrderSeeder::class);

        $manual->refresh();

        $this->assertNull($manual->seed_key);
        $this->assertNull($manual->deleted_at);
        $this->assertSame('Hand written build', $manual->title);
    }

    public function test_deleted_builds_do_not_appear_in_the_listing(): void
    {
        $this->seed(BuildOrderSeeder::class);

        BuildOrder::where('seed_key', self::KEY)->delete();

        $this->get(route('builds.index'))
            ->assertOk()
            ->assertDontSee(self::TITLE);
    }

    public function test_seeded_builds_appear_under_the_pub_filter(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $this->get(route('builds.index', ['race' => 'Pub']))
            ->assertOk()
            ->assertSee(self::TITLE);
    }
}
