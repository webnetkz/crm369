<?php

use App\Support\SystemUpdates\SystemUpdateProgressReader;
use Tests\TestCase;

uses(TestCase::class);

test('progress reader accepts only a valid run uuid and sanitizes bounded updater state', function () {
    $directory = sys_get_temp_dir().'/crm369-progress-'.bin2hex(random_bytes(5));
    mkdir($directory, 0700, true);
    config(['system-updates.progress_directory' => $directory]);
    $uuid = '019fa2c4-4b29-7e85-9a02-5e57ea58b644';

    file_put_contents($directory.'/'.$uuid.'.json', json_encode([
        'status' => 'running',
        'progress' => 112,
        'stage' => "migrations\x00",
        'message' => 'Applying migrations',
        'started_at' => '2026-07-24T10:00:00+00:00',
        'finished_at' => null,
    ], JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/'.$uuid.'.steps', json_encode([
        'at' => '2026-07-24T10:01:00+00:00',
        'progress' => 70,
        'stage' => 'migrations',
        'message' => 'Applying migrations',
    ], JSON_THROW_ON_ERROR).PHP_EOL);

    $progress = app(SystemUpdateProgressReader::class)->read($uuid);

    expect($progress)
        ->not->toBeNull()
        ->and($progress['status'])->toBe('running')
        ->and($progress['progress'])->toBe(100)
        ->and($progress['stage'])->toBe('migrations')
        ->and($progress['steps'])->toHaveCount(1)
        ->and(app(SystemUpdateProgressReader::class)->read('../../etc/passwd'))->toBeNull();

    unlink($directory.'/'.$uuid.'.json');
    unlink($directory.'/'.$uuid.'.steps');
    rmdir($directory);
});

test('progress reader rejects malformed or unknown state', function () {
    $directory = sys_get_temp_dir().'/crm369-progress-'.bin2hex(random_bytes(5));
    mkdir($directory, 0700, true);
    config(['system-updates.progress_directory' => $directory]);
    $uuid = '019fa2c4-4b29-7e85-9a02-5e57ea58b644';
    file_put_contents($directory.'/'.$uuid.'.json', '{"status":"executing"}');

    expect(app(SystemUpdateProgressReader::class)->read($uuid))->toBeNull();

    unlink($directory.'/'.$uuid.'.json');
    rmdir($directory);
});
