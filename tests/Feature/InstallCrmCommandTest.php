<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates the only initial super administrator', function () {
    config(['admin.super_admin_email' => 'owner@example.com']);

    $this->artisan('crm369:install', [
        '--name' => 'CRM Owner',
        '--email' => 'OWNER@example.com',
    ])
        ->expectsQuestion('Super-admin password', 'StrongPassword123!')
        ->expectsQuestion('Confirm super-admin password', 'StrongPassword123!')
        ->expectsOutput('Initial super administrator [owner@example.com] created successfully.')
        ->assertSuccessful();

    $user = User::query()->sole();

    expect(User::query()->count())->toBe(1)
        ->and($user->name)->toBe('CRM Owner')
        ->and($user->email)->toBe('owner@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($user->user_group_id)->toBeNull()
        ->and($user->isSuperAdmin())->toBeTrue()
        ->and($user->password)->not->toBe('StrongPassword123!')
        ->and(Hash::check('StrongPassword123!', $user->password))->toBeTrue();
});

test('it refuses to modify a CRM that already has a user', function () {
    config(['admin.super_admin_email' => 'owner@example.com']);

    $existingUser = User::factory()->create();

    $this->artisan('crm369:install', [
        '--name' => 'CRM Owner',
        '--email' => 'owner@example.com',
    ])
        ->expectsOutput('CRM369 already has a user account; the initial installer will not modify it.')
        ->assertFailed();

    expect(User::query()->sole()->is($existingUser))->toBeTrue();
});

test('it requires the account email to match the configured super administrator', function () {
    config(['admin.super_admin_email' => 'configured@example.com']);

    $this->artisan('crm369:install', [
        '--name' => 'CRM Owner',
        '--email' => 'different@example.com',
    ])
        ->expectsOutput('The email must match the configured SUPER_ADMIN_EMAIL value.')
        ->assertFailed();

    expect(User::query()->exists())->toBeFalse();
});
