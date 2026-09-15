<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Historical one-off. This reset every stats row to elo 1000 when it
        // was written, and has already run everywhere it applies.
        //
        // It is neutered in place rather than renamed or deleted. The
        // migrations table records it under this exact filename, so renaming
        // it would make Laravel treat it as a new migration and wipe every
        // player's rating. Deleting it would break `migrate:rollback` of that
        // batch. The stats table already defaults elo to 1000, so a fresh
        // install loses nothing by this doing nothing.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // You can back up the previous state of the elo column if needed
        // Example: DB::table('stats')->update(['elo' => 'previous_value']); 
    }
};
