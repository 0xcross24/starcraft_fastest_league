<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('is_active');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
        });

        // Existing seasons never recorded their dates: use their first game as
        // the start and, for finished seasons, their last game as the end
        // (Season 1 went on hiatus long before Season 2 began).
        foreach (DB::table('seasons')->get() as $season) {
            $games = DB::table('replays')
                ->where('season_id', $season->id)
                ->selectRaw('min(coalesce(start_time, created_at)) as first_game, max(coalesce(start_time, created_at)) as last_game')
                ->first();

            DB::table('seasons')->where('id', $season->id)->update([
                'starts_at' => $games->first_game,
                'ends_at' => $season->is_active ? null : $games->last_game,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
