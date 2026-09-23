<?php

namespace Tests\Feature;

use App\Models\BuildOrder;
use Database\Seeders\BuildOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the production state left by reverting the change that added these
 * builds: the seed_keys vanished from the seeder's list, so their rows were
 * soft deleted, and re-landing the builds could not bring them back.
 */
class BuildOrderRevertRepairTest extends TestCase
{
    use RefreshDatabase;

    private const RESTORED = [
        'terran-6rax-mass-bio',
        'terran-fast-cc-transition-mech',
    ];

    public function test_the_seeder_alone_cannot_resurrect_a_trashed_build(): void
    {
        $this->seed(BuildOrderSeeder::class);

        BuildOrder::where('seed_key', self::RESTORED[0])->delete();

        $this->seed(BuildOrderSeeder::class);

        $this->assertNull(
            BuildOrder::where('seed_key', self::RESTORED[0])->first(),
            'A trashed seeded build must stay deleted, otherwise admin deletions would be undone.'
        );
    }

    public function test_clearing_deleted_at_hands_the_build_back_to_the_seeder(): void
    {
        $this->seed(BuildOrderSeeder::class);

        foreach (self::RESTORED as $key) {
            BuildOrder::where('seed_key', $key)->delete();
        }

        // What the repair migration does.
        BuildOrder::withTrashed()
            ->whereIn('seed_key', self::RESTORED)
            ->restore();

        $this->seed(BuildOrderSeeder::class);

        foreach (self::RESTORED as $key) {
            $build = BuildOrder::where('seed_key', $key)->first();

            $this->assertNotNull($build, "{$key} should be visible again.");
            $this->assertNotNull($build->parent_id, "{$key} should be re-linked to its opener.");
            $this->assertSame('Mid game', $build->phase);
        }
    }

    public function test_all_three_transitions_show_under_their_opener(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $opener = BuildOrder::where('seed_key', 'terran-3rax-15gas-academy')->firstOrFail();

        $this->assertSame(
            ['6 Rax Mass Bio', 'Fast CC Mech Transition', 'Fast Factory Dropship 5 rax Transition'],
            $opener->transitions->pluck('title')->all()
        );

        $this->get(route('builds.index'))
            ->assertOk()
            ->assertSee('6 Rax Mass Bio')
            ->assertSee('Fast CC Mech Transition')
            ->assertSee('Fast Factory Dropship 5 rax Transition');
    }
}
