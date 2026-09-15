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
     *  - a build that already exists is left alone, so admin edits survive
     *  - a build that was deleted stays deleted, because soft deletes leave a
     *    row behind for this to find
     *  - a build removed from builds() below is deleted
     */
    public function run(): void
    {
        $builds = $this->builds();

        foreach ($builds as $build) {
            $alreadyHandled = BuildOrder::withTrashed()
                ->where('seed_key', $build['seed_key'])
                ->exists();

            if ($alreadyHandled) {
                continue;
            }

            BuildOrder::create($build);
        }

        BuildOrder::whereNotNull('seed_key')
            ->whereNotIn('seed_key', array_column($builds, 'seed_key'))
            ->delete();
    }

    private function builds(): array
    {
        return [
            [
                'seed_key' => 'protoss-storm-drop-pub',
                'title' => 'Protoss Storm Drop Build',
                'race' => 'Protoss',
                'matchup' => ['PUB'],
                'description' => 'Every time you make 3 Zealots, make 1 Pylon.',
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
