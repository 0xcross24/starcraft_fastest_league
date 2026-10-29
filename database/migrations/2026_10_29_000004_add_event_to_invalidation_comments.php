<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decisions and reopenings go into the game's thread as events, so the
 * history stays readable after a rejected request is reopened. An event made
 * by the site itself (every player agreed) has no user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invalidation_comments', function (Blueprint $table) {
            $table->string('event')->nullable(); // approved / rejected / reopened
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invalidation_comments', function (Blueprint $table) {
            $table->dropColumn('event');
        });
    }
};
