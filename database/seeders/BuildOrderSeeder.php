<?php

namespace Database\Seeders;

use App\Models\BuildOrder;
use Illuminate\Database\Seeder;

class BuildOrderSeeder extends Seeder
{
    /**
     * Runs on every deploy, so it has to be safe to repeat.
     *
     * It owns only the rows carrying a seed_key. Builds created by hand in the
     * admin UI have a null seed_key and are never touched.
     *
     * Within the rows it owns:
     *  - a build that is missing is created
     *  - a build still matching the content the seeder last wrote is updated,
     *    so corrections made here reach the site
     *  - a build whose content has diverged was edited in the admin UI, and is
     *    left alone
     *  - a build that was deleted stays deleted, because soft deletes leave a
     *    row behind for this to find
     *  - a build removed from builds() below is deleted
     */
    public function run(): void
    {
        $builds = $this->builds();

        foreach ($builds as $build) {
            $existing = BuildOrder::withTrashed()
                ->where('seed_key', $build['seed_key'])
                ->first();

            if (! $existing) {
                BuildOrder::create($build + ['seed_hash' => self::hashFor($build)]);
                continue;
            }

            if ($existing->trashed()) {
                continue;
            }

            // A null hash means the row was seeded before hashes were
            // recorded. Adopt it rather than reading it as edited, otherwise
            // those builds could never be corrected again.
            if ($existing->seed_hash !== null && $existing->seed_hash !== $this->hashOf($existing)) {
                continue;
            }

            $existing->update($build + ['seed_hash' => self::hashFor($build)]);
        }

        BuildOrder::whereNotNull('seed_key')
            ->whereNotIn('seed_key', array_column($builds, 'seed_key'))
            ->delete();
    }

    /**
     * Fingerprint of the fields the seeder manages.
     */
    public static function hashFor(array $build): string
    {
        return hash('sha256', json_encode([
            $build['title'],
            $build['race'],
            $build['matchup'],
            $build['description'] ?? null,
            $build['steps'],
            $build['youtube_url'] ?? null,
        ]));
    }

    private function hashOf(BuildOrder $build): string
    {
        return self::hashFor([
            'title' => $build->title,
            'race' => $build->race,
            'matchup' => $build->matchup,
            'description' => $build->description,
            'steps' => $build->steps,
            'youtube_url' => $build->youtube_url,
        ]);
    }

    private function builds(): array
    {
        return [
            [
                'seed_key' => 'protoss-storm-drop-pub',
                'title' => 'Protoss Storm Drop Build',
                'race' => 'Protoss',
                'matchup' => ['PUB'],
                'description' => '3 gate forge Storm Drop build',
                'youtube_url' => 'https://youtu.be/uJ_-qEyE3es',
                'steps' => <<<'STEPS'
                    7 Pylon
                    9 Gateway
                    11 Gateway
                    11 Gateway
                    12 Pylon + Zealot
                    15 Forge
                    23 Pylon + Cannon
                    31 Gas
                    31 Gas

                    ** TIP **
                    Every time you make 3 Zealots, make 1 Pylon

                    100% Gas - Cybernetics Core
                    100% Core - Robo + Citadel of Adun + 3rd Gas
                    100% Robo - Robotics Bay + Shuttle
                    100% Adun - Templar Archive

                    Start Shuttle speed and Storm + Stargate + 4th Gas
                    Make 1 Reaver and 3 DTs after

                    Around ~5:00 you want to have your choke blocked with 2-3 Cannons + 4th Gateway
                    STEPS,
            ],
        ];
    }
}
