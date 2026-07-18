<?php

use App\Models\User;
use App\Support\LogEntryReader;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('log settings are visible only to the configured super admin', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $directory = fakeLogDirectory([
        'laravel.log' => [
            'content' => '[2026-07-01 09:00:00] local.INFO: Portal booted successfully',
            'mtime' => 1_783_000_000,
        ],
    ]);

    try {
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $superAdmin = User::factory()->create([
            'email' => 'super@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('settings.logs.edit'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('settings.logs.edit'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Logs')
                ->has('files', 1)
                ->has('entries.data', 1)
                ->where('entries.data.0.summary', 'Portal booted successfully')
                ->where('entries.meta.per_page', 100)
            );
    } finally {
        File::deleteDirectory($directory);
    }
});

test('super admin can review combined log entries from every log file', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $directory = fakeLogDirectory([
        'laravel.log' => [
            'content' => implode(PHP_EOL, [
                '[2026-07-01 09:15:00] local.WARNING: Cache warming took longer than expected',
                'Stack trace line',
                '[2026-07-01 10:45:00] local.ERROR: Queue worker stopped unexpectedly',
            ]),
            'mtime' => 1_783_000_100,
        ],
        'laravel-2026-06-30.log' => [
            'content' => '[2026-06-30 18:30:00] local.INFO: Previous day entry',
            'mtime' => 1_782_999_900,
        ],
    ]);

    try {
        $superAdmin = User::factory()->create([
            'email' => 'super@example.com',
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('settings.logs.edit'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Logs')
                ->has('files', 2)
                ->has('entries.data', 3)
                ->where('entries.data.0.level', 'error')
                ->where('entries.data.0.file_name', 'laravel.log')
                ->where('entries.data.1.level', 'warning')
                ->where('entries.data.2.file_name', 'laravel-2026-06-30.log')
                ->where('files.0.name', 'laravel.log')
                ->where('files.0.entries_count', 2)
                ->where('files.1.entries_count', 1)
            );

        expect($response->inertiaProps('entries.data.1.content'))
            ->toContain('Stack trace line');
    } finally {
        File::deleteDirectory($directory);
    }
});

test('log settings paginate entries by one hundred records per page', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $entries = [];

    foreach (range(1, 205) as $index) {
        $entries[] = sprintf(
            '[2026-07-01 12:%02d:%02d] local.INFO: Entry %03d',
            intdiv($index, 60),
            $index % 60,
            $index,
        );
    }

    $directory = fakeLogDirectory([
        'laravel.log' => [
            'content' => implode(PHP_EOL, $entries),
            'mtime' => 1_783_000_200,
        ],
    ]);

    try {
        $superAdmin = User::factory()->create([
            'email' => 'super@example.com',
        ]);

        $firstPage = $this->actingAs($superAdmin)
            ->get(route('settings.logs.edit'))
            ->assertSuccessful();

        $secondPage = $this->actingAs($superAdmin)
            ->get(route('settings.logs.edit', ['page' => 2]))
            ->assertSuccessful();

        $thirdPage = $this->actingAs($superAdmin)
            ->get(route('settings.logs.edit', ['page' => 3]))
            ->assertSuccessful();

        expect($firstPage->inertiaProps('entries.meta.per_page'))->toBe(100)
            ->and(count($firstPage->inertiaProps('entries.data')))->toBe(100)
            ->and($firstPage->inertiaProps('entries.data.0.summary'))->toBe('Entry 205')
            ->and($firstPage->inertiaProps('entries.data.99.summary'))->toBe('Entry 106')
            ->and(count($secondPage->inertiaProps('entries.data')))->toBe(100)
            ->and($secondPage->inertiaProps('entries.data.0.summary'))->toBe('Entry 105')
            ->and($secondPage->inertiaProps('entries.data.99.summary'))->toBe('Entry 006')
            ->and(count($thirdPage->inertiaProps('entries.data')))->toBe(5)
            ->and($thirdPage->inertiaProps('entries.data.0.summary'))->toBe('Entry 005')
            ->and($thirdPage->inertiaProps('entries.data.4.summary'))->toBe('Entry 001')
            ->and($thirdPage->inertiaProps('entries.meta.total'))->toBe(205);
    } finally {
        File::deleteDirectory($directory);
    }
});

test('large log files are paginated with bounded memory usage', function () {
    $directory = fakeLogDirectory([]);
    $path = $directory.'/browser.log';
    $stream = fopen($path, 'wb');

    if ($stream === false) {
        throw new RuntimeException('Unable to create the large log fixture.');
    }

    $message = str_repeat('x', 16 * 1024);

    try {
        foreach (range(1, 1000) as $index) {
            fwrite(
                $stream,
                sprintf(
                    '[2026-07-01 12:00:00] local.INFO: Entry %04d %s%s',
                    $index,
                    $message,
                    PHP_EOL,
                ),
            );
        }
    } finally {
        fclose($stream);
    }

    try {
        memory_reset_peak_usage();
        $memoryBeforeReading = memory_get_usage();

        $logData = app(LogEntryReader::class)->paginate(1, 100);

        $peakMemoryIncrease = memory_get_peak_usage() - $memoryBeforeReading;

        expect($logData['total'])->toBe(1000)
            ->and($logData['entries'])->toHaveCount(100)
            ->and($logData['entries'][0]['summary'])->toStartWith('Entry 1000')
            ->and($logData['entries'][99]['summary'])->toStartWith('Entry 0901')
            ->and($peakMemoryIncrease)->toBeLessThan(8 * 1024 * 1024);
    } finally {
        File::deleteDirectory($directory);
    }
});

test('oversized individual log entries are truncated', function () {
    $directory = fakeLogDirectory([
        'browser.log' => [
            'content' => '[2026-07-01 12:00:00] local.ERROR: '.str_repeat('x', 128 * 1024),
            'mtime' => 1_783_000_300,
        ],
    ]);

    try {
        $logData = app(LogEntryReader::class)->paginate(1, 100);

        expect($logData['total'])->toBe(1)
            ->and($logData['entries'])->toHaveCount(1)
            ->and(mb_strlen($logData['entries'][0]['content'], '8bit'))->toBeLessThan(66 * 1024)
            ->and($logData['entries'][0]['content'])->toEndWith(
                '[Entry content truncated after 64 KB to protect memory.]',
            );
    } finally {
        File::deleteDirectory($directory);
    }
});

/**
 * @param  array<string, array{content: string, mtime: int}>  $files
 */
function fakeLogDirectory(array $files): string
{
    $directory = sys_get_temp_dir().'/crm369-logs-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($directory);

    foreach ($files as $name => $file) {
        $path = $directory.'/'.$name;

        File::put($path, $file['content']);
        touch($path, $file['mtime']);
    }

    app()->instance(LogEntryReader::class, new LogEntryReader($directory));

    return $directory;
}
