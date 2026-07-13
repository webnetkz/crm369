<?php

use App\Support\DatabaseConnectionDataMigrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->sourceDatabasePath = tempnam(sys_get_temp_dir(), 'crm369-source-');
    $this->targetDatabasePath = tempnam(sys_get_temp_dir(), 'crm369-target-');

    config([
        'database.connections.sqlite_source' => [
            'driver' => 'sqlite',
            'database' => $this->sourceDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.connections.sqlite_target' => [
            'driver' => 'sqlite',
            'database' => $this->targetDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('sqlite_source');
    DB::purge('sqlite_target');

    foreach (['sqlite_source', 'sqlite_target'] as $connection) {
        Schema::connection($connection)->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::connection($connection)->create('projects', function ($table): void {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->string('name');
            $table->timestamps();
        });

        Schema::connection($connection)->create('project_tasks', function ($table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('creator_user_id')->constrained('users');
            $table->foreignId('parent_task_id')->nullable()->constrained('project_tasks');
            $table->string('title');
            $table->timestamps();
        });
    }
});

afterEach(function () {
    @unlink($this->sourceDatabasePath);
    @unlink($this->targetDatabasePath);
});

test('database connection data migrator copies dependent tables in a safe order', function () {
    DB::connection('sqlite_source')->table('users')->insert([
        [
            'id' => 5,
            'name' => 'Owner',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::connection('sqlite_source')->table('projects')->insert([
        [
            'id' => 10,
            'owner_user_id' => 5,
            'name' => 'Migration Project',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::connection('sqlite_source')->table('project_tasks')->insert([
        [
            'id' => 100,
            'project_id' => 10,
            'creator_user_id' => 5,
            'parent_task_id' => null,
            'title' => 'Parent task',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 101,
            'project_id' => 10,
            'creator_user_id' => 5,
            'parent_task_id' => 100,
            'title' => 'Child task',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::connection('sqlite_target')->table('users')->insert([
        [
            'id' => 1,
            'name' => 'Old target user',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $result = app(DatabaseConnectionDataMigrator::class)->migrate(
        sourceConnection: 'sqlite_source',
        targetConnection: 'sqlite_target',
    );

    expect($result)->toBe([
        'users' => 1,
        'projects' => 1,
        'project_tasks' => 2,
    ])->and(DB::connection('sqlite_target')->table('users')->count())->toBe(1)
        ->and(DB::connection('sqlite_target')->table('projects')->count())->toBe(1)
        ->and(DB::connection('sqlite_target')->table('project_tasks')->count())->toBe(2)
        ->and(DB::connection('sqlite_target')->table('project_tasks')->where('id', 101)->value('parent_task_id'))->toBe(100);
});

test('database connection data migrator rejects identical source and target connections', function () {
    expect(fn () => app(DatabaseConnectionDataMigrator::class)->migrate(
        sourceConnection: 'sqlite_source',
        targetConnection: 'sqlite_source',
    ))->toThrow(InvalidArgumentException::class);
});

test('database connection data migration command copies rows between configured connections', function () {
    DB::connection('sqlite_source')->table('users')->insert([
        [
            'id' => 7,
            'name' => 'Command owner',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->artisan('app:database:migrate-connection-data', [
        '--from' => 'sqlite_source',
        '--to' => 'sqlite_target',
        '--skip-migrate-target' => true,
    ])->assertSuccessful();

    expect(DB::connection('sqlite_target')->table('users')->where('id', 7)->value('name'))
        ->toBe('Command owner');
});
