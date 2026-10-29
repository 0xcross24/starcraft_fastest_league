<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The site owner gets the new 'owner' role so roles can be managed from /admin.
    public function up(): void
    {
        DB::table('users')->where('player_name', 'Wk`Cross-')->update(['role' => 'owner']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'owner')->update(['role' => 'admin']);
    }
};
