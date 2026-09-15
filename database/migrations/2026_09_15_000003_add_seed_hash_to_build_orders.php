<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('build_orders', 'seed_hash')) {
            return;
        }

        Schema::table('build_orders', function (Blueprint $table) {
            // Fingerprint of the content the seeder last wrote. If the row
            // still matches it, nobody has edited the build by hand and the
            // seeder may update it. If it differs, the build was edited in the
            // admin UI and the seeder leaves it alone.
            $table->string('seed_hash', 64)->nullable()->after('seed_key');
        });
    }

    public function down(): void
    {
        Schema::table('build_orders', function (Blueprint $table) {
            $table->dropColumn('seed_hash');
        });
    }
};
