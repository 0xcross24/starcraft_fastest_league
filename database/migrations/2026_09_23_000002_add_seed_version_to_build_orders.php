<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a seeder change overwrite a build that was edited in the admin UI.
     *
     * Without this, editing a seeded build in the UI made it permanently
     * immune to the seeder - a later PR updating that build merged, deployed
     * and silently changed nothing. Bumping the version in builds() is now an
     * explicit "yes, replace the admin edit".
     */
    public function up(): void
    {
        Schema::table('build_orders', function (Blueprint $table) {
            $table->unsignedInteger('seed_version')->nullable()->after('seed_hash');
        });
    }

    public function down(): void
    {
        Schema::table('build_orders', function (Blueprint $table) {
            $table->dropColumn('seed_version');
        });
    }
};
