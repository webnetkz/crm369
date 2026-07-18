<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('database sequences can be synchronized after records with explicit ids are imported', function () {
    $connection = (string) config('database.default');

    if (DB::connection($connection)->getDriverName() !== 'pgsql') {
        $this->artisan('app:database:synchronize-sequences', [
            '--database' => $connection,
        ])->assertSuccessful();

        return;
    }

    $table = 'sequence_synchronization_test_records';
    $schema = Schema::connection($connection);

    $schema->create($table, function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    try {
        DB::connection($connection)->table($table)->insert([
            'id' => 30,
            'name' => 'Imported record',
        ]);
        DB::connection($connection)->select(
            "select setval(pg_get_serial_sequence(?, 'id'), 1, true)",
            [$table],
        );

        $this->artisan('app:database:synchronize-sequences', [
            '--database' => $connection,
        ])->assertSuccessful();

        $insertedId = DB::connection($connection)->table($table)->insertGetId([
            'name' => 'New record',
        ]);

        expect($insertedId)->toBe(31);
    } finally {
        $schema->dropIfExists($table);
    }
});
