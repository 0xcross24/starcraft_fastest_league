<?php

namespace Tests\Feature;

use App\Models\BuildOrder;
use Database\Seeders\BuildOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildOrderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_build_orders(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('title', 'Protoss Storm Drop Build')->firstOrFail();

        $this->assertSame('Protoss', $build->race);
        $this->assertSame(['PUB'], $build->matchup);
        $this->assertStringContainsString('6 Pylon', $build->steps);
        $this->assertStringContainsString('Templar Archive', $build->steps);
    }

    public function test_running_it_again_does_not_duplicate(): void
    {
        $this->seed(BuildOrderSeeder::class);
        $this->seed(BuildOrderSeeder::class);

        $this->assertSame(1, BuildOrder::where('title', 'Protoss Storm Drop Build')->count());
    }

    public function test_running_it_again_preserves_edits_made_in_the_admin_ui(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('title', 'Protoss Storm Drop Build')->firstOrFail();
        $build->update([
            'steps' => 'Edited by an admin',
            'description' => 'Edited description',
            'youtube_url' => 'https://example.com/vod',
        ]);

        $this->seed(BuildOrderSeeder::class);

        $build->refresh();

        $this->assertSame('Edited by an admin', $build->steps);
        $this->assertSame('Edited description', $build->description);
        $this->assertSame('https://example.com/vod', $build->youtube_url);
    }

    public function test_seeded_builds_appear_under_the_pub_filter(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $this->get(route('builds.index', ['race' => 'Pub']))
            ->assertOk()
            ->assertSee('Protoss Storm Drop Build');
    }
}
