<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Set all Elo ratings to 1000
        DB::table('stats')->update(['elo' => 1000]);
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
