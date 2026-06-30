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
        DB::table('portal_settings')
            ->where(function ($query): void {
                $query->whereNull('company_name')
                    ->orWhere('company_name', '')
                    ->orWhere('company_name', 'Laravel');
            })
            ->update(['company_name' => 'CRM369']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
