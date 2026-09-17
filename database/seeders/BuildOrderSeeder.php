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
        $written = [];

        foreach ($builds as $build) {
            $fields = $this->fieldsOf($build);

            $existing = BuildOrder::withTrashed()
                ->where('seed_key', $build['seed_key'])
                ->first();

            if (! $existing) {
                BuildOrder::create($fields + [
                    'seed_key' => $build['seed_key'],
                    'seed_hash' => self::hashFor($build),
                ]);
                $written[] = $build['seed_key'];
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

            $existing->update($fields + ['seed_hash' => self::hashFor($build)]);
            $written[] = $build['seed_key'];
        }

        // Parents are linked in a second pass so a build may be listed before
        // the one it continues from. Only rows this run wrote are touched, so
        // a build re-parented in the admin UI keeps that parent.
        $this->linkParents($builds, $written);

        BuildOrder::whereNotNull('seed_key')
            ->whereNotIn('seed_key', array_column($builds, 'seed_key'))
            ->delete();
    }

    private function linkParents(array $builds, array $written): void
    {
        if ($written === []) {
            return;
        }

        $idsByKey = BuildOrder::whereNotNull('seed_key')->pluck('id', 'seed_key');

        foreach ($builds as $build) {
            if (! in_array($build['seed_key'], $written, true)) {
                continue;
            }

            $parentKey = $build['parent_seed_key'] ?? null;

            BuildOrder::where('seed_key', $build['seed_key'])->update([
                'parent_id' => $parentKey === null ? null : ($idsByKey[$parentKey] ?? null),
            ]);
        }
    }

    /**
     * The columns the seeder writes, separated from its own bookkeeping keys.
     */
    private function fieldsOf(array $build): array
    {
        return [
            'title' => $build['title'],
            'race' => $build['race'],
            'matchup' => $build['matchup'],
            'description' => $build['description'] ?? null,
            'steps' => $build['steps'],
            'youtube_url' => $build['youtube_url'] ?? null,
            'phase' => $build['phase'] ?? null,
            'position' => $build['position'] ?? 0,
        ];
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
            $build['phase'] ?? null,
            $build['position'] ?? 0,
            $build['parent_seed_key'] ?? null,
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
            'phase' => $build->phase,
            'position' => $build->position,
            // Re-parenting in the admin UI changes this, which is what makes
            // such a build read as edited and stops the seeder overwriting it.
            'parent_seed_key' => $build->parent?->seed_key,
        ]);
    }

    protected function builds(): array
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
