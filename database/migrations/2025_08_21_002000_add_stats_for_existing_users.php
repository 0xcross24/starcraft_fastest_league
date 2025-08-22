<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Season;
use App\Models\Stats;

return new class extends Migration {
    public function up(): void
    {
        $currentSeason = Season::where('is_active', 1)->first();
        if (!$currentSeason) return;

        $users = User::all();
        foreach ($users as $user) {
            foreach (['2v2', '3v3'] as $format) {
                $exists = Stats::where('user_id', $user->id)
                    ->where('season_id', $currentSeason->id)
                    ->where('format', $format)
                    ->exists();
                if (!$exists) {
                    Stats::create([
                        'user_id' => $user->id,
                        'wins' => 0,
                        'losses' => 0,
                        'elo' => 1000,
                        'season_id' => $currentSeason->id,
                        'format' => $format,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $currentSeason = Season::where('is_active', 1)->first();
        if (!$currentSeason) return;
        Stats::where('season_id', $currentSeason->id)
            ->whereIn('format', ['2v2', '3v3'])
            ->update(['elo' => 1000, 'wins' => 0, 'losses' => 0]);
    }
};
