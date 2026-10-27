<?php

use App\Services\EloRecalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-rate every season with iCCup's points table, so ratings already earned
 * under the old Elo formula are on the same scale as new games.
 *
 * Not reversible: the old Elo ratings aren't kept. Back up stats and replays
 * before deploying.
 */
return new class extends Migration
{
    public function up(): void
    {
        $recalculator = app(EloRecalculator::class);

        foreach (DB::table('seasons')->orderBy('id')->pluck('id') as $seasonId) {
            $recalculator->recalculate($seasonId);
        }
    }

    public function down(): void
    {
        // Nothing to undo in the schema; restore stats and replays from backup.
    }
};
