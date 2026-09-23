<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two seeded builds are soft deleted on production and the seeder cannot
     * bring them back on its own.
     *
     * They were added, then removed again when that change was reverted to
     * chase an unrelated outage. Removing a seed_key from the seeder's list
     * soft deletes the row it owns, which is the intended behaviour: it is how
     * a build gets retired. Re-landing the builds afterwards does not undo it,
     * because the seeder skips any row it finds trashed so that a build
     * deleted in the admin UI stays deleted.
     *
     * The result is two builds that exist in the seeder, pass every test, and
     * can never appear on the site. Clearing deleted_at hands them back, and
     * the seeder run in this same deploy refreshes their content and re-links
     * their parent.
     *
     * Named explicitly rather than restoring every trashed seeded row, so a
     * build someone deliberately deleted in the admin UI is not resurrected.
     */
    private const KEYS = [
        'terran-6rax-mass-bio',
        'terran-fast-cc-transition-mech',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('build_orders')) {
            return;
        }

        DB::table('build_orders')
            ->whereIn('seed_key', self::KEYS)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);
    }

    public function down(): void
    {
        // Deliberately empty. This migration repairs data rather than changing
        // schema, and re-deleting the builds on rollback would only reinstate
        // the fault.
    }
};
