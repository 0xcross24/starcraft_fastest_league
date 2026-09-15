<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production has this table already, created outside the migration
        // runner, so it is recorded as pending there. Without this guard the
        // first deploy that runs migrations fails on "table already exists".
        if (Schema::hasTable('build_orders')) {
            return;
        }

        Schema::create('build_orders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('race');
            $table->json('matchup')->nullable();
            $table->text('steps')->nullable();
            $table->string('youtube_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('build_orders');
    }
};
