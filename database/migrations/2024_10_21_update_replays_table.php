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
            $table->id(); // This creates the 'id' column as a primary key
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Add user_id
            $table->string('replay_id'); // Assuming replay_id should be unique
            $table->string('player_name');
            $table->tinyInteger('winning_team'); // Assuming winning_team can be 0 or 1
            $table->timestamp('start_time')->nullable(); // Can be nullable if not always provided
            $table->string('team'); // Assuming team is a string representation
            $table->string('hash'); // Assuming hash should be unique
            $table->timestamps(); // This will create 'created_at' and 'updated_at' columns
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
