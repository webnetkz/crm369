<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('user_groups')->updateOrInsert(
            ['name' => 'Administrators'],
            [
                'description' => 'Administrators can view the user list and manage user accounts.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_groups')->where('name', 'Administrators')->delete();
    }
};
