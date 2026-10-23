<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store each player's ELO after every game, instead of the pages working it
 * out by adding up the points of whatever games they happened to show. That
 * gave other players wrong numbers on player pages and would break paging.
 *
 * Backfill: per season and format, every name starts at 1000 and gains each
 * game's points in upload order. For every name this ends exactly on its
 * current stats ELO (checked on production 2026-10-23). Invalidated games
 * carry no ELO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replays', function (Blueprint $table) {
            $table->integer('elo_after')->nullable()->after('points');
        });

        $running = [];
        DB::table('replays')
            ->select('id', 'player_name_id', 'season_id', 'format', 'points', 'is_invalid')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function ($row) use (&$running) {
                if ($row->is_invalid) {
                    return;
                }
                $key = "{$row->season_id}|{$row->format}|{$row->player_name_id}";
                $running[$key] = ($running[$key] ?? 1000) + $row->points;

                DB::table('replays')->where('id', $row->id)->update(['elo_after' => $running[$key]]);
            });
    }

    public function down(): void
    {
        Schema::table('replays', function (Blueprint $table) {
            $table->dropColumn('elo_after');
        });
    }
};
