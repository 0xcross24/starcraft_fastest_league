<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('stats', function (Blueprint $table) {
            //
        });
    }
};
