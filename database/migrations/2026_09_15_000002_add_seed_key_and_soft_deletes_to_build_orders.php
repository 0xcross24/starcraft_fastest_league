<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('build_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('build_orders', 'seed_key')) {
                // Identifies rows the seeder owns. Null means the build was
                // created by hand in the admin UI, and the seeder leaves it
                // alone. Also lets a seeded build be renamed without the
                // seeder treating it as a new one.
                $table->string('seed_key')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('build_orders', 'deleted_at')) {
                // A deleted build has to leave a trace, otherwise the next
                // deploy would see it missing and seed it straight back.
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('build_orders', function (Blueprint $table) {
            $table->dropColumn(['seed_key', 'deleted_at']);
        });
    }
};
