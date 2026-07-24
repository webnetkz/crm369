<?php

use App\Support\SystemUpdates\SystemUpdateBridgeInspector;
use Tests\TestCase;

uses(TestCase::class);

test('secure root owned bridge metadata is accepted', function (int $permissions) {
    expect(app(SystemUpdateBridgeInspector::class)->metadataIsSecure(
        owner: 0,
        permissions: $permissions,
    ))->toBeTrue();
})->with([
    'root 0755' => 0100755,
    'root 0750' => 0100750,
    'root 0700' => 0100700,
]);

test('unsafe bridge metadata is rejected', function (int $owner, int $permissions) {
    expect(app(SystemUpdateBridgeInspector::class)->metadataIsSecure(
        owner: $owner,
        permissions: $permissions,
    ))->toBeFalse();
})->with([
    'not owned by root' => [1000, 0100750],
    'owner cannot execute' => [0, 0100640],
    'group writable' => [0, 0100770],
    'world writable' => [0, 0100752],
    'directory' => [0, 0040750],
    'symbolic link' => [0, 0120750],
]);

test('missing paths and symbolic links are unavailable bridges', function () {
    $inspector = app(SystemUpdateBridgeInspector::class);
    $missingPath = storage_path('framework/non-existent-system-updater');
    $linkPath = storage_path('framework/testing-system-updater-link');

    @unlink($linkPath);
    config(['system-updates.bridge_path' => $missingPath]);

    expect($inspector->isAvailable())->toBeFalse();

    symlink('/usr/bin/true', $linkPath);
    config(['system-updates.bridge_path' => $linkPath]);

    expect($inspector->isAvailable())->toBeFalse();

    @unlink($linkPath);
});
