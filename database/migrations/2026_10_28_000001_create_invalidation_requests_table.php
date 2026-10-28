<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Players asking for one of their games to be invalidated. The site is the
 * record; the Discord ticket (a forum post) is where admins talk it over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invalidation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('replay_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_name_id')->nullable()->constrained('player_names')->nullOnDelete();
            $table->string('reason');
            $table->text('justification');
            $table->string('status')->default('open');
            $table->string('discord_thread_id')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            // One request per player per game
            $table->unique(['replay_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invalidation_requests');
    }
};
