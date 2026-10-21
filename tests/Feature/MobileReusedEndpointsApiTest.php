<?php

use App\Models\ApiAccessToken;
use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\Contact;
use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseArticle;
use App\Models\MenuItem;
use App\Models\MobileAccessToken;
use App\Models\MobileDevice;
use App\Models\PortalSetting;
use App\Models\ProjectTask;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use App\Models\Supplier;
use App\Models\TsdQrScan;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Warehouse;
use App\Models\WarehouseFloor;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use App\Notifications\SystemNotification;
use App\Support\WarehouseHierarchyManager;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Every production request resolves its bearer token in a fresh application, while the test application keeps
 * the resolved guards between requests. Resetting them after each request makes every request authenticate
 * (and pick its language) from its own bearer token.
 */
beforeEach(function (): void {
    Event::listen(RequestHandled::class, fn (): mixed => app('auth')->forgetGuards());
});

/**
 * Issue a mobile bearer token for one of the user's devices.
 */
function mobileReusedTokenFor(User $user, ?string $deviceId = null): string
{
    return MobileAccessToken::issueToUser($user, $deviceId ?? 'android-reused-'.$user->id)['plain_text_token'];
}

function mobileReusedSuperAdmin(): User
{
    config(['admin.super_admin_email' => 'mobile-reused-admin@example.com']);

    return User::factory()->create([
        'name' => 'Super',
        'last_name' => 'Admin',
        'email' => 'mobile-reused-admin@example.com',
        'language' => 'en',
    ]);
}

/**
 * @param  array<int, string>  $permissions
 * @param  array<int, string>  $configuredModules
 * @param  array<string, mixed>  $attributes
 */
function mobileReusedGroupUser(array $permissions = [], array $configuredModules = [], array $attributes = []): User
{
    $group = UserGroup::factory()->create([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules($permissions, $configuredModules),
    ]);

    return User::factory()->create([
        'user_group_id' => $group->id,
        'language' => 'en',
        ...$attributes,
    ]);
}

function mobileReusedDisableModules(string ...$modules): void
{
    PortalSetting::current()->update(['disabled_modules' => $modules]);
}

/**
 * @return array{warehouse: Warehouse, floor: WarehouseFloor, place: WarehousePlace, item: WarehouseItem}
 */
function mobileReusedWarehouseWithItem(User $creator): array
{
    $warehouse = app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload('Main warehouse', 1250.5),
        $creator,
    );
    $place = WarehousePlace::query()->where('name', 'A-01-1-001')->firstOrFail();
    $item = WarehouseItem::factory()->forPlace($place)->create([
        'name' => 'Bearing SKF 6204',
        'sku' => 'SKF-6204',
        'qr_code' => 'WI-SKF-6204',
        'quantity' => 12,
        'notes' => 'Keep dry',
    ]);

    return [
        'warehouse' => $warehouse,
        'floor' => $place->floor,
        'place' => $place,
        'item' => $item,
    ];
}

/**
 * @return array<string, mixed>
 */
function mobileReusedDirectoryPayload(string $name = 'Suppliers', string $slug = 'suppliers'): array
{
    return [
        'name' => $name,
        'slug' => $slug,
        'description' => 'Approved supplier list.',
        'csv_exchange_enabled' => true,
        'columns' => [
            ['label' => 'Company', 'key' => 'company', 'type' => 'text', 'is_required' => true],
            ['label' => 'Rating', 'key' => 'rating', 'type' => 'number', 'is_required' => false],
            ['label' => 'Approved', 'key' => 'approved', 'type' => 'boolean', 'is_required' => true],
            ['label' => 'Contract date', 'key' => 'contract_date', 'type' => 'date', 'is_required' => false],
            ['label' => 'Notes', 'key' => 'notes', 'type' => 'textarea', 'is_required' => false],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function mobileReusedSupplierPayload(string $name = 'Northwind Supply LLP'): array
{
    return [
        'name' => $name,
        'bin' => '000000000000',
        'contact_person' => 'John Doe',
        'email' => 'supply@example.com',
        'phone' => '+7 700 000 0000',
        'currency' => 'KZT',
        'payment_terms_days' => 15,
        'lead_time_days' => 5,
        'rating' => 4.8,
        'is_active' => true,
        'notes' => 'Main bearing supplier.',
    ];
}

/**
 * @return array<string, mixed>
 */
function mobileReusedPurchaseRequestPayload(?WarehousePlace $place = null): array
{
    return [
        'title' => 'Bearings for line 3',
        'needed_at' => now()->addWeek()->toDateString(),
        'budget_amount' => 1500,
        'currency' => 'KZT',
        'justification' => 'Stock for the production order.',
        'items' => [
            [
                'item_name' => 'Bearing SKF 6204',
                'sku' => 'SKF-6204',
                'unit' => 'pcs',
                'quantity' => 10,
                'target_unit_price' => 120,
                'warehouse_place_id' => $place?->id,
                'production_reference' => 'MO-2026-003',
            ],
        ],
    ];
}

/**
 * Every reused-controller route of the native mobile API with route parameters that do not need to exist.
 *
 * @return array<int, array{0: string, 1: string, 2: array<string, int|string>}>
 */
function mobileReusedRoutes(): array
{
    return [
        ['get', 'mobile.v1.profile.show', []],
        ['patch', 'mobile.v1.profile.update', []],
        ['patch', 'mobile.v1.profile.language.update', []],
        ['patch', 'mobile.v1.profile.appearance.update', []],
        ['put', 'mobile.v1.profile.password.update', []],
        ['get', 'mobile.v1.menu.index', []],
        ['get', 'mobile.v1.notifications.index', []],
        ['patch', 'mobile.v1.notifications.read-all', []],
        ['patch', 'mobile.v1.notifications.read', ['notification' => 'missing-notification']],
        ['delete', 'mobile.v1.contacts.destroy', ['contact' => 1]],
        ['get', 'mobile.v1.directories.index', []],
        ['get', 'mobile.v1.directories.show', ['referenceDirectory' => 1]],
        ['get', 'mobile.v1.directories.export', ['referenceDirectory' => 1]],
        ['get', 'mobile.v1.directories.template', ['referenceDirectory' => 1]],
        ['post', 'mobile.v1.directories.store', []],
        ['patch', 'mobile.v1.directories.update', ['referenceDirectory' => 1]],
        ['delete', 'mobile.v1.directories.destroy', ['referenceDirectory' => 1]],
        ['post', 'mobile.v1.directories.import', ['referenceDirectory' => 1]],
        ['post', 'mobile.v1.directories.records.store', ['referenceDirectory' => 1]],
        ['patch', 'mobile.v1.directories.records.update', ['referenceDirectory' => 1, 'referenceDirectoryRecord' => 1]],
        ['delete', 'mobile.v1.directories.records.destroy', ['referenceDirectory' => 1, 'referenceDirectoryRecord' => 1]],
        ['get', 'mobile.v1.knowledge-bases.index', []],
        ['get', 'mobile.v1.knowledge-bases.show', ['knowledgeBase' => 1]],
        ['get', 'mobile.v1.knowledge-bases.articles.show', ['knowledgeBase' => 1, 'knowledgeBaseArticle' => 1]],
        ['post', 'mobile.v1.knowledge-bases.store', []],
        ['patch', 'mobile.v1.knowledge-bases.update', ['knowledgeBase' => 1]],
        ['delete', 'mobile.v1.knowledge-bases.destroy', ['knowledgeBase' => 1]],
        ['post', 'mobile.v1.knowledge-bases.articles.store', ['knowledgeBase' => 1]],
        ['patch', 'mobile.v1.knowledge-bases.articles.update', ['knowledgeBase' => 1, 'knowledgeBaseArticle' => 1]],
        ['delete', 'mobile.v1.knowledge-bases.articles.destroy', ['knowledgeBase' => 1, 'knowledgeBaseArticle' => 1]],
        ['get', 'mobile.v1.procurement.index', []],
        ['post', 'mobile.v1.procurement.requests.store', []],
        ['patch', 'mobile.v1.procurement.requests.decision.update', ['purchaseRequest' => 1]],
        ['post', 'mobile.v1.procurement.suppliers.store', []],
        ['patch', 'mobile.v1.procurement.suppliers.update', ['supplier' => 1]],
        ['post', 'mobile.v1.procurement.quotations.store', []],
        ['post', 'mobile.v1.procurement.orders.store', []],
        ['patch', 'mobile.v1.procurement.orders.send', ['purchaseOrder' => 1]],
        ['post', 'mobile.v1.procurement.receipts.store', []],
        ['post', 'mobile.v1.procurement.returns.store', []],
        ['get', 'mobile.v1.warehouses.index', []],
        ['post', 'mobile.v1.warehouses.store', []],
        ['post', 'mobile.v1.warehouses.scan', []],
        ['get', 'mobile.v1.warehouses.qr.show', ['qrCode' => 'WH-UNKNOWN']],
        ['get', 'mobile.v1.warehouses.show', ['warehouse' => 1]],
        ['get', 'mobile.v1.warehouses.items', ['warehouse' => 1]],
        ['get', 'mobile.v1.warehouses.floors.show', ['warehouse' => 1, 'warehouseFloor' => 1]],
        ['get', 'mobile.v1.equipment.index', []],
        ['post', 'mobile.v1.equipment.store', []],
        ['get', 'mobile.v1.equipment.show', ['equipmentItem' => 1]],
        ['patch', 'mobile.v1.equipment.update', ['equipmentItem' => 1]],
        ['get', 'mobile.v1.tsd.index', []],
        ['post', 'mobile.v1.tsd.store', []],
        ['get', 'mobile.v1.calendar.events', []],
        ['get', 'mobile.v1.company-structure.index', []],
        ['get', 'mobile.v1.company-structure.show', ['user' => 1]],
    ];
}

test('reused mobile endpoints require a mobile session', function () {
    $routes = mobileReusedRoutes();

    expect($routes)->toHaveCount(56);

    foreach ($routes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters))->assertUnauthorized();
    }

    $admin = mobileReusedSuperAdmin();
    $apiToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $admin->id,
        'name' => 'Integration token',
        ...ApiAccessToken::tokenAttributes($apiToken),
        'permissions' => ApiAccessToken::availablePermissions(),
    ]);

    $this->withToken($apiToken)
        ->getJson(route('mobile.v1.profile.show'))
        ->assertUnauthorized();
});

test('the mobile profile endpoints read and update the signed in user', function () {
    Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);

    $user = User::factory()->create([
        'name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'position' => 'Storekeeper',
        'language' => 'en',
    ]);

    $this->withToken(mobileReusedTokenFor($user));

    $this->getJson(route('mobile.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'john.doe@example.com')
        ->assertJsonPath('data.position', 'Storekeeper')
        ->assertJsonPath('data.language', 'en')
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'last_name', 'middle_name', 'email', 'phone', 'position',
                'avatar', 'avatar_position_x', 'avatar_position_y', 'avatar_scale',
                'language', 'has_selected_language', 'background_color', 'background_image', 'background_blur',
                'email_verified_at', 'is_super_admin', 'is_active', 'deactivated_at', 'group', 'created_at', 'updated_at',
            ],
        ]);

    $this->patchJson(route('mobile.v1.profile.update'), [
        'name' => 'John',
        'last_name' => 'Doe',
        'middle_name' => 'Quincy',
        'email' => 'john.doe@example.com',
        'phone' => '8 700 000 00 00',
        'position' => 'Warehouse manager',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Profile updated.')
        ->assertJsonPath('data.middle_name', 'Quincy')
        ->assertJsonPath('data.phone', '+77000000000')
        ->assertJsonPath('data.position', 'Warehouse manager');

    $this->post(route('mobile.v1.profile.update'), [
        '_method' => 'PATCH',
        'name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'avatar' => UploadedFile::fake()->image('avatar.png', 256, 256),
        'avatar_position_x' => '40',
        'avatar_position_y' => '60',
        'avatar_scale' => '1.5',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.avatar_position_x', 40)
        ->assertJsonPath('data.avatar_position_y', 60)
        ->assertJsonPath('data.avatar_scale', 1.5)
        ->assertJsonPath('data.middle_name', null)
        ->assertJsonPath('data.avatar', fn (?string $avatar): bool => is_string($avatar) && str_starts_with($avatar, 'http'));

    Storage::disk('public')->assertExists($user->fresh()->avatar_path);

    $this->patchJson(route('mobile.v1.profile.update'), [
        'name' => '',
        'email' => 'not-an-email',
        'phone' => '12',
        'avatar_scale' => 7,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'avatar_scale']);

    $this->patchJson(route('mobile.v1.profile.language.update'), ['language' => 'de'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('language');

    $this->patchJson(route('mobile.v1.profile.language.update'), ['language' => 'ru'])
        ->assertOk()
        ->assertJsonPath('data.language', 'ru')
        ->assertJsonPath('data.has_selected_language', true)
        ->assertJsonPath('message', __('ui.profile.language_updated', [], 'en'));

    $this->patchJson(route('mobile.v1.profile.language.update'), ['language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.language', 'en')
        ->assertJsonPath('message', __('ui.profile.language_updated', [], 'ru'));

    $this->patchJson(route('mobile.v1.profile.appearance.update'), [
        'background_color' => '#1E293B',
        'background_blur' => 12,
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.settings.appearance_updated_success', [], 'en'))
        ->assertJsonPath('data.background_color', '#1E293B')
        ->assertJsonPath('data.background_blur', 12)
        ->assertJsonPath('data.background_image', null);

    $this->post(route('mobile.v1.profile.appearance.update'), [
        '_method' => 'PATCH',
        'background_color' => '#1E293B',
        'background_blur' => '20',
        'background_image' => UploadedFile::fake()->image('background.jpg', 1080, 1920),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.background_blur', 20)
        ->assertJsonPath('data.background_image', fn (?string $image): bool => is_string($image) && str_starts_with($image, 'http'));

    $backgroundImagePath = $user->fresh()->background_image_path;

    Storage::disk('public')->assertExists($backgroundImagePath);

    $this->patchJson(route('mobile.v1.profile.appearance.update'), [
        'background_color' => null,
        'background_blur' => 0,
        'remove_background_image' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.background_color', null)
        ->assertJsonPath('data.background_image', null);

    Storage::disk('public')->assertMissing($backgroundImagePath);

    $this->patchJson(route('mobile.v1.profile.appearance.update'), [
        'background_color' => 'blue',
        'background_blur' => 101,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['background_color', 'background_blur']);
});

test('changing the email through the mobile profile requires verifying the new address', function () {
    $user = User::factory()->create(['email' => 'old.address@example.com']);

    $this->withToken(mobileReusedTokenFor($user))
        ->patchJson(route('mobile.v1.profile.update'), [
            'name' => $user->name,
            'email' => 'new.address@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'new.address@example.com')
        ->assertJsonPath('data.email_verified_at', null);

    $this->getJson(route('mobile.v1.profile.show'))->assertUnauthorized();
});

test('the mobile password update keeps the current session and signs out the other devices', function () {
    $user = User::factory()->create(['language' => 'en']);
    $currentToken = mobileReusedTokenFor($user, 'android-current-device');
    $otherToken = MobileAccessToken::issueToUser($user, 'android-other-device')['plain_text_token'];
    $currentDevice = MobileDevice::factory()->create(['user_id' => $user->id, 'device_id' => 'android-current-device']);
    $otherDevice = MobileDevice::factory()->create(['user_id' => $user->id, 'device_id' => 'android-other-device']);

    $this->withToken($currentToken)
        ->putJson(route('mobile.v1.profile.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->putJson(route('mobile.v1.profile.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'another-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and(MobileAccessToken::query()->whereBelongsTo($user)->count())->toBe(2);

    $this->putJson(route('mobile.v1.profile.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertOk()
        ->assertExactJson(['message' => 'Password updated.']);

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue()
        ->and(MobileAccessToken::query()->whereBelongsTo($user)->pluck('device_id')->all())->toBe(['android-current-device'])
        ->and($currentDevice->fresh()->disabled_at)->toBeNull()
        ->and($otherDevice->fresh()->disabled_at)->not->toBeNull();

    $this->withToken($currentToken)
        ->getJson(route('mobile.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->withToken($otherToken)
        ->getJson(route('mobile.v1.profile.show'))
        ->assertUnauthorized();
});

test('the mobile endpoints ignore the user_id impersonation parameter of the integration api', function () {
    $admin = mobileReusedSuperAdmin();
    $otherUser = User::factory()->create(['language' => 'ru']);

    $otherUser->notify(new SystemNotification(title: 'Foreign notification', message: 'Visible only to its owner.'));

    $this->withToken(mobileReusedTokenFor($admin));

    $this->getJson(route('mobile.v1.profile.show', ['user_id' => $otherUser->id]))
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id);

    $this->patchJson(route('mobile.v1.profile.language.update', ['user_id' => $otherUser->id]), ['language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id);

    $this->getJson(route('mobile.v1.notifications.index', ['user_id' => $otherUser->id]))
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.subject_user_id', $admin->id);

    $this->patchJson(route('mobile.v1.notifications.read-all', ['user_id' => $otherUser->id]))
        ->assertOk()
        ->assertJsonPath('meta.subject_user_id', $admin->id);

    expect($otherUser->fresh()->language)->toBe('ru')
        ->and($otherUser->unreadNotifications()->count())->toBe(1);
});

test('the mobile menu endpoint lists the built in and custom menu items of the user', function () {
    $user = mobileReusedGroupUser();
    $otherUser = User::factory()->create();

    $sharedItem = MenuItem::query()->create([
        'type' => MenuItem::TYPE_CUSTOM,
        'user_id' => $otherUser->id,
        'is_global' => true,
        'title' => 'Company portal',
        'icon' => 'rocket',
        'url' => 'https://portal.example.com',
        'opens_in_new_tab' => true,
        'is_visible' => true,
        'sort_order' => 10,
    ]);
    MenuItem::query()->create([
        'type' => MenuItem::TYPE_CUSTOM,
        'user_id' => $user->id,
        'is_global' => false,
        'title' => 'Warehouse handbook',
        'icon' => 'book',
        'url' => '/knowledge-bases',
        'opens_in_new_tab' => false,
        'is_visible' => true,
        'sort_order' => 20,
    ]);
    MenuItem::query()->create([
        'type' => MenuItem::TYPE_CUSTOM,
        'user_id' => $otherUser->id,
        'is_global' => false,
        'title' => 'Private shortcut of another user',
        'icon' => 'book',
        'url' => '/projects',
        'opens_in_new_tab' => false,
        'is_visible' => true,
        'sort_order' => 30,
    ]);

    $user->update(['hidden_menu_item_ids' => [$sharedItem->id]]);
    mobileReusedDisableModules('news');

    $response = $this->withToken(mobileReusedTokenFor($user))
        ->getJson(route('mobile.v1.menu.index'))
        ->assertOk()
        ->assertJsonPath('can.share_with_all_users', false)
        ->assertJsonCount(2, 'data.custom_items')
        ->assertJsonPath('data.custom_items.0.title', 'Company portal')
        ->assertJsonPath('data.custom_items.0.is_global', true)
        ->assertJsonPath('data.custom_items.0.is_visible', false)
        ->assertJsonPath('data.custom_items.1.title', 'Warehouse handbook')
        ->assertJsonPath('data.custom_items.1.is_visible', true)
        ->assertJsonStructure([
            'can' => ['share_with_all_users'],
            'data' => [
                'built_in_items' => [['key', 'title', 'url', 'is_visible']],
                'custom_items' => [['id', 'key', 'type', 'title', 'icon', 'url', 'is_global', 'opens_in_new_tab', 'is_visible', 'sort_order']],
            ],
        ]);

    $builtInItems = collect($response->json('data.built_in_items'))->keyBy('key');

    expect($builtInItems->get('news')['is_visible'])->toBeFalse()
        ->and($builtInItems->get('dashboard')['is_visible'])->toBeTrue();
});

test('the mobile notification endpoints list and mark the notifications of the user', function () {
    $user = User::factory()->create(['language' => 'en']);
    $otherUser = User::factory()->create();

    $user->notify(new SystemNotification(
        title: 'Stock is running low',
        message: 'Bearing SKF 6204 has 2 pieces left.',
        actionUrl: '/warehouses',
        actionLabel: 'Open warehouses',
    ));
    $this->travel(1)->minute();
    $user->notify(new SystemNotification(
        title: 'New task assigned',
        message: 'Count the bearings on line 3.',
    ));
    $otherUser->notify(new SystemNotification(
        title: 'Foreign notification',
        message: 'Visible only to its owner.',
    ));

    $latestNotification = $user->notifications()->latest()->firstOrFail();
    $foreignNotification = $otherUser->notifications()->firstOrFail();

    $this->withToken(mobileReusedTokenFor($user));

    $this->getJson(route('mobile.v1.notifications.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'New task assigned')
        ->assertJsonPath('data.0.action_url', null)
        ->assertJsonPath('data.1.action_url', '/warehouses')
        ->assertJsonPath('data.1.action_label', 'Open warehouses')
        ->assertJsonPath('meta.status', 'all')
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.unread_count', 2)
        ->assertJsonPath('meta.subject_user_id', $user->id)
        ->assertJsonStructure([
            'data' => [['id', 'title', 'message', 'action_url', 'action_label', 'created_at', 'read_at', 'is_read']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'status', 'subject_user_id', 'unread_count'],
        ]);

    $this->getJson(route('mobile.v1.notifications.index', ['status' => 'unread', 'per_page' => 1, 'page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Stock is running low')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson(route('mobile.v1.notifications.index', ['status' => 'archived', 'per_page' => 500]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'per_page']);

    $this->patchJson(route('mobile.v1.notifications.read', $latestNotification->id))
        ->assertOk()
        ->assertJsonPath('message', __('ui.notifications.marked_as_read', [], 'en'))
        ->assertJsonPath('data.id', $latestNotification->id)
        ->assertJsonPath('data.is_read', true)
        ->assertJsonPath('meta.unread_count', 1);

    $this->patchJson(route('mobile.v1.notifications.read', $foreignNotification->id))
        ->assertNotFound();

    $this->getJson(route('mobile.v1.notifications.index', ['status' => 'read']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $latestNotification->id);

    $this->patchJson(route('mobile.v1.notifications.read-all'))
        ->assertOk()
        ->assertExactJson([
            'message' => __('ui.notifications.all_marked_as_read', [], 'en'),
            'meta' => [
                'subject_user_id' => $user->id,
                'unread_count' => 0,
            ],
        ]);

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($foreignNotification->fresh()->read_at)->toBeNull();
});

test('the mobile contact delete endpoint mirrors web contact type access', function () {
    $personContactsUser = mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS]);
    $person = Contact::factory()->person()->create();
    $company = Contact::factory()->company()->create();

    $this->withToken(mobileReusedTokenFor($personContactsUser));

    $this->deleteJson(route('mobile.v1.contacts.destroy', $company))->assertNotFound();

    $this->deleteJson(route('mobile.v1.contacts.destroy', $person))
        ->assertOk()
        ->assertExactJson([
            'message' => __('ui.contacts.deleted_success', [], 'en'),
            'data' => ['id' => $person->id],
        ]);

    $this->deleteJson(route('mobile.v1.contacts.destroy', $person))->assertNotFound();

    expect(Contact::query()->whereKey($person->id)->exists())->toBeFalse()
        ->and(Contact::query()->whereKey($company->id)->exists())->toBeTrue();

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser()))
        ->deleteJson(route('mobile.v1.contacts.destroy', $company))
        ->assertForbidden();

    mobileReusedDisableModules('contacts');

    $this->withToken(mobileReusedTokenFor(mobileReusedSuperAdmin()))
        ->deleteJson(route('mobile.v1.contacts.destroy', $company))
        ->assertNotFound();

    expect(Contact::query()->whereKey($company->id)->exists())->toBeTrue();
});

test('the mobile directory endpoints manage directories records and csv exchange', function () {
    $manager = mobileReusedGroupUser([UserGroup::PERMISSION_MANAGE_DIRECTORIES], ['directories']);

    $this->withToken(mobileReusedTokenFor($manager));

    $directoryId = $this->postJson(route('mobile.v1.directories.store'), mobileReusedDirectoryPayload())
        ->assertCreated()
        ->assertJsonPath('message', __('ui.directories.created_success', [], 'en'))
        ->assertJsonPath('data.slug', 'suppliers')
        ->assertJsonPath('data.csv_exchange_enabled', true)
        ->assertJsonPath('data.columns.3.key', 'contract_date')
        ->assertJsonPath('data.records_count', 0)
        ->assertJsonPath('data.creator.id', $manager->id)
        ->json('data.id');

    $this->postJson(route('mobile.v1.directories.store'), [
        ...mobileReusedDirectoryPayload(),
        'columns' => [],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug', 'columns']);

    $recordId = $this->postJson(route('mobile.v1.directories.records.store', $directoryId), [
        'values' => [
            'company' => 'Northwind Trade',
            'rating' => '4.5',
            'approved' => true,
            'contract_date' => '2026-01-15',
            'notes' => 'Primary supplier',
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.directories.record_created_success', [], 'en'))
        ->assertJsonPath('data.reference_directory_id', $directoryId)
        ->assertJsonPath('data.values.company', 'Northwind Trade')
        ->assertJsonPath('data.values.rating', 4.5)
        ->assertJsonPath('data.values.approved', true)
        ->assertJsonPath('data.values.contract_date', '2026-01-15')
        ->json('data.id');

    $this->postJson(route('mobile.v1.directories.records.store', $directoryId), [
        'values' => ['rating' => 'excellent', 'contract_date' => 'someday'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['values.company', 'values.rating', 'values.approved', 'values.contract_date']);

    $this->patchJson(route('mobile.v1.directories.records.update', [$directoryId, $recordId]), [
        'values' => [
            'company' => 'Northwind Trade LLP',
            'rating' => 5,
            'approved' => 'false',
            'contract_date' => null,
            'notes' => null,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.record_updated_success', [], 'en'))
        ->assertJsonPath('data.values.company', 'Northwind Trade LLP')
        ->assertJsonPath('data.values.rating', 5)
        ->assertJsonPath('data.values.approved', false)
        ->assertJsonPath('data.values.contract_date', null);

    $this->getJson(route('mobile.v1.directories.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $directoryId)
        ->assertJsonPath('data.0.records_count', 1)
        ->assertJsonMissingPath('data.0.records');

    $exportResponse = $this->get(route('mobile.v1.directories.export', ['referenceDirectory' => $directoryId, 'delimiter' => ';']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload();

    expect($exportResponse->streamedContent())
        ->toStartWith("\xEF\xBB\xBF")
        ->toContain('company;rating;approved;contract_date;notes')
        ->toContain('"Northwind Trade LLP";5;false;;');

    $templateResponse = $this->get(route('mobile.v1.directories.template', ['referenceDirectory' => $directoryId, 'delimiter' => 'tab']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload();

    expect($templateResponse->streamedContent())
        ->toContain("company\trating\tapproved\tcontract_date\tnotes")
        ->toContain("123\ttrue\t2026-01-15");

    $this->getJson(route('mobile.v1.directories.export', ['referenceDirectory' => $directoryId, 'delimiter' => ';;']))
        ->assertUnprocessable();

    $this->post(route('mobile.v1.directories.import', $directoryId), [
        'delimiter' => ';',
        'file' => UploadedFile::fake()->createWithContent('suppliers.csv', implode("\n", [
            'company;rating;approved;contract_date;notes',
            'Contoso Ltd;3;yes;2026-02-01;Backup supplier',
            'Fabrikam;;no;;',
        ])),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertExactJson([
            'message' => __('ui.directories.csv_import_success', ['count' => 2], 'en'),
            'data' => ['imported_count' => 2],
        ]);

    $this->post(route('mobile.v1.directories.import', $directoryId), [
        'delimiter' => ';',
        'file' => UploadedFile::fake()->createWithContent('suppliers.csv', "company;rating;approved\n;abc;maybe"),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->getJson(route('mobile.v1.directories.show', $directoryId))
        ->assertOk()
        ->assertJsonPath('data.id', $directoryId)
        ->assertJsonPath('data.records_count', 3)
        ->assertJsonCount(3, 'data.records')
        ->assertJsonPath('data.creator.id', $manager->id)
        ->assertJsonPath('data.records.0.creator.id', $manager->id);

    $this->patchJson(route('mobile.v1.directories.update', $directoryId), [
        ...mobileReusedDirectoryPayload('Approved suppliers', 'approved-suppliers'),
        'csv_exchange_enabled' => false,
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.updated_success', [], 'en'))
        ->assertJsonPath('data.slug', 'approved-suppliers')
        ->assertJsonPath('data.csv_exchange_enabled', false);

    $this->get(route('mobile.v1.directories.export', $directoryId))->assertForbidden();
    $this->get(route('mobile.v1.directories.template', $directoryId))->assertForbidden();

    $this->deleteJson(route('mobile.v1.directories.records.destroy', [$directoryId, $recordId]))
        ->assertOk()
        ->assertExactJson([
            'message' => __('ui.directories.record_deleted_success', [], 'en'),
            'data' => ['id' => $recordId],
        ]);

    $this->deleteJson(route('mobile.v1.directories.destroy', $directoryId))
        ->assertOk()
        ->assertExactJson([
            'message' => __('ui.directories.deleted_success', [], 'en'),
            'data' => ['id' => $directoryId],
        ]);

    expect(ReferenceDirectory::query()->whereKey($directoryId)->exists())->toBeFalse();
});

test('the mobile directory endpoints mirror the web directory permissions', function () {
    $directory = ReferenceDirectory::factory()->create();
    $record = $directory->records()->create(['values' => ['title' => 'Bolt M8', 'code' => 8]]);
    $otherDirectory = ReferenceDirectory::factory()->create();
    $foreignRecord = $otherDirectory->records()->create(['values' => ['title' => 'Nut M8', 'code' => 9]]);
    $readRoutes = [
        ['get', 'mobile.v1.directories.index', []],
        ['get', 'mobile.v1.directories.show', [$directory]],
        ['get', 'mobile.v1.directories.export', [$directory]],
        ['get', 'mobile.v1.directories.template', [$directory]],
    ];
    $manageRoutes = [
        ['post', 'mobile.v1.directories.store', []],
        ['patch', 'mobile.v1.directories.update', [$directory]],
        ['delete', 'mobile.v1.directories.destroy', [$directory]],
        ['post', 'mobile.v1.directories.import', [$directory]],
        ['post', 'mobile.v1.directories.records.store', [$directory]],
        ['patch', 'mobile.v1.directories.records.update', [$directory, $record]],
        ['delete', 'mobile.v1.directories.records.destroy', [$directory, $record]],
    ];

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_DIRECTORIES], ['directories'])));

    foreach ($readRoutes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters))->assertOk();
    }

    foreach ($manageRoutes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters))->assertForbidden();
    }

    $this->withToken(mobileReusedTokenFor(User::factory()->create()));

    foreach ([...$readRoutes, ...$manageRoutes] as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters))->assertForbidden();
    }

    $this->withToken(mobileReusedTokenFor(mobileReusedSuperAdmin()));

    $this->patchJson(route('mobile.v1.directories.records.update', [$directory, $foreignRecord]), [
        'values' => ['title' => 'Moved'],
    ])->assertNotFound();
    $this->deleteJson(route('mobile.v1.directories.records.destroy', [$directory, $foreignRecord]))->assertNotFound();

    mobileReusedDisableModules('directories');

    foreach ([...$readRoutes, ...$manageRoutes] as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters))->assertNotFound();
    }

    expect(ReferenceDirectoryRecord::query()->whereKey([$record->id, $foreignRecord->id])->count())->toBe(2);
});

test('the mobile knowledge base endpoints manage bases and articles', function () {
    Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);

    $readerGroup = UserGroup::factory()->create(['name' => 'Warehouse team']);
    $manager = mobileReusedGroupUser([UserGroup::PERMISSION_MANAGE_KNOWLEDGE_BASES], ['knowledge-bases']);

    $this->withToken(mobileReusedTokenFor($manager));

    $baseId = $this->postJson(route('mobile.v1.knowledge-bases.store'), [
        'title' => 'Warehouse handbook',
        'description' => 'Rules for the warehouse team.',
        'is_published' => true,
        'user_group_ids' => [$readerGroup->id],
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.knowledge.base_created_success', [], 'en'))
        ->assertJsonPath('data.slug', 'warehouse-handbook')
        ->assertJsonPath('data.article_count', 0)
        ->assertJsonPath('data.groups.0.id', $readerGroup->id)
        ->json('data.id');

    $this->postJson(route('mobile.v1.knowledge-bases.store'), [
        'title' => 'Warehouse handbook',
        'user_group_ids' => [999999],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug', 'user_group_ids.0']);

    $articleResponse = $this->post(route('mobile.v1.knowledge-bases.articles.store', $baseId), [
        'title' => 'Receiving goods',
        'excerpt' => 'How to accept a delivery.',
        'sort_order' => '1',
        'is_published' => '1',
        'blocks' => [
            ['type' => 'heading', 'content' => 'Receiving goods', 'heading_level' => '2'],
            ['type' => 'paragraph', 'content' => 'Check the <strong>delivery note</strong> before <script>x</script>signing.'],
            ['type' => 'list', 'items' => ['Count the boxes', 'Scan every QR code'], 'ordered' => '1'],
            ['type' => 'image', 'caption' => 'Receiving zone', 'image_file' => UploadedFile::fake()->image('zone.png', 640, 480)],
        ],
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.knowledge.article_created_success', [], 'en'))
        ->assertJsonPath('data.knowledge_base_id', $baseId)
        ->assertJsonPath('data.slug', 'receiving-goods')
        ->assertJsonPath('data.blocks.0.heading_level', 2)
        ->assertJsonPath('data.blocks.1.content', 'Check the <strong>delivery note</strong> before signing.')
        ->assertJsonPath('data.blocks.2.ordered', true)
        ->assertJsonPath('data.blocks.3.type', 'image')
        ->assertJsonPath('data.blocks.3.caption', 'Receiving zone');

    $articleId = $articleResponse->json('data.id');
    $imagePath = $articleResponse->json('data.blocks.3.image_path');

    Storage::disk('public')->assertExists($imagePath);

    expect($articleResponse->json('data.blocks.3.image_url'))->toStartWith('http');

    $childArticleId = $this->postJson(route('mobile.v1.knowledge-bases.articles.store', $baseId), [
        'parent_id' => $articleId,
        'title' => 'Damaged boxes',
        'sort_order' => 2,
        'is_published' => false,
        'blocks' => [
            ['type' => 'paragraph', 'content' => 'Photograph the damage and notify the supplier.'],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $articleId)
        ->assertJsonPath('data.is_published', false)
        ->json('data.id');

    $this->postJson(route('mobile.v1.knowledge-bases.articles.store', $baseId), [
        'title' => 'Empty article',
        'blocks' => [
            ['type' => 'paragraph', 'content' => '  '],
            ['type' => 'image'],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['blocks.0.content', 'blocks.1.image_file']);

    $this->patchJson(route('mobile.v1.knowledge-bases.articles.update', [$baseId, $articleId]), [
        'title' => 'Receiving goods',
        'excerpt' => 'How to accept a delivery step by step.',
        'sort_order' => 1,
        'is_published' => true,
        'blocks' => [
            ['type' => 'paragraph', 'content' => 'Check the delivery note.'],
            ['type' => 'image', 'caption' => 'Receiving zone, gate 2', 'image_path' => $imagePath],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.knowledge.article_updated_success', [], 'en'))
        ->assertJsonPath('data.excerpt', 'How to accept a delivery step by step.')
        ->assertJsonPath('data.blocks.1.image_path', $imagePath)
        ->assertJsonPath('data.blocks.1.caption', 'Receiving zone, gate 2');

    Storage::disk('public')->assertExists($imagePath);

    $this->patchJson(route('mobile.v1.knowledge-bases.articles.update', [$baseId, $articleId]), [
        'title' => 'Receiving goods',
        'parent_id' => $articleId,
        'blocks' => [['type' => 'paragraph', 'content' => 'Loop']],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    $this->getJson(route('mobile.v1.knowledge-bases.index'))
        ->assertOk()
        ->assertJsonPath('data.bases.0.id', $baseId)
        ->assertJsonPath('data.bases.0.article_count', 2)
        ->assertJsonPath('data.activeBase.id', $baseId)
        ->assertJsonPath('data.activeBase.articles.0.id', $articleId)
        ->assertJsonPath('data.activeBase.articles.0.children.0.id', $childArticleId)
        ->assertJsonPath('data.activeArticle.id', $articleId)
        ->assertJsonPath('data.can.manage', true)
        ->assertJsonPath('groups', fn (array $groups): bool => collect($groups)->pluck('id')->contains($readerGroup->id));

    $this->getJson(route('mobile.v1.knowledge-bases.show', $baseId))
        ->assertOk()
        ->assertJsonPath('data.activeBase.id', $baseId)
        ->assertJsonPath('data.activeArticle.id', $articleId);

    $this->getJson(route('mobile.v1.knowledge-bases.articles.show', [$baseId, $childArticleId]))
        ->assertOk()
        ->assertJsonPath('data.activeArticle.id', $childArticleId)
        ->assertJsonPath('data.activeArticle.parent_id', $articleId)
        ->assertJsonPath('data.activeArticle.blocks.0.type', 'paragraph');

    $this->patchJson(route('mobile.v1.knowledge-bases.update', $baseId), [
        'title' => 'Warehouse handbook 2026',
        'slug' => 'warehouse-handbook',
        'description' => null,
        'is_published' => false,
        'user_group_ids' => [],
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.knowledge.base_updated_success', [], 'en'))
        ->assertJsonPath('data.title', 'Warehouse handbook 2026')
        ->assertJsonPath('data.is_published', false)
        ->assertJsonPath('data.groups', [])
        ->assertJsonPath('data.article_count', 2);

    $this->deleteJson(route('mobile.v1.knowledge-bases.articles.destroy', [$baseId, $childArticleId]))
        ->assertOk()
        ->assertExactJson([
            'message' => __('ui.knowledge.article_deleted_success', [], 'en'),
            'data' => ['id' => $childArticleId],
        ]);

    $this->deleteJson(route('mobile.v1.knowledge-bases.destroy', $baseId))
        ->assertOk()
        ->assertExactJson([
            'message' => __('ui.knowledge.base_deleted_success', [], 'en'),
            'data' => ['id' => $baseId],
        ]);

    expect(KnowledgeBase::query()->whereKey($baseId)->exists())->toBeFalse();
});

test('the mobile knowledge base endpoints mirror web visibility and manage permissions', function () {
    $reader = mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_KNOWLEDGE_BASES], ['knowledge-bases']);
    $otherGroup = UserGroup::factory()->create();

    $groupBase = KnowledgeBase::factory()->create(['title' => 'A group handbook']);
    $groupBase->groups()->sync([$reader->user_group_id]);
    $publicBase = KnowledgeBase::factory()->create(['title' => 'B public handbook']);
    $hiddenBase = KnowledgeBase::factory()->create(['title' => 'C hidden handbook']);
    $hiddenBase->groups()->sync([$otherGroup->id]);
    KnowledgeBase::factory()->create(['title' => 'D unpublished handbook', 'is_published' => false]);

    $publishedArticle = KnowledgeBaseArticle::factory()->create(['knowledge_base_id' => $groupBase->id, 'sort_order' => 1]);
    $draftArticle = KnowledgeBaseArticle::factory()->create(['knowledge_base_id' => $groupBase->id, 'is_published' => false]);
    $publicArticle = KnowledgeBaseArticle::factory()->create(['knowledge_base_id' => $publicBase->id]);
    $hiddenArticle = KnowledgeBaseArticle::factory()->create(['knowledge_base_id' => $hiddenBase->id]);

    $this->withToken(mobileReusedTokenFor($reader));

    $this->getJson(route('mobile.v1.knowledge-bases.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data.bases')
        ->assertJsonPath('data.bases.0.id', $groupBase->id)
        ->assertJsonPath('data.bases.0.article_count', 1)
        ->assertJsonPath('data.bases.1.id', $publicBase->id)
        ->assertJsonPath('data.activeBase.id', $groupBase->id)
        ->assertJsonCount(1, 'data.activeBase.articles')
        ->assertJsonPath('data.activeArticle.id', $publishedArticle->id)
        ->assertJsonPath('data.can.manage', false)
        ->assertJsonPath('groups', []);

    $this->getJson(route('mobile.v1.knowledge-bases.show', $publicBase))
        ->assertOk()
        ->assertJsonPath('data.activeArticle.id', $publicArticle->id);
    $this->getJson(route('mobile.v1.knowledge-bases.show', $hiddenBase))->assertNotFound();
    $this->getJson(route('mobile.v1.knowledge-bases.articles.show', [$hiddenBase, $hiddenArticle]))->assertNotFound();
    $this->getJson(route('mobile.v1.knowledge-bases.articles.show', [$groupBase, $draftArticle]))->assertNotFound();
    $this->getJson(route('mobile.v1.knowledge-bases.articles.show', [$groupBase, $publicArticle]))->assertNotFound();

    foreach ([
        ['post', 'mobile.v1.knowledge-bases.store', []],
        ['patch', 'mobile.v1.knowledge-bases.update', [$groupBase]],
        ['delete', 'mobile.v1.knowledge-bases.destroy', [$groupBase]],
        ['post', 'mobile.v1.knowledge-bases.articles.store', [$groupBase]],
        ['patch', 'mobile.v1.knowledge-bases.articles.update', [$groupBase, $publishedArticle]],
        ['delete', 'mobile.v1.knowledge-bases.articles.destroy', [$groupBase, $publishedArticle]],
    ] as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters), ['title' => 'Forbidden'])->assertForbidden();
    }

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([], ['knowledge-bases'])))
        ->getJson(route('mobile.v1.knowledge-bases.index'))
        ->assertForbidden();

    mobileReusedDisableModules('knowledge-bases');

    $this->withToken(mobileReusedTokenFor(mobileReusedSuperAdmin()));

    $this->getJson(route('mobile.v1.knowledge-bases.index'))->assertNotFound();
    $this->postJson(route('mobile.v1.knowledge-bases.store'), ['title' => 'Hidden'])->assertNotFound();
    $this->deleteJson(route('mobile.v1.knowledge-bases.destroy', $groupBase))->assertNotFound();

    expect(KnowledgeBase::query()->whereKey($groupBase->id)->exists())->toBeTrue();
});

test('the mobile procurement endpoints run the full purchasing cycle', function () {
    $admin = mobileReusedSuperAdmin();
    ['place' => $place] = mobileReusedWarehouseWithItem($admin);

    $this->withToken(mobileReusedTokenFor($admin));

    $emptyIndex = $this->getJson(route('mobile.v1.procurement.index'))
        ->assertOk()
        ->assertJsonPath('data.summary.pending_approvals', 0)
        ->assertJsonPath('data.can.manage', true)
        ->assertJsonPath('data.warehousePlaces.0.path', 'Main warehouse / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001');

    expect($emptyIndex->getContent())
        ->toContain('"month_receipts":{}')
        ->toContain('"potential_savings":{}');

    $supplierId = $this->postJson(route('mobile.v1.procurement.suppliers.store'), mobileReusedSupplierPayload())
        ->assertCreated()
        ->assertJsonPath('message', __('ui.procurement.supplier_created_success', [], 'en'))
        ->assertJsonPath('data.name', 'Northwind Supply LLP')
        ->assertJsonPath('data.created_by_user_id', $admin->id)
        ->json('data.id');

    $this->patchJson(route('mobile.v1.procurement.suppliers.update', $supplierId), [
        ...mobileReusedSupplierPayload(),
        'rating' => 4.9,
        'lead_time_days' => 4,
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.procurement.supplier_updated_success', [], 'en'))
        ->assertJsonPath('data.lead_time_days', 4);

    $this->postJson(route('mobile.v1.procurement.requests.store'), [
        ...mobileReusedPurchaseRequestPayload($place),
        'budget_amount' => 100,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('budget_amount');

    $requestResponse = $this->postJson(route('mobile.v1.procurement.requests.store'), mobileReusedPurchaseRequestPayload($place))
        ->assertCreated()
        ->assertJsonPath('message', __('ui.procurement.created_request_success', [], 'en'))
        ->assertJsonPath('data.status', PurchaseRequest::STATUS_PENDING_APPROVAL)
        ->assertJsonPath('data.requested_by_user_id', $admin->id)
        ->assertJsonPath('data.items.0.item_name', 'Bearing SKF 6204');

    $requestId = $requestResponse->json('data.id');
    $requestItemId = $requestResponse->json('data.items.0.id');

    $this->patchJson(route('mobile.v1.procurement.requests.decision.update', $requestId), ['decision' => 'reject'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rejection_reason');

    $this->patchJson(route('mobile.v1.procurement.requests.decision.update', $requestId), ['decision' => 'approve'])
        ->assertOk()
        ->assertJsonPath('message', __('ui.procurement.decision_success', [], 'en'))
        ->assertJsonPath('data.status', PurchaseRequest::STATUS_APPROVED)
        ->assertJsonPath('data.approved_by_user_id', $admin->id);

    $this->patchJson(route('mobile.v1.procurement.requests.decision.update', $requestId), ['decision' => 'approve'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('decision');

    $quotationId = $this->postJson(route('mobile.v1.procurement.quotations.store'), [
        'purchase_request_item_id' => $requestItemId,
        'supplier_id' => $supplierId,
        'unit_price' => 100,
        'currency' => 'KZT',
        'tax_percent' => 12,
        'delivery_cost' => 20,
        'quoted_at' => now()->toDateString(),
        'valid_until' => now()->addMonth()->toDateString(),
        'lead_time_days' => 5,
        'notes' => 'Price valid for one month.',
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.procurement.quotation_saved_success', [], 'en'))
        ->assertJsonPath('data.supplier_id', $supplierId)
        ->json('data.id');

    $orderResponse = $this->postJson(route('mobile.v1.procurement.orders.store'), [
        'purchase_request_id' => $requestId,
        'supplier_id' => $supplierId,
        'quotation_ids' => [$quotationId],
        'ordered_at' => now()->toDateString(),
        'expected_at' => now()->addDays(5)->toDateString(),
        'notes' => 'Deliver to gate 2.',
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.procurement.order_created_success', [], 'en'))
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_DRAFT)
        ->assertJsonPath('data.supplier.id', $supplierId)
        ->assertJsonPath('data.items.0.quantity', 10);

    $orderId = $orderResponse->json('data.id');
    $orderItemId = $orderResponse->json('data.items.0.id');

    $this->patchJson(route('mobile.v1.procurement.orders.send', $orderId))
        ->assertOk()
        ->assertJsonPath('message', __('ui.procurement.order_sent_success', [], 'en'))
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_SENT)
        ->assertJsonPath('data.sent_by_user_id', $admin->id);

    $receiptResponse = $this->postJson(route('mobile.v1.procurement.receipts.store'), [
        'purchase_order_id' => $orderId,
        'received_at' => now()->toDateString(),
        'external_reference' => 'INV-001',
        'notes' => 'First partial delivery.',
        'items' => [
            ['purchase_order_item_id' => $orderItemId, 'warehouse_place_id' => $place->id, 'quantity' => 6],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.procurement.receipt_created_success', [], 'en'))
        ->assertJsonPath('data.purchase_order.status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->assertJsonPath('data.items.0.quantity', 6)
        ->assertJsonPath('data.items.0.warehouse_item.sku', 'SKF-6204');

    $this->postJson(route('mobile.v1.procurement.receipts.store'), [
        'purchase_order_id' => $orderId,
        'received_at' => now()->toDateString(),
        'items' => [
            ['purchase_order_item_id' => $orderItemId, 'warehouse_place_id' => $place->id, 'quantity' => 50],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.quantity');

    $this->postJson(route('mobile.v1.procurement.returns.store'), [
        'purchase_order_id' => $orderId,
        'returned_at' => now()->toDateString(),
        'reason' => 'Two bearings are damaged.',
        'items' => [
            ['goods_receipt_item_id' => $receiptResponse->json('data.items.0.id'), 'quantity' => 2],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.procurement.return_created_success', [], 'en'))
        ->assertJsonPath('data.items.0.quantity', 2);

    $this->getJson(route('mobile.v1.procurement.index'))
        ->assertOk()
        ->assertJsonPath('data.summary.active_orders', 1)
        ->assertJsonPath('data.summary.month_receipts.KZT', 600)
        ->assertJsonPath('data.suppliers.0.order_count', 1)
        ->assertJsonPath('data.purchaseRequests.0.status', PurchaseRequest::STATUS_PARTIALLY_RECEIVED)
        ->assertJsonPath('data.purchaseOrders.0.items.0.received_quantity', 6)
        ->assertJsonPath('data.purchaseOrders.0.items.0.returned_quantity', 2)
        ->assertJsonPath('data.goodsReceipts.0.items.0.returnable_quantity', 4)
        ->assertJsonPath('data.purchaseReturns.0.items.0.quantity', 2)
        ->assertJsonStructure([
            'data' => [
                'summary' => ['pending_approvals', 'active_orders', 'month_receipts', 'potential_savings'],
                'suppliers', 'purchaseRequests', 'purchaseOrders', 'goodsReceipts', 'purchaseReturns',
                'warehousePlaces', 'warehouseItems', 'companyContacts',
                'can' => ['manage', 'approve_budget', 'manage_orders', 'receive_orders', 'return_goods'],
            ],
        ]);
});

test('the mobile procurement endpoints mirror the web procurement permissions', function () {
    $supplier = Supplier::factory()->create();
    $pendingRequest = PurchaseRequest::factory()->create(['status' => PurchaseRequest::STATUS_PENDING_APPROVAL]);
    $purchaseOrder = PurchaseOrder::factory()->create();
    $restrictedRoutes = [
        ['patch', 'mobile.v1.procurement.requests.decision.update', [$pendingRequest]],
        ['post', 'mobile.v1.procurement.suppliers.store', []],
        ['patch', 'mobile.v1.procurement.suppliers.update', [$supplier]],
        ['post', 'mobile.v1.procurement.quotations.store', []],
        ['post', 'mobile.v1.procurement.orders.store', []],
        ['patch', 'mobile.v1.procurement.orders.send', [$purchaseOrder]],
        ['post', 'mobile.v1.procurement.receipts.store', []],
        ['post', 'mobile.v1.procurement.returns.store', []],
    ];

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_PROCUREMENT], ['procurement'])));

    $this->getJson(route('mobile.v1.procurement.index'))
        ->assertOk()
        ->assertJsonPath('data.can', [
            'manage' => false,
            'approve_budget' => false,
            'manage_orders' => false,
            'receive_orders' => false,
            'return_goods' => false,
        ]);

    $this->postJson(route('mobile.v1.procurement.requests.store'), mobileReusedPurchaseRequestPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', PurchaseRequest::STATUS_PENDING_APPROVAL);

    foreach ($restrictedRoutes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters))->assertForbidden();
    }

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([UserGroup::PERMISSION_APPROVE_PROCUREMENT_BUDGET], ['procurement'])))
        ->patchJson(route('mobile.v1.procurement.requests.decision.update', $pendingRequest), [
            'decision' => 'reject',
            'rejection_reason' => 'Budget is frozen until next quarter.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseRequest::STATUS_REJECTED)
        ->assertJsonPath('data.rejection_reason', 'Budget is frozen until next quarter.');

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([], ['procurement'])));

    $this->getJson(route('mobile.v1.procurement.index'))->assertForbidden();
    $this->postJson(route('mobile.v1.procurement.requests.store'), mobileReusedPurchaseRequestPayload())->assertForbidden();

    mobileReusedDisableModules('procurement');

    $this->withToken(mobileReusedTokenFor(mobileReusedSuperAdmin()));

    $this->getJson(route('mobile.v1.procurement.index'))->assertNotFound();

    foreach ($restrictedRoutes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters))->assertNotFound();
    }
});

test('each restricted mobile procurement endpoint opens with its own web permission', function () {
    $supplier = Supplier::factory()->create();
    $pendingRequest = PurchaseRequest::factory()->create(['status' => PurchaseRequest::STATUS_PENDING_APPROVAL]);
    $draftOrderWithoutItems = PurchaseOrder::factory()->create();

    foreach ([
        [UserGroup::PERMISSION_APPROVE_PROCUREMENT_BUDGET, 'patch', 'mobile.v1.procurement.requests.decision.update', [$pendingRequest]],
        [UserGroup::PERMISSION_MANAGE_PROCUREMENT, 'post', 'mobile.v1.procurement.suppliers.store', []],
        [UserGroup::PERMISSION_MANAGE_PROCUREMENT, 'patch', 'mobile.v1.procurement.suppliers.update', [$supplier]],
        [UserGroup::PERMISSION_MANAGE_PROCUREMENT, 'post', 'mobile.v1.procurement.quotations.store', []],
        [UserGroup::PERMISSION_MANAGE_PROCUREMENT_ORDERS, 'post', 'mobile.v1.procurement.orders.store', []],
        [UserGroup::PERMISSION_MANAGE_PROCUREMENT_ORDERS, 'patch', 'mobile.v1.procurement.orders.send', [$draftOrderWithoutItems]],
        [UserGroup::PERMISSION_RECEIVE_PROCUREMENT_ORDERS, 'post', 'mobile.v1.procurement.receipts.store', []],
        [UserGroup::PERMISSION_RETURN_PROCUREMENT_GOODS, 'post', 'mobile.v1.procurement.returns.store', []],
    ] as [$permission, $method, $routeName, $parameters]) {
        $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([$permission], ['procurement'])))
            ->json($method, route($routeName, $parameters))
            ->assertUnprocessable();
    }

    expect($pendingRequest->fresh()->status)->toBe(PurchaseRequest::STATUS_PENDING_APPROVAL)
        ->and($draftOrderWithoutItems->fresh()->status)->toBe(PurchaseOrder::STATUS_DRAFT);
});

test('the mobile warehouse endpoints list create and inspect warehouses', function () {
    $user = mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_WAREHOUSES], ['warehouses']);

    $this->withToken(mobileReusedTokenFor($user));

    $createResponse = $this->postJson(route('mobile.v1.warehouses.store'), warehouseHierarchyPayload('Main warehouse', 1250.5))
        ->assertCreated()
        ->assertJsonPath('message', __('ui.warehouses.created_success', [], 'en'))
        ->assertJsonPath('data.name', 'Main warehouse')
        ->assertJsonPath('data.area_sqm', 1250.5)
        ->assertJsonPath('data.row_count', 2)
        ->assertJsonPath('data.place_count', 3)
        ->assertJsonPath('data.created_by.id', $user->id)
        ->assertJsonPath('data.qr_code', fn (string $qrCode): bool => str_starts_with($qrCode, 'WH-'));

    $this->postJson(route('mobile.v1.warehouses.store'), ['name' => 'Empty warehouse', 'area_sqm' => -1, 'rows' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['area_sqm', 'rows']);

    $warehouse = Warehouse::query()->findOrFail($createResponse->json('data.id'));
    $place = WarehousePlace::query()->where('name', 'A-01-1-001')->firstOrFail();
    $floor = $place->floor;
    $item = WarehouseItem::factory()->forPlace($place)->create([
        'name' => 'Bearing SKF 6204',
        'sku' => 'SKF-6204',
        'qr_code' => 'WI-SKF-6204',
        'quantity' => 12,
        'notes' => 'Keep dry',
    ]);
    WarehouseItem::factory()->forPlace($place)->create(['name' => 'Cable reel', 'qr_code' => 'WI-CABLE-0001']);

    $this->getJson(route('mobile.v1.warehouses.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $warehouse->id)
        ->assertJsonPath('data.0.rows.0.columns.0.floors.0.places.0.id', $place->id)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.total_area_sqm', 1250.5)
        ->assertJsonPath('meta.place_count', 3);

    $this->getJson(route('mobile.v1.warehouses.show', $warehouse))
        ->assertOk()
        ->assertJsonPath('data.id', $warehouse->id)
        ->assertJsonPath('data.rows.0.name', 'Ряд A')
        ->assertJsonPath('data.rows.0.columns.0.floors.0.id', $floor->id);

    $this->getJson(route('mobile.v1.warehouses.items', ['warehouse' => $warehouse, 'per_page' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $item->id)
        ->assertJsonPath('data.0.location.path', 'Main warehouse / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson(route('mobile.v1.warehouses.floors.show', [$warehouse, $floor]))
        ->assertOk()
        ->assertJsonPath('data.id', $floor->id)
        ->assertJsonPath('data.places.0.id', $place->id)
        ->assertJsonPath('data.places.0.item_count', 2)
        ->assertJsonPath('data.places.0.items.0.id', $item->id)
        ->assertJsonPath('data.places.0.items.0.quantity', 12);

    $foreignFloor = WarehouseFloor::factory()->create();

    $this->getJson(route('mobile.v1.warehouses.floors.show', [$warehouse, $foreignFloor]))->assertNotFound();

    $this->getJson(route('mobile.v1.warehouses.qr.show', 'wi-skf-6204'))
        ->assertOk()
        ->assertJsonPath('data.matched', true)
        ->assertJsonPath('data.entity_type', 'item')
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonPath('data.normalized_qr_code', 'WI-SKF-6204')
        ->assertJsonPath('data.warehouse.id', $warehouse->id);

    $this->getJson(route('mobile.v1.warehouses.qr.show', $floor->qr_code))
        ->assertOk()
        ->assertJsonPath('data.entity_type', 'floor')
        ->assertJsonPath('data.id', $floor->id)
        ->assertJsonPath('data.details.item_count', 2)
        ->assertJsonCount(2, 'data.contents');

    $this->getJson(route('mobile.v1.warehouses.qr.show', 'UNKNOWN-QR'))->assertNotFound();

    $this->postJson(route('mobile.v1.warehouses.scan'), [
        'qr_code' => ' '.$place->qr_code.' ',
        'device_name' => 'Pixel 9',
        'location' => 'Gate 2',
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.warehouses.scan_found_success', [], 'en'))
        ->assertJsonPath('data.entity_type', 'place')
        ->assertJsonPath('data.title', 'A-01-1-001')
        ->assertJsonPath('scan.qr_code', $place->qr_code)
        ->assertJsonPath('scan.source', TsdQrScan::SOURCE_API)
        ->assertJsonPath('scan.context', 'warehouse_lookup')
        ->assertJsonPath('scan.device_name', 'Pixel 9')
        ->assertJsonPath('scan.actor.id', $user->id);

    $this->postJson(route('mobile.v1.warehouses.scan'), [
        'qr_code' => 'UNKNOWN-QR',
        'context' => 'inventory',
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.warehouses.scan_not_found', [], 'en'))
        ->assertJsonPath('data', null)
        ->assertJsonPath('scan.context', 'inventory');

    $this->postJson(route('mobile.v1.warehouses.scan'), ['qr_code' => '   '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('qr_code');

    expect(TsdQrScan::query()->where('scanned_by_user_id', $user->id)->count())->toBe(2);
});

test('the mobile warehouse endpoints mirror the web warehouse permissions', function () {
    $admin = mobileReusedSuperAdmin();
    ['warehouse' => $warehouse, 'floor' => $floor, 'item' => $item] = mobileReusedWarehouseWithItem($admin);
    $routes = [
        ['get', 'mobile.v1.warehouses.index', []],
        ['post', 'mobile.v1.warehouses.store', []],
        ['post', 'mobile.v1.warehouses.scan', []],
        ['get', 'mobile.v1.warehouses.qr.show', [$item->qr_code]],
        ['get', 'mobile.v1.warehouses.show', [$warehouse]],
        ['get', 'mobile.v1.warehouses.items', [$warehouse]],
        ['get', 'mobile.v1.warehouses.floors.show', [$warehouse, $floor]],
    ];

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_TSD], ['warehouses', 'tsd'])));

    foreach ($routes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters), ['qr_code' => $item->qr_code])->assertForbidden();
    }

    mobileReusedDisableModules('warehouses');

    $this->withToken(mobileReusedTokenFor($admin));

    foreach ($routes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters), ['qr_code' => $item->qr_code])->assertNotFound();
    }

    expect(TsdQrScan::query()->count())->toBe(0);
});

test('the mobile equipment endpoints list create show and update equipment', function () {
    $user = mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_EQUIPMENT], ['equipment']);
    $responsibleUser = User::factory()->create(['name' => 'Ivan', 'last_name' => 'Petrov', 'email' => 'ivan.petrov@example.com']);
    $holder = User::factory()->create(['name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane.doe@example.com']);

    $this->withToken(mobileReusedTokenFor($user));

    $this->postJson(route('mobile.v1.equipment.store'), [
        'name' => 'Zebra TC52 scanner',
        'responsible_user_id' => $responsibleUser->id,
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.equipment.created_success', [], 'en'))
        ->assertJsonPath('data.status', EquipmentItem::STATUS_ON_BALANCE)
        ->assertJsonPath('data.qr_code', fn (string $qrCode): bool => str_starts_with($qrCode, 'EQ-') && strlen($qrCode) === 29);

    $itemId = $this->postJson(route('mobile.v1.equipment.store'), [
        'name' => 'Dell Latitude 5440',
        'qr_code' => ' EQ-LAPTOP-0001 ',
        'status' => EquipmentItem::STATUS_ISSUED,
        'issued_to_user_id' => $holder->id,
        'responsible_user_id' => $responsibleUser->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.qr_code', 'EQ-LAPTOP-0001')
        ->assertJsonPath('data.status', EquipmentItem::STATUS_ISSUED)
        ->assertJsonPath('data.status_label', __('ui.equipment.statuses.issued', [], 'en'))
        ->assertJsonPath('data.issued_to_user.id', $holder->id)
        ->assertJsonPath('data.responsible_user.email', 'ivan.petrov@example.com')
        ->assertJsonPath('data.created_by.id', $user->id)
        ->json('data.id');

    $this->postJson(route('mobile.v1.equipment.store'), [
        'name' => 'Duplicate laptop',
        'qr_code' => 'EQ-LAPTOP-0001',
        'status' => 'lost',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qr_code', 'status']);

    $this->postJson(route('mobile.v1.equipment.store'), [
        'name' => 'Issued without holder',
        'status' => EquipmentItem::STATUS_ISSUED,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('issued_to_user_id');

    $this->getJson(route('mobile.v1.equipment.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonCount(5, 'status_options')
        ->assertJsonPath('status_options.0.value', EquipmentItem::STATUS_ON_BALANCE)
        ->assertJsonStructure([
            'data' => [['id', 'name', 'qr_code', 'status', 'status_label', 'issued_to_user', 'responsible_user', 'created_by', 'updated_by', 'created_at', 'updated_at']],
            'status_options' => [['value', 'label', 'description']],
        ]);

    $this->getJson(route('mobile.v1.equipment.show', $itemId))
        ->assertOk()
        ->assertJsonPath('data.id', $itemId)
        ->assertJsonPath('data.qr_code', 'EQ-LAPTOP-0001');

    $this->getJson(route('mobile.v1.warehouses.qr.show', 'EQ-LAPTOP-0001'))->assertNotFound();

    $this->patchJson(route('mobile.v1.equipment.update', $itemId), [
        'name' => 'Dell Latitude 5440 (repair)',
        'qr_code' => 'EQ-LAPTOP-0001',
        'status' => EquipmentItem::STATUS_REPAIR,
        'responsible_user_id' => $responsibleUser->id,
    ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.equipment.updated_success', [], 'en'))
        ->assertJsonPath('data.qr_code', 'EQ-LAPTOP-0001')
        ->assertJsonPath('data.status', EquipmentItem::STATUS_REPAIR)
        ->assertJsonPath('data.issued_to_user', null)
        ->assertJsonPath('data.updated_by.id', $user->id);

    $this->patchJson(route('mobile.v1.equipment.update', $itemId), [
        'name' => 'Dell Latitude 5440',
        'status' => EquipmentItem::STATUS_ON_BALANCE,
    ])
        ->assertOk()
        ->assertJsonPath('data.responsible_user', null)
        ->assertJsonPath('data.qr_code', fn (string $qrCode): bool => $qrCode !== 'EQ-LAPTOP-0001' && str_starts_with($qrCode, 'EQ-'));

    expect(EquipmentItemHistory::query()
        ->where('equipment_item_id', $itemId)
        ->where('source', EquipmentItemHistory::SOURCE_API)
        ->count())->toBe(3);
});

test('the mobile equipment endpoints mirror the web equipment permissions', function () {
    $equipmentItem = EquipmentItem::factory()->create();
    $routes = [
        ['get', 'mobile.v1.equipment.index', []],
        ['post', 'mobile.v1.equipment.store', []],
        ['get', 'mobile.v1.equipment.show', [$equipmentItem]],
        ['patch', 'mobile.v1.equipment.update', [$equipmentItem]],
    ];

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([], ['equipment'])));

    foreach ($routes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters), ['name' => 'Forbidden'])->assertForbidden();
    }

    mobileReusedDisableModules('equipment');

    $this->withToken(mobileReusedTokenFor(mobileReusedSuperAdmin()));

    foreach ($routes as [$method, $routeName, $parameters]) {
        $this->json($method, route($routeName, $parameters), ['name' => 'Hidden'])->assertNotFound();
    }

    expect($equipmentItem->fresh()->name)->not->toBe('Hidden');
});

test('the mobile tsd endpoints list and record scans', function () {
    $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));

    $user = mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_TSD, UserGroup::PERMISSION_ACCESS_WAREHOUSES], ['tsd', 'warehouses']);
    ['item' => $item] = mobileReusedWarehouseWithItem($user);

    $this->withToken(mobileReusedTokenFor($user));

    $this->postJson(route('mobile.v1.tsd.store'), [
        'qr_code' => $item->qr_code,
        'device_name' => 'Pixel 9',
        'location' => 'Gate 2',
        'context' => 'acceptance',
        'payload' => ['batch' => 'LOT-08', 'count' => 3],
        'scanned_at' => '2026-10-21T09:15:00Z',
    ])
        ->assertCreated()
        ->assertJsonPath('data.scanned_at', '2026-10-21T09:15:00.000000Z')
        ->assertJsonPath('message', __('ui.tsd.created_success', [], 'en'))
        ->assertJsonPath('data.qr_code', 'WI-SKF-6204')
        ->assertJsonPath('data.source', TsdQrScan::SOURCE_API)
        ->assertJsonPath('data.source_label', __('ui.tsd.source_api', [], 'en'))
        ->assertJsonPath('data.payload.batch', 'LOT-08')
        ->assertJsonPath('data.actor.id', $user->id)
        ->assertJsonPath('data.webhook', null)
        ->assertJsonPath('resolved.entity_type', 'item')
        ->assertJsonPath('resolved.location.path', 'Main warehouse / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001');

    $this->postJson(route('mobile.v1.tsd.store'), [
        'qr_code' => $item->qr_code,
        'scanned_at' => '2026-10-21T14:15:00+05:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.scanned_at', '2026-10-21T09:15:00.000000Z');

    $this->postJson(route('mobile.v1.tsd.store'), ['qr_code' => 'ORD-2026-000145 | cell-a3 | lot-08'])
        ->assertCreated()
        ->assertJsonPath('data.normalized_qr_code', 'ORD-2026-000145|CELL-A3|LOT-08')
        ->assertJsonPath('resolved', null);

    $this->postJson(route('mobile.v1.tsd.store'), ['qr_code' => '', 'payload' => 'text'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('qr_code');

    $this->getJson(route('mobile.v1.tsd.index', ['per_page' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.qr_code', 'ORD-2026-000145 | cell-a3 | lot-08')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 3);

    $this->getJson(route('mobile.v1.tsd.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.per_page', 25);
});

test('the mobile tsd scan resolves warehouse positions only for users who can open warehouses', function () {
    $admin = mobileReusedSuperAdmin();
    ['item' => $item] = mobileReusedWarehouseWithItem($admin);

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_TSD], ['tsd', 'warehouses'])))
        ->postJson(route('mobile.v1.tsd.store'), ['qr_code' => $item->qr_code])
        ->assertCreated()
        ->assertJsonPath('data.qr_code', $item->qr_code)
        ->assertJsonPath('resolved', null);

    mobileReusedDisableModules('warehouses');

    $this->withToken(mobileReusedTokenFor($admin))
        ->postJson(route('mobile.v1.tsd.store'), ['qr_code' => $item->qr_code])
        ->assertCreated()
        ->assertJsonPath('resolved', null);

    expect(TsdQrScan::query()->count())->toBe(2);
});

test('the mobile tsd endpoints mirror the web tsd permissions', function () {
    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_WAREHOUSES], ['tsd', 'warehouses'])));

    $this->getJson(route('mobile.v1.tsd.index'))->assertForbidden();
    $this->postJson(route('mobile.v1.tsd.store'), ['qr_code' => 'WH-0001'])->assertForbidden();

    mobileReusedDisableModules('tsd');

    $this->withToken(mobileReusedTokenFor(mobileReusedSuperAdmin()));

    $this->getJson(route('mobile.v1.tsd.index'))->assertNotFound();
    $this->postJson(route('mobile.v1.tsd.store'), ['qr_code' => 'WH-0001'])->assertNotFound();

    expect(TsdQrScan::query()->count())->toBe(0);
});

test('the mobile calendar endpoint returns the visible task deadlines and conferences', function () {
    $this->travelTo(now()->setDate(2026, 7, 19)->setTime(9, 0));

    $user = User::factory()->create(['name' => 'John', 'last_name' => 'Doe', 'language' => 'en']);
    $colleague = User::factory()->create(['name' => 'Jane', 'last_name' => 'Doe']);

    $task = ProjectTask::factory()->standalone()->create([
        'title' => 'Prepare the quarterly stock report',
        'creator_user_id' => $colleague->id,
        'assignee_user_id' => $user->id,
        'importance' => ProjectTask::IMPORTANCE_HIGH,
        'due_at' => '2026-07-21 11:30:00',
    ]);
    ProjectTask::factory()->standalone()->create([
        'title' => 'Hidden task',
        'creator_user_id' => $colleague->id,
        'assignee_user_id' => $colleague->id,
        'due_at' => '2026-07-22 11:30:00',
    ]);
    $conference = Conference::factory()->create([
        'title' => 'Stock report review',
        'created_by_user_id' => $colleague->id,
        'starts_at' => '2026-07-23 14:00:00',
    ]);
    ConferenceInvitation::factory()->create([
        'conference_id' => $conference->id,
        'user_id' => $user->id,
        'invited_by_user_id' => $colleague->id,
    ]);
    Conference::factory()->create([
        'title' => 'Hidden conference',
        'created_by_user_id' => $colleague->id,
        'starts_at' => '2026-07-24 14:00:00',
    ]);

    $this->withToken(mobileReusedTokenFor($user));

    $this->getJson(route('mobile.v1.calendar.events', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 'task:'.$task->id)
        ->assertJsonPath('data.0.source_id', $task->id)
        ->assertJsonPath('data.0.type', 'task')
        ->assertJsonPath('data.0.meta.assignee.id', $user->id)
        ->assertJsonPath('data.0.meta.importance', ProjectTask::IMPORTANCE_HIGH)
        ->assertJsonPath('data.1.id', 'conference:'.$conference->id)
        ->assertJsonPath('data.1.type', 'conference')
        ->assertJsonPath('data.1.meta.organizer.id', $colleague->id)
        ->assertJsonPath('data.1.meta.is_organizer', false)
        ->assertJsonPath('meta', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'types' => ['task', 'conference'],
            'count' => 2,
        ]);

    $this->getJson(route('mobile.v1.calendar.events', ['from' => '2026-07-01', 'to' => '2026-07-31', 'types' => 'conference']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'conference')
        ->assertJsonPath('meta.types', ['conference']);

    $this->getJson(route('mobile.v1.calendar.events', ['from' => '2026-07-01', 'to' => '2026-07-31', 'types' => ['task']]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'task');

    $this->getJson(route('mobile.v1.calendar.events'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.from', '2026-06-29')
        ->assertJsonPath('meta.to', '2026-08-02');

    $this->getJson(route('mobile.v1.calendar.events', ['from' => '2026-07-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');

    $this->getJson(route('mobile.v1.calendar.events', ['from' => '2026-07-31', 'to' => '2026-07-01', 'types' => ['meeting']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'types.0']);

    $this->getJson(route('mobile.v1.calendar.events', ['from' => '2026-01-01', 'to' => '2027-03-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

test('the mobile calendar endpoint mirrors the web calendar permissions', function () {
    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([], ['calendar'])))
        ->getJson(route('mobile.v1.calendar.events'))
        ->assertForbidden();

    mobileReusedDisableModules('calendar');

    $this->withToken(mobileReusedTokenFor(mobileReusedSuperAdmin()))
        ->getJson(route('mobile.v1.calendar.events'))
        ->assertNotFound();
});

test('the mobile company structure endpoints return the hierarchy and a user node', function () {
    $director = User::factory()->create(['name' => 'Jane', 'last_name' => 'Doe', 'position' => 'Managing director']);
    $manager = User::factory()->create(['name' => 'John', 'last_name' => 'Doe', 'position' => 'Warehouse manager', 'manager_id' => $director->id]);
    $storekeeper = User::factory()->create(['name' => 'Alex', 'last_name' => 'Smith', 'position' => 'Storekeeper', 'manager_id' => $manager->id]);
    $viewer = mobileReusedGroupUser([UserGroup::PERMISSION_ACCESS_COMPANY_STRUCTURE], ['company-structure'], [
        'name' => 'Sam',
        'last_name' => 'Brown',
        'manager_id' => $director->id,
    ]);

    $this->withToken(mobileReusedTokenFor($viewer));

    $this->getJson(route('mobile.v1.company-structure.index'))
        ->assertOk()
        ->assertJsonPath('stats.total_users', 4)
        ->assertJsonPath('stats.root_users', 1)
        ->assertJsonPath('stats.managers', 2)
        ->assertJsonPath('roots.0.id', $director->id)
        ->assertJsonPath('roots.0.full_name', 'Jane Doe')
        ->assertJsonPath('roots.0.subordinates_count', 2)
        ->assertJsonPath('roots.0.children.0.id', $manager->id)
        ->assertJsonPath('roots.0.children.0.children.0.id', $storekeeper->id)
        ->assertJsonPath('roots.0.children.1.id', $viewer->id)
        ->assertJsonMissingPath('data');

    $this->getJson(route('mobile.v1.company-structure.show', $manager))
        ->assertOk()
        ->assertJsonPath('data.id', $manager->id)
        ->assertJsonPath('data.position', 'Warehouse manager')
        ->assertJsonPath('data.manager_id', $director->id)
        ->assertJsonPath('data.manager.full_name', 'Jane Doe')
        ->assertJsonPath('data.subordinates.0.id', $storekeeper->id)
        ->assertJsonPath('data.children.0.id', $storekeeper->id)
        ->assertJsonPath('ancestors.0.id', $director->id);

    $this->getJson(route('mobile.v1.company-structure.show', ['user' => 999999]))->assertNotFound();
});

test('the mobile company structure endpoints mirror the web company structure permissions', function () {
    $colleague = User::factory()->create();

    $this->withToken(mobileReusedTokenFor(mobileReusedGroupUser([], ['company-structure'])));

    $this->getJson(route('mobile.v1.company-structure.index'))->assertForbidden();
    $this->getJson(route('mobile.v1.company-structure.show', $colleague))->assertForbidden();

    mobileReusedDisableModules('company-structure');

    $this->withToken(mobileReusedTokenFor(mobileReusedSuperAdmin()));

    $this->getJson(route('mobile.v1.company-structure.index'))->assertNotFound();
    $this->getJson(route('mobile.v1.company-structure.show', $colleague))->assertNotFound();
});
