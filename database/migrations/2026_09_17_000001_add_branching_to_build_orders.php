<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('build_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('build_orders', 'parent_id')) {
                // Plain indexed column rather than a foreign key: SQLite cannot
                // add a constraint to an existing table, and builds are soft
                // deleted, so a database-level cascade would not fire anyway.
                // Integrity is enforced in the model and controller instead.
                $table->unsignedBigInteger('parent_id')->nullable()->index()->after('id');
            }

            if (! Schema::hasColumn('build_orders', 'phase')) {
                $table->string('phase')->nullable()->after('race');
            }

            if (! Schema::hasColumn('build_orders', 'position')) {
                $table->unsignedInteger('position')->default(0)->after('phase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('build_orders', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'phase', 'position']);
        });
    }
};
