<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The conversation on an invalidation request, one thread per game: players in
 * the game agree, dispute or comment, and admins reply. Mirrored to the game's
 * Discord ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invalidation_comments', function (Blueprint $table) {
            $table->id();
            $table->string('replay_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // agree / dispute from a player in the game; null for a plain comment or an admin's reply
            $table->string('stance')->nullable();
            $table->boolean('from_admin')->default(false);
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invalidation_comments');
    }
};
