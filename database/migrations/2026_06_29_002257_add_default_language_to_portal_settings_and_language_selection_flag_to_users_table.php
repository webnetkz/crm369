<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $table->string('default_language')->default(config('app.locale'))->after('logo_path');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_selected_language')->default(false)->after('language');
        });

        $defaultLanguage = (string) config('app.locale', 'ru');

        DB::table('portal_settings')
            ->whereNull('default_language')
            ->update(['default_language' => $defaultLanguage]);

        DB::table('users')
            ->select(['id', 'language'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user) use ($defaultLanguage): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'has_selected_language' => is_string($user->language)
                            && $user->language !== ''
                            && $user->language !== $defaultLanguage,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $table->dropColumn('default_language');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_selected_language');
        });
    }
};
