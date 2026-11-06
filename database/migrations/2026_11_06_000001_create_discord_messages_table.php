<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages the site keeps editing in Discord (the live rankings post):
     * which message it is, who sent it (bot or webhook), the format on show
     * and a hash of what it shows, so unchanged standings are not re-sent.
     */
    public function up(): void
    {
        Schema::create('discord_messages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('message_id')->nullable();
            $table->string('via', 10)->nullable();
            $table->string('format', 5)->default('2v2');
            $table->string('hash', 64)->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_messages');
    }
};
