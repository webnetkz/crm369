<?php

use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('portal title uses CRM369 by default', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<title>CRM369</title>', false)
        ->assertDontSee('<title>Laravel</title>', false);
});

test('portal title uses configured company name', function () {
    PortalSetting::query()->create([
        'id' => 1,
        'company_name' => 'Acme CRM',
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<title>Acme CRM</title>', false);
});

test('only super admin can open portal settings', function () {
    config(['admin.super_admin_email' => 'super@example.com']);
    PortalSetting::query()->updateOrCreate(
        ['id' => 1],
        ['default_language' => 'ru'],
    );

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $adminGroupUser = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $this->actingAs($adminGroupUser)
        ->get(route('settings.portal.edit'))
        ->assertForbidden();

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('settings.portal.edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Portal')
            ->has('settings.company_name')
            ->has('settings.logo_url')
            ->where('settings.default_language', 'ru')
        );
});

test('portal logo is used as the site icon when uploaded', function () {
    PortalSetting::query()->create([
        'id' => 1,
        'company_name' => 'CRM 369',
        'logo_path' => 'portal/logo.png',
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="/storage/portal/logo.png" sizes="any">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/storage/portal/logo.png">', false)
        ->assertDontSee('<link rel="icon" href="/favicon.ico" sizes="any">', false);
});

test('super admin can update portal company name and logo', function () {
    Storage::fake('public');
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $logo = UploadedFile::fake()->image('logo.png', 320, 120);

    $this->actingAs($superAdmin)
        ->post(route('settings.portal.update'), [
            'company_name' => 'CRM 369',
            'logo' => $logo,
            'default_language' => 'en',
        ])
        ->assertRedirect();

    $settings = PortalSetting::current();

    expect($settings->company_name)->toBe('CRM 369')
        ->and($settings->logo_path)->not->toBeNull()
        ->and($settings->default_language)->toBe('en');

    Storage::disk('public')->assertExists($settings->logo_path);

    $this->actingAs($superAdmin)
        ->get(route('settings.portal.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('settings.company_name', 'CRM 369')
            ->where('settings.logo_url', '/storage/'.$settings->logo_path)
            ->where('settings.default_language', 'en')
        );
});
