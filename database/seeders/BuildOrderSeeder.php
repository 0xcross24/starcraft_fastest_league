<?php

namespace Database\Seeders;

use App\Models\BuildOrder;
use Illuminate\Database\Seeder;

class BuildOrderSeeder extends Seeder
{
    /**
     * Uses firstOrCreate rather than updateOrCreate so that re-running this to
     * add new builds never overwrites edits made through the admin UI.
     */
    public function run(): void
    {
        foreach ($this->builds() as $build) {
            BuildOrder::firstOrCreate(
                [
                    'title' => $build['title'],
                    'race' => $build['race'],
                ],
                $build
            );
        }
    }

    private function builds(): array
    {
        return [
            [
                'title' => 'Protoss Storm Drop Build',
                'race' => 'Protoss',
                'matchup' => ['PUB'],
                'description' => 'Every time you make 3 Zealots, make 1 Pylon.',
                'steps' => <<<'STEPS'
                    6 Pylon
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
