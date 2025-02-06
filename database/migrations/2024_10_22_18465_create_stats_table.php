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
        // Create the stats table
        Schema::create('stats', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->integer('wins')->default(0); // Wins counter
            $table->integer('losses')->default(0); // Losses counter
            $table->bigInteger('elo')->default(1000); // elo ranking default starts with 1000

            // Foreign key constraint
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Link to user
            $table->foreignId('season_id')->constrained('seasons')->onDelete('cascade'); // Link to season

            $table->unique(['user_id', 'season_id']);  // Ensures one stat record per user per season
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats');
    }
};
