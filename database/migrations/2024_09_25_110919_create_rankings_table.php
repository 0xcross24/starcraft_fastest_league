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
            $table->foreign('rank_season')->references('seasons')->on('season_id');
            $table->foreign('rank_account')->references('accounts')->on('account_id');
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
