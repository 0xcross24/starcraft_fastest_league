<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One login can now own several in-game names (PlayerName::MAX_PER_USER), and
 * each name is ranked on its own. Stats and replays move from being keyed by
 * user to being keyed by player name; user_id stays on both as the owner.
 *
 * Every existing user gets their current player_name as their first name, in
 * user id order, so player_name_id ties break the same way user_id did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('users')->orderBy('id')->select('id', 'player_name')->each(function ($user) use ($now) {
            DB::table('player_names')->insert([
                'user_id' => $user->id,
                'name' => $user->player_name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('stats', function (Blueprint $table) {
            $table->foreignId('player_name_id')->nullable()->constrained('player_names')->cascadeOnDelete();
        });
        DB::table('stats')->update([
            'player_name_id' => DB::raw('(select id from player_names where player_names.user_id = stats.user_id)'),
        ]);
        Schema::table('stats', function (Blueprint $table) {
            // The unique key is the only index MySQL has on user_id, and the
            // foreign key needs one, so add a plain index before dropping it.
            $table->index('user_id');
            $table->dropUnique(['user_id', 'season_id', 'format']);
            $table->unique(['player_name_id', 'season_id', 'format']);
        });

        Schema::table('replays', function (Blueprint $table) {
            $table->foreignId('player_name_id')->nullable()->constrained('player_names')->cascadeOnDelete();
        });
        DB::table('replays')->update([
            'player_name_id' => DB::raw('(select id from player_names where player_names.user_id = replays.user_id)'),
        ]);
    }

    public function down(): void
    {
        // Rows belonging to extra names have nowhere to go once stats are
        // keyed by user again.
        $primaryIds = DB::table('player_names')
            ->join('users', 'users.id', '=', 'player_names.user_id')
            ->whereColumn('player_names.name', 'users.player_name')
            ->pluck('player_names.id');
        DB::table('stats')->whereNotIn('player_name_id', $primaryIds)->delete();
        DB::table('replays')->whereNotIn('player_name_id', $primaryIds)->delete();

        Schema::table('replays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_name_id');
        });

        // The foreign keys lean on these indexes, so each one goes after the
        // index that replaces it is in place.
        Schema::table('stats', function (Blueprint $table) {
            $table->dropForeign(['player_name_id']);
            $table->dropUnique(['player_name_id', 'season_id', 'format']);
            $table->dropColumn('player_name_id');
            $table->unique(['user_id', 'season_id', 'format']);
            $table->dropIndex(['user_id']);
        });

        Schema::dropIfExists('player_names');
    }
};
