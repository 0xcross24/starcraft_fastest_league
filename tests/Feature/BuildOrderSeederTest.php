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
        $this->assertSame('https://youtu.be/uJ_-qEyE3es', $build->youtube_url);
    }

    public function test_it_seeds_the_terran_opener(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('seed_key', 'terran-3rax-15gas-academy')->firstOrFail();

        $this->assertSame('3 Rax 15 Gas Academy', $build->title);
        $this->assertSame('Terran', $build->race);
        $this->assertSame(['PUB'], $build->matchup);
        $this->assertSame('Opener', $build->phase);
        $this->assertStringContainsString('8 Depot', $build->steps);
        $this->assertStringContainsString('100% Academy - 3 medics', $build->steps);
        $this->assertNull($build->parent_id);
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
        $this->assertSame(1, BuildOrder::where('seed_key', self::KEY)->count());
    }

    public function test_an_unedited_build_is_updated_to_match_the_seeder(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('seed_key', self::KEY)->firstOrFail();

        // Stands in for the seeder's content changing since the last run: the
        // row holds an older version, and its hash matches that older version,
        // so it still reads as untouched by hand.
        $older = [
            'title' => self::TITLE,
            'race' => 'Protoss',
            'matchup' => ['PUB'],
            'description' => 'an older subtitle',
            'steps' => 'an older build order',
            'youtube_url' => null,
        ];

        // Query builder bypasses the model's array cast, so matchup has to be
        // encoded here while the hash is taken over the array form.
        BuildOrder::where('id', $build->id)->update(array_merge($older, [
            'matchup' => json_encode($older['matchup']),
            'seed_hash' => BuildOrderSeeder::hashFor($older),
        ]));

        $this->seed(BuildOrderSeeder::class);

        $build->refresh();

        $this->assertSame('3 gate forge Storm Drop build', $build->description);
        $this->assertStringContainsString('7 Pylon', $build->steps);
        $this->assertSame(1, BuildOrder::where('seed_key', self::KEY)->count());
    }

    public function test_a_build_seeded_before_hashes_were_recorded_is_adopted(): void
    {
        $this->seed(BuildOrderSeeder::class);

        // Rows created by the previous version of the seeder carry no hash.
        BuildOrder::where('seed_key', self::KEY)->update([
            'seed_hash' => null,
            'description' => 'the old subtitle',
        ]);

        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('seed_key', self::KEY)->firstOrFail();

        $this->assertSame('3 gate forge Storm Drop build', $build->description);
        $this->assertNotNull($build->seed_hash);
    }

    public function test_the_tip_is_in_the_steps_and_the_description_is_a_subtitle(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('seed_key', self::KEY)->firstOrFail();

        $this->assertSame('3 gate forge Storm Drop build', $build->description);
        $this->assertStringContainsString('Every time you make 3 Zealots', $build->steps);
        $this->assertStringNotContainsString('Zealots', $build->description);
    }

    public function test_a_build_deleted_in_the_admin_ui_is_not_reseeded(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $build = BuildOrder::where('seed_key', self::KEY)->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('builds.destroy', $build->id))
            ->assertRedirect(route('builds.index'));

        $this->seed(BuildOrderSeeder::class);

        $this->assertSame(0, BuildOrder::where('seed_key', self::KEY)->count());
        $this->assertSame(1, BuildOrder::withTrashed()->where('seed_key', self::KEY)->count());
    }

    public function test_a_build_removed_from_the_seeder_is_deleted(): void
    {
        $this->seed(BuildOrderSeeder::class);
        $this->assertSame(1, BuildOrder::where('seed_key', self::KEY)->count());

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

    public function test_a_seeded_build_can_declare_its_parent_by_seed_key(): void
    {
        $seeder = new class extends BuildOrderSeeder {
            protected function builds(): array
            {
                return [
                    // Child listed first, to prove order in the file does not matter.
                    [
                        'seed_key' => 'child',
                        'parent_seed_key' => 'parent',
                        'title' => 'A transition',
                        'race' => 'Terran',
                        'matchup' => ['TvP'],
                        'steps' => 'x',
                        'phase' => 'Mid game',
                    ],
                    [
                        'seed_key' => 'parent',
                        'title' => 'An opener',
                        'race' => 'Terran',
                        'matchup' => ['TvP'],
                        'steps' => 'y',
                    ],
                ];
            }
        };

        $seeder->run();

        $parent = BuildOrder::where('seed_key', 'parent')->firstOrFail();
        $child = BuildOrder::where('seed_key', 'child')->firstOrFail();

        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame('Mid game', $child->phase);
        $this->assertNull($parent->parent_id);
        $this->assertSame(['A transition'], $parent->transitions()->pluck('title')->all());
    }

    public function test_re_parenting_in_the_admin_ui_survives_reseeding(): void
    {
        $seeder = new class extends BuildOrderSeeder {
            protected function builds(): array
            {
                return [
                    ['seed_key' => 'a', 'title' => 'A', 'race' => 'Terran', 'matchup' => ['TvP'], 'steps' => 'x'],
                    ['seed_key' => 'b', 'title' => 'B', 'race' => 'Terran', 'matchup' => ['TvP'], 'steps' => 'y'],
                ];
            }
        };

        $seeder->run();

        $a = BuildOrder::where('seed_key', 'a')->firstOrFail();
        $b = BuildOrder::where('seed_key', 'b')->firstOrFail();

        // An admin makes B continue from A.
        $b->update(['parent_id' => $a->id]);

        $seeder->run();

        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    public function test_seeded_builds_appear_under_the_pub_filter(): void
    {
        $this->seed(BuildOrderSeeder::class);

        $this->get(route('builds.index', ['race' => 'Pub']))
            ->assertOk()
            ->assertSee(self::TITLE);
    }
}
