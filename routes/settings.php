<?php

use App\Http\Controllers\Settings\ApiController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\MenuController;
use App\Http\Controllers\Settings\MessengerIntegrationController;
use App\Http\Controllers\Settings\ModuleController;
use App\Http\Controllers\Settings\PortalController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\UserGroupController;
use App\Http\Controllers\Settings\UserGroupPermissionController;
use App\Http\Controllers\Settings\UserImpersonationController;
use App\Http\Controllers\Settings\WebhookController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/impersonation', [UserImpersonationController::class, 'destroy'])->name('settings.impersonation.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::post('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');
    Route::get('settings/api', [ApiController::class, 'edit'])->name('settings.api.edit');
});

Route::middleware(['auth', 'verified', 'can:view-users'])->group(function () {
    Route::get('settings/users', [UserController::class, 'index'])->name('settings.users.index');
    Route::get('settings/users/{user}', [UserController::class, 'show'])->name('settings.users.show');
});

Route::middleware(['auth', 'verified', 'can:manage-user-accounts'])->group(function () {
    Route::post('settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::patch('settings/users/{user}/profile', [UserController::class, 'updateProfile'])->name('settings.users.profile.update');
    Route::patch('settings/users/{user}/password', [UserController::class, 'resetPassword'])->name('settings.users.password.reset');
    Route::post('settings/api/tokens', [ApiController::class, 'store'])
        ->middleware('throttle:api-tokens')
        ->name('settings.api.tokens.store');
    Route::delete('settings/api/tokens/{apiAccessToken}', [ApiController::class, 'destroy'])->name('settings.api.tokens.destroy');
});

Route::middleware(['auth', 'verified', 'can:impersonate-users'])->group(function () {
    Route::post('settings/users/{user}/impersonation', [UserImpersonationController::class, 'store'])->name('settings.users.impersonation.store');
});

Route::middleware(['auth', 'verified', 'can:manage-user-activation'])->group(function () {
    Route::patch('settings/users/{user}/activation', [UserController::class, 'updateActivation'])->name('settings.users.activation.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/menu', [MenuController::class, 'edit'])->name('settings.menu.edit');
    Route::post('settings/menu', [MenuController::class, 'store'])->name('settings.menu.store');
    Route::patch('settings/menu/items/{menuItem}', [MenuController::class, 'update'])->name('settings.menu.items.update');
    Route::patch('settings/menu/order', [MenuController::class, 'updateOrder'])->name('settings.menu.order.update');
    Route::patch('settings/menu/built-in/{key}/visibility', [MenuController::class, 'updateBuiltInVisibility'])->name('settings.menu.built-in.visibility.update');
    Route::patch('settings/menu/items/{menuItem}/visibility', [MenuController::class, 'updateVisibility'])->name('settings.menu.items.visibility.update');
    Route::delete('settings/menu/items/{menuItem}', [MenuController::class, 'destroy'])->name('settings.menu.items.destroy');
});

Route::middleware(['auth', 'verified', 'can:manage-users'])->group(function () {
    Route::patch('settings/users/{user}/group', [UserController::class, 'updateGroup'])->name('settings.users.group.update');

    Route::get('settings/groups', [UserGroupController::class, 'index'])->name('settings.groups.index');
    Route::post('settings/groups', [UserGroupController::class, 'store'])->name('settings.groups.store');
    Route::get('settings/rights', [UserGroupPermissionController::class, 'index'])->name('settings.rights.index');
    Route::patch('settings/rights/{userGroup}', [UserGroupPermissionController::class, 'update'])->name('settings.rights.update');

    Route::get('settings/portal', [PortalController::class, 'edit'])->name('settings.portal.edit');
    Route::post('settings/portal', [PortalController::class, 'update'])->name('settings.portal.update');
    Route::get('settings/modules', [ModuleController::class, 'edit'])->name('settings.modules.edit');
    Route::patch('settings/modules', [ModuleController::class, 'update'])->name('settings.modules.update');

    Route::get('settings/integrations', [MessengerIntegrationController::class, 'edit'])->name('settings.integrations.edit');
    Route::patch('settings/integrations/{messengerIntegration}', [MessengerIntegrationController::class, 'update'])->name('settings.integrations.update');
});

Route::middleware(['auth', 'verified', 'can:manage-webhooks'])->group(function () {
    Route::get('settings/webhooks', [WebhookController::class, 'edit'])->name('settings.webhooks.edit');
    Route::post('settings/webhooks', [WebhookController::class, 'store'])->name('settings.webhooks.store');
    Route::patch('settings/webhooks/{portalWebhook}', [WebhookController::class, 'update'])->name('settings.webhooks.update');
    Route::post('settings/webhooks/{portalWebhook}/regenerate', [WebhookController::class, 'regenerate'])->name('settings.webhooks.regenerate');
    Route::delete('settings/webhooks/{portalWebhook}', [WebhookController::class, 'destroy'])->name('settings.webhooks.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
