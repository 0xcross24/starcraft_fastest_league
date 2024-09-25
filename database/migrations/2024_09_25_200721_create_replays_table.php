<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('replays', function (Blueprint $table) {
            $table->id('replay_id');
            $table->unsignedBigInteger('player_1');
            $table->foreign('player_1')->references('account_id')->on('accounts');
            $table->string('player_1_race');
            $table->integer('player_1_team');
            $table->integer('player_1_apm');
            $table->integer('player_1_eapm');
            $table->integer('player_1_cmd_count');
            $table->integer('player_1_ecmd_count');
            $table->integer('player_1_start_pos');
            $table->unsignedBigInteger('player_2');
            $table->foreign('player_2')->references('account_id')->on('accounts');
            $table->string('player_2_race');
            $table->integer('player_2_team');
            $table->integer('player_2_apm');
            $table->integer('player_2_eapm');
            $table->integer('player_2_cmd_count');
            $table->integer('player_2_ecmd_count');
            $table->integer('player_2_start_pos');
            $table->integer('winning_team');
            $table->dateTime('start_time');
            $table->time('replay_length');
            $table->unsignedBigInteger('map');
            $table->foreign('map')->references('map_id')->on('maps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replays');
    }
};
