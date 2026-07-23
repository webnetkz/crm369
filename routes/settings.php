<?php

use App\Actions\SystemUpdates\StartSystemUpdate;
use App\Http\Controllers\Settings\ApiController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\BusinessProcessController;
use App\Http\Controllers\Settings\LogController;
use App\Http\Controllers\Settings\MenuController;
use App\Http\Controllers\Settings\MessengerIntegrationController;
use App\Http\Controllers\Settings\ModuleController;
use App\Http\Controllers\Settings\OneCIntegrationController;
use App\Http\Controllers\Settings\PortalController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SystemSecurityController;
use App\Http\Controllers\Settings\SystemUpdateController;
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

    Route::post('settings/security/audits', [SecurityController::class, 'storeAudit'])
        ->middleware([RequirePassword::class, 'throttle:3,1'])
        ->name('security.audits.store');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::post('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');
});

Route::middleware(['auth', 'verified', 'can:manage-system-security', RequirePassword::class])
    ->prefix('settings/system-security')
    ->name('settings.system-security.')
    ->group(function () {
        Route::get('/', [SystemSecurityController::class, 'edit'])->name('edit');
        Route::post('audits', [SystemSecurityController::class, 'storeAudit'])
            ->middleware('throttle:3,1')
            ->name('audits.store');
        Route::patch('two-factor-requirement', [SystemSecurityController::class, 'updateTwoFactorRequirement'])
            ->middleware('throttle:6,1')
            ->name('two-factor-requirement.update');
    });

Route::middleware(['auth', 'verified', 'can:manage-system-updates', RequirePassword::class])
    ->prefix('settings/system-updates')
    ->name('settings.system-updates.')
    ->group(function () {
        Route::get('/', [SystemUpdateController::class, 'edit'])->name('edit');
        Route::post('checks', [SystemUpdateController::class, 'check'])
            ->middleware('throttle:3,1')
            ->name('checks.store');
        Route::post('components/{component}', [SystemUpdateController::class, 'start'])
            ->whereIn('component', StartSystemUpdate::COMPONENTS)
            ->middleware('throttle:3,1')
            ->name('components.update');
    });

Route::middleware(['auth', 'verified', 'module.enabled:api', 'can:manage-api-tokens'])->group(function () {
    Route::get('settings/api', [ApiController::class, 'edit'])->name('settings.api.edit');
    Route::get('settings/api/documentation', [ApiController::class, 'documentation'])
        ->name('settings.api.documentation.edit');

    Route::post('settings/api/tokens', [ApiController::class, 'store'])
        ->middleware('throttle:api-tokens')
        ->name('settings.api.tokens.store');
    Route::delete('settings/api/tokens/{apiAccessToken}', [ApiController::class, 'destroy'])
        ->name('settings.api.tokens.destroy');
});

Route::middleware(['auth', 'verified', 'can:view-users'])->group(function () {
    Route::get('settings/users', [UserController::class, 'index'])->name('settings.users.index');
    Route::get('settings/users/export', [UserController::class, 'exportCsv'])->name('settings.users.export');
    Route::get('settings/users/{user}', [UserController::class, 'show'])
        ->whereNumber('user')
        ->name('settings.users.show');
    Route::patch('settings/users/table-columns', [UserController::class, 'updateTableColumns'])->name('settings.users.table-columns.update');
});

Route::middleware(['auth', 'verified', 'can:manage-user-accounts'])->group(function () {
    Route::post('settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::get('settings/users/template', [UserController::class, 'downloadCsvTemplate'])->name('settings.users.template');
    Route::post('settings/users/import', [UserController::class, 'importCsv'])->name('settings.users.import');
    Route::patch('settings/users/{user}/profile', [UserController::class, 'updateProfile'])
        ->whereNumber('user')
        ->name('settings.users.profile.update');
    Route::patch('settings/users/{user}/password', [UserController::class, 'resetPassword'])
        ->whereNumber('user')
        ->name('settings.users.password.reset');
});

Route::middleware(['auth', 'verified', 'can:impersonate-users'])->group(function () {
    Route::post('settings/users/{user}/impersonation', [UserImpersonationController::class, 'store'])
        ->whereNumber('user')
        ->name('settings.users.impersonation.store');
});

Route::middleware(['auth', 'verified', 'can:manage-user-activation'])->group(function () {
    Route::patch('settings/users/{user}/activation', [UserController::class, 'updateActivation'])
        ->whereNumber('user')
        ->name('settings.users.activation.update');
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
    Route::patch('settings/users/{user}/group', [UserController::class, 'updateGroup'])
        ->whereNumber('user')
        ->name('settings.users.group.update');

    Route::get('settings/groups', [UserGroupController::class, 'index'])->name('settings.groups.index');
    Route::post('settings/groups', [UserGroupController::class, 'store'])->name('settings.groups.store');
    Route::get('settings/rights', [UserGroupPermissionController::class, 'index'])->name('settings.rights.index');
    Route::patch('settings/rights/{userGroup}', [UserGroupPermissionController::class, 'update'])->name('settings.rights.update');

    Route::get('settings/portal', [PortalController::class, 'edit'])->name('settings.portal.edit');
    Route::post('settings/portal', [PortalController::class, 'update'])->name('settings.portal.update');
    Route::get('settings/modules', [ModuleController::class, 'edit'])->name('settings.modules.edit');
    Route::patch('settings/modules', [ModuleController::class, 'update'])->name('settings.modules.update');
    Route::get('settings/logs', [LogController::class, 'edit'])->name('settings.logs.edit');
});

Route::middleware(['auth', 'verified', 'module.enabled:business-processes', 'can:manage-business-processes'])->group(function () {
    Route::get('settings/business-processes', [BusinessProcessController::class, 'index'])
        ->name('settings.business-processes.index');
    Route::post('settings/business-processes', [BusinessProcessController::class, 'store'])
        ->name('settings.business-processes.store');
    Route::patch('settings/business-processes/{businessProcess}', [BusinessProcessController::class, 'update'])
        ->name('settings.business-processes.update');
    Route::delete('settings/business-processes/{businessProcess}', [BusinessProcessController::class, 'destroy'])
        ->name('settings.business-processes.destroy');
});

Route::middleware(['auth', 'verified', 'module.enabled:integrations', 'can:manage-messenger-integrations'])->group(function () {
    Route::get('settings/integrations', [MessengerIntegrationController::class, 'edit'])
        ->name('settings.integrations.edit');
    Route::patch('settings/integrations/{messengerIntegration}', [MessengerIntegrationController::class, 'update'])
        ->name('settings.integrations.update');
});

Route::middleware(['auth', 'verified', 'module.enabled:one-c', 'can:manage-one-c'])->group(function () {
    Route::get('settings/one-c', [OneCIntegrationController::class, 'edit'])
        ->name('settings.one-c.edit');
    Route::post('settings/one-c', [OneCIntegrationController::class, 'store'])
        ->name('settings.one-c.store');
    Route::patch('settings/one-c/{oneCIntegration}', [OneCIntegrationController::class, 'update'])
        ->name('settings.one-c.update');
    Route::post('settings/one-c/{oneCIntegration}/test', [OneCIntegrationController::class, 'test'])
        ->middleware('throttle:6,1')
        ->name('settings.one-c.test');
    Route::delete('settings/one-c/{oneCIntegration}', [OneCIntegrationController::class, 'destroy'])
        ->name('settings.one-c.destroy');
});

Route::middleware(['auth', 'verified', 'module.enabled:webhooks', 'can:manage-webhooks'])->group(function () {
    Route::get('settings/webhooks', [WebhookController::class, 'edit'])->name('settings.webhooks.edit');
    Route::get('settings/webhooks/documentation', [WebhookController::class, 'documentation'])
        ->name('settings.webhooks.documentation.edit');
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
