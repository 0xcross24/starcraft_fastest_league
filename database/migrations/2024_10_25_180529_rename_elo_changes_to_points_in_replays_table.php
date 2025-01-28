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
        Schema::table('replays', function (Blueprint $table) {
            $table->renameColumn('elo_changes', 'points'); // Rename the column
        });
    }

    public function down()
    {
        Schema::table('replays', function (Blueprint $table) {
            $table->renameColumn('points', 'elo_changes'); // Roll back the column name change
        });
    }
};
