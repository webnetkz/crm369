<?php

use App\Models\SystemUpdateRun;
use App\Models\SystemUpdateSnapshot;
use App\Models\User;
use App\Support\SystemUpdates\GitHubReleaseClient;
use App\Support\SystemUpdates\SystemUpdateDatabaseReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'admin.super_admin_email' => 'super@example.com',
        'system-updates.repository' => 'webnetkz/crm369',
        'system-updates.branch' => 'main',
        'system-updates.bridge_path' => '/usr/bin/true',
    ]);
});

test('system updates are restricted to a password confirmed super administrator', function () {
    $user = User::factory()->create();
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->get(route('settings.system-updates.edit'))
        ->assertRedirect(route('login'));

    $this->actingAs($superAdmin)
        ->get(route('settings.system-updates.edit'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.system-updates.edit'))
        ->assertForbidden();
});

test('system update page remains available before its database migrations are applied', function () {
    Process::fake();
    systemUpdateDatabaseIsNotReady();
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.system-updates.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/SystemUpdates')
            ->where('databaseReady', false)
            ->where('snapshot.error', __('ui.system_updates.errors.database_not_ready'))
            ->where('latestRun', null)
            ->has('history', 0),
        );
});

test('version checks are blocked before the system update migrations are applied', function () {
    Http::preventStrayRequests();
    systemUpdateDatabaseIsNotReady();
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.system-updates.checks.store'))
        ->assertRedirect()
        ->assertSessionHasErrors('database');

    expect(SystemUpdateSnapshot::query()->count())->toBe(0);
});

test('updates are not started before the system update migrations are applied', function () {
    Process::fake();
    systemUpdateDatabaseIsNotReady();
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.system-updates.components.update', 'application'))
        ->assertRedirect()
        ->assertSessionHasErrors('database');

    expect(SystemUpdateRun::query()->count())->toBe(0);
    Process::assertNothingRan();
});

test('scheduled version check fails cleanly before the system update migrations are applied', function () {
    systemUpdateDatabaseIsNotReady();

    $this->artisan('crm369:updates:check')
        ->expectsOutput(__('ui.system_updates.errors.database_not_ready'))
        ->assertExitCode(Command::FAILURE);
});

test('super administrator sees current and latest versions with update history', function () {
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $snapshot = SystemUpdateSnapshot::query()->create([
        'components' => [
            systemUpdateComponent(),
            systemUpdateComponent([
                'key' => 'php',
                'currentVersion' => '8.4.10',
                'latestVersion' => '8.4.11',
            ]),
        ],
        'checked_at' => now(),
    ]);
    SystemUpdateRun::query()->create([
        'uuid' => '019fa2c4-4b29-7e85-9a02-5e57ea58b644',
        'requested_by_user_id' => $superAdmin->id,
        'component' => 'application',
        'status' => 'completed',
        'current_version' => 'v1.0.0',
        'target_version' => 'v1.1.0',
        'progress' => 100,
        'finished_at' => $snapshot->checked_at->subMinute(),
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.system-updates.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/SystemUpdates')
            ->where('repository.name', 'webnetkz/crm369')
            ->where('repository.branch', 'main')
            ->has('snapshot.components', 2)
            ->where('snapshot.components.0.key', 'application')
            ->where('snapshot.components.0.currentVersion', 'v1.0.0')
            ->where('snapshot.components.0.latestVersion', 'v1.1.0')
            ->where('history.0.status', 'completed')
            ->where('history.0.requestedBy.name', $superAdmin->name),
        );
});

test('super administrator can check GitHub and package versions', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/webnetkz/crm369/releases/latest' => Http::response([
            'tag_name' => 'v1.2.0',
            'html_url' => 'https://github.com/webnetkz/crm369/releases/tag/v1.2.0',
            'published_at' => now()->toIso8601String(),
        ]),
        'api.github.com/repos/webnetkz/crm369/commits/v1.2.0' => Http::response([
            'sha' => str_repeat('b', 40),
        ]),
        'repo.packagist.org/p2/laravel/framework.json' => Http::response([
            'packages' => [
                'laravel/framework' => [
                    ['version' => 'v13.20.0'],
                ],
            ],
        ]),
        'getcomposer.org/versions' => Http::response([
            'stable' => [
                ['version' => '2.9.4'],
            ],
        ]),
    ]);
    Process::fake([
        '*' => Process::result(output: ''),
    ]);

    $statePath = storage_path('framework/testing-system-version.json');
    file_put_contents($statePath, json_encode([
        'version' => 'v1.0.0',
        'reference' => str_repeat('a', 40),
    ], JSON_THROW_ON_ERROR));
    config(['system-updates.version_state_path' => $statePath]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.system-updates.checks.store'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $application = collect(SystemUpdateSnapshot::query()->latest()->firstOrFail()->components)
        ->firstWhere('key', 'application');

    expect($application)
        ->toMatchArray([
            'currentVersion' => 'v1.0.0',
            'latestVersion' => 'v1.2.0',
            'currentReference' => str_repeat('a', 40),
            'latestReference' => str_repeat('b', 40),
            'status' => 'update_available',
            'updateAvailable' => true,
            'canUpdate' => true,
        ]);

    @unlink($statePath);
});

test('GitHub version check falls back to the configured branch when no release exists', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/webnetkz/crm369/releases/latest' => Http::response(status: 404),
        'api.github.com/repos/webnetkz/crm369/commits/main' => Http::response([
            'sha' => str_repeat('c', 40),
            'html_url' => 'https://github.com/webnetkz/crm369/commit/'.str_repeat('c', 40),
            'commit' => [
                'committer' => [
                    'date' => '2026-07-24T10:00:00Z',
                ],
            ],
        ]),
    ]);

    expect(app(GitHubReleaseClient::class)->latest())->toMatchArray([
        'version' => 'main @ cccccccc',
        'reference' => str_repeat('c', 40),
        'channel' => 'branch',
    ]);
});

test('super administrator can start only a server-approved available update', function () {
    Process::fake([
        '*' => Process::result(output: ''),
    ]);
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    SystemUpdateSnapshot::query()->create([
        'components' => [systemUpdateComponent()],
        'checked_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.system-updates.components.update', 'application'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $run = SystemUpdateRun::query()->sole();

    expect($run->requested_by_user_id)->toBe($superAdmin->id)
        ->and($run->component)->toBe('application')
        ->and($run->status)->toBe('running')
        ->and($run->target_reference)->toBe(str_repeat('b', 40));

    Process::assertRan(function ($process): bool {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : $process->command;

        return str_contains($command, '/usr/bin/true')
            && str_contains($command, 'start')
            && str_contains($command, 'application')
            && ! str_contains($command, 'sh -c');
    });
});

test('updates cannot be started for arbitrary components or without an available update', function () {
    Process::fake();
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    SystemUpdateSnapshot::query()->create([
        'components' => [systemUpdateComponent([
            'status' => 'current',
            'updateAvailable' => false,
            'canUpdate' => false,
        ])],
        'checked_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.system-updates.components.update', 'application'))
        ->assertSessionHasErrors('component');

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/settings/system-updates/components/shell')
        ->assertNotFound();

    expect(SystemUpdateRun::query()->count())->toBe(0);
});

test('stale updater processes are marked as failed when the page is opened', function () {
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $run = SystemUpdateRun::query()->create([
        'uuid' => '019fa2c4-4b29-7e85-9a02-5e57ea58b645',
        'requested_by_user_id' => $superAdmin->id,
        'component' => 'php',
        'status' => 'running',
        'progress' => 40,
    ]);
    $run->forceFill([
        'created_at' => now()->subMinutes(61),
        'updated_at' => now()->subMinutes(61),
    ])->saveQuietly();

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.system-updates.edit'))
        ->assertSuccessful();

    expect($run->refresh())
        ->status->toBe('failed')
        ->finished_at->not->toBeNull();
});

test('completed updater progress is synchronized without persisting transient steps', function () {
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $run = SystemUpdateRun::query()->create([
        'uuid' => '019fa2c4-4b29-7e85-9a02-5e57ea58b646',
        'requested_by_user_id' => $superAdmin->id,
        'component' => 'ubuntu',
        'status' => 'running',
        'progress' => 1,
        'stage' => 'starting',
    ]);
    $directory = sys_get_temp_dir().'/crm369-progress-'.bin2hex(random_bytes(5));
    mkdir($directory, 0700, true);
    config(['system-updates.progress_directory' => $directory]);
    file_put_contents($directory.'/'.$run->uuid.'.json', json_encode([
        'status' => 'completed',
        'progress' => 100,
        'stage' => 'completed',
        'message' => 'Обновление успешно завершено.',
        'started_at' => '2026-07-24T01:38:38+00:00',
        'finished_at' => '2026-07-24T01:39:44+00:00',
    ], JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/'.$run->uuid.'.steps', json_encode([
        'at' => '2026-07-24T01:39:44+00:00',
        'progress' => 100,
        'stage' => 'completed',
        'message' => 'Обновление успешно завершено.',
    ], JSON_THROW_ON_ERROR).PHP_EOL);

    try {
        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('settings.system-updates.edit'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->where('latestRun.status', 'completed')
                ->where('latestRun.progress', 100)
                ->has('latestRun.steps', 1),
            );

        expect($run->refresh())
            ->status->toBe('completed')
            ->progress->toBe(100)
            ->stage->toBe('completed')
            ->finished_at->not->toBeNull();
    } finally {
        @unlink($directory.'/'.$run->uuid.'.json');
        @unlink($directory.'/'.$run->uuid.'.steps');
        @rmdir($directory);
    }
});

test('system updater uses an independent allowlisted root bridge with backups and migrations', function () {
    $updater = file_get_contents(base_path('scripts/update-system.sh'));
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));
    $bridgeInspector = file_get_contents(base_path('app/Support/SystemUpdates/SystemUpdateBridgeInspector.php'));
    $applicationUpdate = explode('update_laravel() {', explode('update_application() {', $updater, 2)[1], 2)[0];
    $laravelUpdate = explode('execute_update() {', explode('update_laravel() {', $updater, 2)[1], 2)[0];

    expect($updater)
        ->toContain('systemd-run')
        ->toContain('case "$component" in')
        ->toContain('application|laravel|php|postgresql|redis|nginx|node|composer|ubuntu')
        ->toContain('pg_dump --format=custom')
        ->toContain('pg_dump --format=custom crm369 >"$database_backup_file"')
        ->not->toContain('pg_dump --format=custom --file=')
        ->toContain('COMPOSER_HOME="$COMPOSER_HOME_PATH"')
        ->toContain('composer_command update laravel/framework')
        ->toContain('--no-scripts')
        ->toContain('app_command package:discover --no-interaction')
        ->toContain('normalize_laravel_permissions')
        ->toContain('restore_laravel_dependencies')
        ->toContain('ensure_storage_link')
        ->toContain('ln -sfn "$storage_target" "$storage_link"')
        ->not->toContain('app_command storage:link')
        ->toContain('workers_stop')
        ->toContain('workers_start')
        ->toContain("database_migrations_started='true'")
        ->toContain('database_restore')
        ->toContain('runuser -u postgres -- pg_restore')
        ->toContain('--clean')
        ->toContain('--if-exists')
        ->toContain('--exit-on-error')
        ->toContain('pg_terminate_backend')
        ->toContain('chown -R root:"$APP_GROUP" "${APP_PATH}/vendor"')
        ->toContain('chown -R "$APP_USER:$APP_GROUP" "${APP_PATH}/bootstrap/cache"')
        ->toContain('migrate --force --no-interaction --isolated')
        ->not->toContain('app_command queue:restart')
        ->toContain('health_check')
        ->toContain('flock -n 9')
        ->toContain('"${incoming_path}/bootstrap/cache"')
        ->toContain('install -m 0755 -o root -g root')
        ->not->toContain('sh -c')
        ->and($installer)
        ->toContain('/usr/local/sbin/crm369-updater')
        ->toContain('install -m 0755 -o root -g root')
        ->toContain('NOPASSWD: /usr/local/sbin/crm369-updater start *')
        ->toContain('visudo -cf')
        ->and($bridgeInspector)
        ->toContain('@lstat($path)')
        ->toContain('($permissions & 0170000) === 0100000')
        ->toContain('($permissions & 0100) !== 0')
        ->toContain('($permissions & 0022) === 0')
        ->not->toContain('is_executable($path)');

    expect(strpos($applicationUpdate, 'maintenance_begin'))
        ->toBeLessThan(strpos($applicationUpdate, 'database_backup'))
        ->and(strpos($applicationUpdate, "database_migrations_started='true'"))
        ->toBeLessThan(strpos($applicationUpdate, 'maintenance_finish'))
        ->and(strpos($laravelUpdate, 'maintenance_begin'))
        ->toBeLessThan(strpos($laravelUpdate, 'database_backup'))
        ->and(strpos($laravelUpdate, "database_migrations_started='true'"))
        ->toBeLessThan(strpos($laravelUpdate, 'maintenance_finish'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function systemUpdateComponent(array $overrides = []): array
{
    return [
        ...[
            'key' => 'application',
            'currentVersion' => 'v1.0.0',
            'latestVersion' => 'v1.1.0',
            'currentReference' => str_repeat('a', 40),
            'latestReference' => str_repeat('b', 40),
            'releaseUrl' => 'https://github.com/webnetkz/crm369/releases/tag/v1.1.0',
            'status' => 'update_available',
            'updateAvailable' => true,
            'canUpdate' => true,
            'source' => 'github',
        ],
        ...$overrides,
    ];
}

function systemUpdateDatabaseIsNotReady(): void
{
    $readiness = Mockery::mock(SystemUpdateDatabaseReadiness::class);
    $readiness->shouldReceive('isReady')->andReturnFalse();

    app()->instance(SystemUpdateDatabaseReadiness::class, $readiness);
}
