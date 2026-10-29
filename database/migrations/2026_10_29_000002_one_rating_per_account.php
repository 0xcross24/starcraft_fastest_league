<?php

use App\Services\EloRecalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ratings move from each player name to the account: one stats row per
 * account, season and format, whichever of its names played the game.
 * Replays keep player_name_id (the name the game was played under).
 *
 * Removed names that have games are kept as retired (retired_at) so their
 * history stays with the account and nobody else can register them.
 *
 * Every season is then re-rated game by game with the account ratings.
 * Not reversible: back up stats and replays before deploying.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_names', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable();
        });

        // Keep each account's oldest row per season and format (its main
        // name's, so the rankings tie-break on stats id stays put).
        $keep = DB::table('stats')
            ->selectRaw('MIN(id) as id')
            ->groupBy('user_id', 'season_id', 'format')
            ->pluck('id');
        DB::table('stats')->whereNotIn('id', $keep)->delete();

        Schema::table('stats', function (Blueprint $table) {
            $table->dropForeign(['player_name_id']);
            $table->dropUnique(['player_name_id', 'season_id', 'format']);
            $table->dropColumn('player_name_id');
            $table->unique(['user_id', 'season_id', 'format']);
        });

        $recalculator = app(EloRecalculator::class);
        foreach (DB::table('seasons')->orderBy('id')->pluck('id') as $seasonId) {
            $recalculator->recalculate($seasonId);
        }
    }

    public function down(): void
    {
        // Per-name ratings are gone; restore stats and replays from backup.
    }
};
