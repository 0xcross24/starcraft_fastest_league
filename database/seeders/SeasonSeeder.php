<?php

namespace Database\Seeders;

use App\Models\Season;
use Illuminate\Database\Seeder;

class SeasonSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create specific users with custom data
        Season::create([
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
