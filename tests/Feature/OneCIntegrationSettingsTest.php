<?php

use App\Models\OneCIntegration;
use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function oneCIntegrationPayload(OneCIntegration $integration, array $overrides = []): array
{
    return array_replace_recursive([
        'name' => $integration->name,
        'product' => $integration->product,
        'transport' => $integration->transport,
        'is_enabled' => $integration->is_enabled,
        'base_url' => $integration->base_url,
        'api_path' => $integration->api_path,
        'auth_type' => $integration->auth_type,
        'username' => $integration->username,
        'password' => '',
        'token' => '',
        'verify_tls' => $integration->verify_tls,
        'connect_timeout_seconds' => $integration->connect_timeout_seconds,
        'request_timeout_seconds' => $integration->request_timeout_seconds,
        'import_enabled' => $integration->import_enabled,
        'export_enabled' => $integration->export_enabled,
        'schedule_enabled' => $integration->schedule_enabled,
        'sync_interval_minutes' => $integration->sync_interval_minutes,
        'sync_window_start' => $integration->sync_window_start,
        'sync_window_end' => $integration->sync_window_end,
        'batch_size' => $integration->batch_size,
        'default_sync_mode' => $integration->default_sync_mode,
        'conflict_strategy' => $integration->conflict_strategy,
        'stop_on_error' => $integration->stop_on_error,
        'dry_run' => $integration->dry_run,
        'entities' => $integration->normalizedEntities(),
    ], $overrides);
}

test('only the super admin can access one c integration settings', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
    $administrator = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->get(route('settings.one-c.edit'))
        ->assertRedirect(route('login'));

    $this->actingAs($administrator)
        ->get(route('settings.one-c.edit'))
        ->assertForbidden();

    $this->actingAs($superAdmin)
        ->get(route('settings.one-c.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/OneC')
            ->has('integrations', 0)
            ->has('productOptions', 3)
            ->has('transportOptions', 2)
            ->has('entityDefinitions', count(OneCIntegration::entityDefinitions()))
        );
});

test('super admin can create an isolated one c database connection', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->post(route('settings.one-c.store'), [
            'name' => '  Основная ERP  ',
            'product' => OneCIntegration::PRODUCT_ERP,
            'transport' => OneCIntegration::TRANSPORT_ODATA,
        ])
        ->assertRedirect();

    $integration = OneCIntegration::query()->sole();

    expect($integration->name)->toBe('Основная ERP')
        ->and($integration->product)->toBe(OneCIntegration::PRODUCT_ERP)
        ->and($integration->is_enabled)->toBeFalse()
        ->and($integration->updated_by_user_id)->toBe($superAdmin->id)
        ->and($integration->normalizedEntities())
        ->each(fn ($entity) => $entity->enabled->toBeFalse());
});

test('connection credentials are encrypted and never returned to inertia', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $integration = OneCIntegration::factory()->create([
        'password' => null,
        'entities' => OneCIntegration::normalizeEntities([]),
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('settings.one-c.update', $integration), oneCIntegrationPayload($integration, [
            'name' => 'Бухгалтерия № 1',
            'product' => OneCIntegration::PRODUCT_ACCOUNTING,
            'is_enabled' => true,
            'password' => 'very-secret-password',
            'entities' => [
                'counterparties' => [
                    'enabled' => true,
                    'direction' => OneCIntegration::DIRECTION_IMPORT,
                    'source_of_truth' => OneCIntegration::SOURCE_ONE_C,
                ],
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $integration->refresh();
    $storedPassword = DB::table((new OneCIntegration)->getTable())
        ->where('id', $integration->id)
        ->value('password');

    expect($integration->password)->toBe('very-secret-password')
        ->and($storedPassword)->not->toBe('very-secret-password')
        ->and($integration->is_enabled)->toBeTrue()
        ->and($integration->enabled_at)->not->toBeNull();

    $this->actingAs($superAdmin)
        ->get(route('settings.one-c.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('integrations.0.password_configured', true)
            ->where('integrations.0.token_configured', false)
            ->missing('integrations.0.password')
            ->missing('integrations.0.token')
        );

    $this->actingAs($superAdmin)
        ->patch(route('settings.one-c.update', $integration), oneCIntegrationPayload($integration, [
            'name' => 'Бухгалтерия без смены пароля',
            'password' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($integration->fresh()->password)->toBe('very-secret-password');
});

test('an incomplete one c connection cannot be enabled', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $integration = OneCIntegration::factory()->create([
        'base_url' => null,
        'username' => null,
        'password' => null,
        'entities' => OneCIntegration::normalizeEntities([]),
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('settings.one-c.update', $integration), oneCIntegrationPayload($integration, [
            'is_enabled' => true,
            'base_url' => '',
            'username' => '',
            'password' => '',
            'import_enabled' => false,
            'export_enabled' => false,
            'entities' => OneCIntegration::normalizeEntities([]),
        ]))
        ->assertInvalid(['base_url', 'import_enabled', 'entities', 'username', 'password']);

    expect($integration->fresh()->is_enabled)->toBeFalse();
});

test('super admin can test a saved one c odata connection', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    Http::preventStrayRequests();
    Http::fake([
        'https://one-c.example.test/odata/standard.odata/$metadata' => Http::response(
            '<?xml version="1.0"?><edmx:Edmx />',
            200,
            ['Content-Type' => 'application/xml'],
        ),
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $integration = OneCIntegration::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('settings.one-c.test', $integration))
        ->assertRedirect();

    $integration->refresh();

    expect($integration->last_test_succeeded)->toBeTrue()
        ->and($integration->last_tested_at)->not->toBeNull()
        ->and($integration->last_test_message)->toContain('HTTP 200')
        ->and($integration->last_test_duration_ms)->toBeInt();

    Http::assertSent(fn (Request $request): bool => $request->url()
        === 'https://one-c.example.test/odata/standard.odata/$metadata'
        && $request->hasHeader(
            'Authorization',
            'Basic '.base64_encode('crm-exchange:secret-password'),
        ));
});

test('failed authentication is recorded without exposing the response payload', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    Http::preventStrayRequests();
    Http::fake([
        '*' => Http::response('internal 1C diagnostic with sensitive data', 401),
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $integration = OneCIntegration::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('settings.one-c.test', $integration))
        ->assertRedirect();

    $integration->refresh();

    expect($integration->last_test_succeeded)->toBeFalse()
        ->and($integration->last_test_message)->toContain('401')
        ->and($integration->last_test_message)->not->toContain('sensitive data');
});

test('globally disabled one c module blocks settings and connection tests', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    PortalSetting::current()->update([
        'disabled_modules' => ['one-c'],
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $integration = OneCIntegration::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('settings.one-c.edit'))
        ->assertNotFound();

    $this->actingAs($superAdmin)
        ->post(route('settings.one-c.test', $integration))
        ->assertNotFound();

    expect($integration->fresh()->last_tested_at)->toBeNull();
});

test('an enabled one c connection must be disabled before deletion', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $integration = OneCIntegration::factory()->enabled()->create();

    $this->actingAs($superAdmin)
        ->delete(route('settings.one-c.destroy', $integration))
        ->assertInvalid(['delete']);

    $this->assertModelExists($integration);

    $integration->update(['is_enabled' => false]);

    $this->actingAs($superAdmin)
        ->delete(route('settings.one-c.destroy', $integration))
        ->assertRedirect(route('settings.one-c.edit'));

    $this->assertModelMissing($integration);
});
