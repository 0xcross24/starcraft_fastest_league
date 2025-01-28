<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEloChangesToReplaysTable extends Migration
{
    public function up()
    {
        Schema::table('replays', function (Blueprint $table) {
            $table->integer('elo_changes')->default(0); // Add the elo_changes column
        });
    }

    public function down()
    {
        Schema::table('replays', function (Blueprint $table) {
            $table->dropColumn('elo_changes'); // Remove the elo_changes column if rolling back
        });
    }
}
