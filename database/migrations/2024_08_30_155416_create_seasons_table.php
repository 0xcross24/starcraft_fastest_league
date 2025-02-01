<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();  // Auto-increment primary key
            $table->boolean('is_active')->default(true);
            $table->timestamps();  // Optional: Add timestamps if needed

            // Set the storage engine to InnoDB and the character set to utf8mb4
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
