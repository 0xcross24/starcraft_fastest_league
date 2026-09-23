<?php

namespace Tests\Feature;

use Database\Seeders\BuildOrderSeeder;

/**
 * The real seeder with every build's version raised, standing in for a PR that
 * deliberately overrides admin edits.
 */
class VersionBumpedBuildOrderSeeder extends BuildOrderSeeder
{
    protected function builds(): array
    {
        return array_map(
            fn (array $build) => $build + ['version' => 2],
            parent::builds()
        );
    }
}
