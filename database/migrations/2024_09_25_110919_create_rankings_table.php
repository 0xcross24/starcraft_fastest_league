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
        Schema::create('rankings', function (Blueprint $table) {
            $table->id('rank_id');
            $table->unsignedBigInteger('rank_season');
            $table->foreign('rank_season')->references('season_id')->on('seasons');
            $table->unsignedBigInteger('rank_account');
            $table->foreign('rank_account')->references('account_id')->on('accounts');
            $table->string('rank_letter');
            $table->integer('rank_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rankings');
    }
};
