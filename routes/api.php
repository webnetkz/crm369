<?php

use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\EdoDocumentController as ApiEdoDocumentController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\KnowledgeBaseController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\MessengerIntegrationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\PortalWebhookController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TsdController as ApiTsdController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserGroupController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Middleware\ResolveApiSubjectUser;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['module.enabled:api', 'auth:api', ResolveApiSubjectUser::class, 'throttle:api'])->group(function (): void {
    Route::get('profile', [ProfileController::class, 'show'])
        ->middleware('api.token:profile.read')
        ->name('api.v1.profile.show');
    Route::patch('profile', [ProfileController::class, 'update'])
        ->middleware('api.token:profile.write')
        ->name('api.v1.profile.update');
    Route::patch('profile/language', [ProfileController::class, 'updateLanguage'])
        ->middleware('api.token:profile.write')
        ->name('api.v1.profile.language.update');
    Route::patch('profile/appearance', [ProfileController::class, 'updateAppearance'])
        ->middleware('api.token:profile.write')
        ->name('api.v1.profile.appearance.update');

    Route::get('notifications', [NotificationController::class, 'index'])
        ->middleware('api.token:notifications.read')
        ->name('api.v1.notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'updateAll'])
        ->middleware('api.token:notifications.write')
        ->name('api.v1.notifications.read-all.update');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'update'])
        ->middleware('api.token:notifications.write')
        ->name('api.v1.notifications.read.update');

    Route::middleware('module.enabled:chats')->group(function (): void {
        Route::get('chats', [ChatController::class, 'index'])
            ->middleware('api.token:chat.read')
            ->name('api.v1.chats.index');
        Route::post('chats/direct', [ChatController::class, 'storeDirect'])
            ->middleware('api.token:chat.write')
            ->name('api.v1.chats.direct.store');
        Route::post('chats/{chatConversation}/messages', [ChatController::class, 'storeMessage'])
            ->middleware('api.token:chat.write')
            ->name('api.v1.chats.messages.store');
    });

    Route::middleware('module.enabled:contacts')->group(function (): void {
        Route::get('contacts', [ContactController::class, 'index'])
            ->middleware('api.token:contacts.read')
            ->name('api.v1.contacts.index');
        Route::get('contacts/{contact}', [ContactController::class, 'show'])
            ->middleware('api.token:contacts.read')
            ->name('api.v1.contacts.show');
        Route::post('contacts', [ContactController::class, 'store'])
            ->middleware('api.token:contacts.write')
            ->name('api.v1.contacts.store');
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])
            ->middleware('api.token:contacts.write')
            ->name('api.v1.contacts.update');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])
            ->middleware('api.token:contacts.write')
            ->name('api.v1.contacts.destroy');
    });

    Route::middleware('module.enabled:edo')->group(function (): void {
        Route::get('edo/documents', [ApiEdoDocumentController::class, 'index'])
            ->middleware('api.token:edo.read')
            ->name('api.v1.edo.index');
        Route::get('edo/documents/{edoDocument}', [ApiEdoDocumentController::class, 'show'])
            ->middleware('api.token:edo.read')
            ->name('api.v1.edo.show');
        Route::post('edo/documents', [ApiEdoDocumentController::class, 'store'])
            ->middleware('api.token:edo.write')
            ->name('api.v1.edo.store');
        Route::patch('edo/documents/{edoDocument}', [ApiEdoDocumentController::class, 'update'])
            ->middleware('api.token:edo.write')
            ->name('api.v1.edo.update');
        Route::delete('edo/documents/{edoDocument}', [ApiEdoDocumentController::class, 'destroy'])
            ->middleware('api.token:edo.write')
            ->name('api.v1.edo.destroy');
        Route::post('edo/documents/{edoDocument}/public-link', [ApiEdoDocumentController::class, 'issuePublicLink'])
            ->middleware('api.token:edo.write')
            ->name('api.v1.edo.public-link.store');
    });

    Route::middleware('module.enabled:knowledge-bases')->group(function (): void {
        Route::get('knowledge-bases', [KnowledgeBaseController::class, 'index'])
            ->middleware('api.token:knowledge.read')
            ->name('api.v1.knowledge-bases.index');
        Route::get('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'show'])
            ->middleware('api.token:knowledge.read')
            ->name('api.v1.knowledge-bases.show');
        Route::get('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'showArticle'])
            ->middleware('api.token:knowledge.read')
            ->name('api.v1.knowledge-bases.articles.show');
        Route::post('knowledge-bases', [KnowledgeBaseController::class, 'store'])
            ->middleware(['api.token:knowledge.write', 'can:manage-knowledge-bases'])
            ->name('api.v1.knowledge-bases.store');
        Route::patch('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'update'])
            ->middleware(['api.token:knowledge.write', 'can:manage-knowledge-bases'])
            ->name('api.v1.knowledge-bases.update');
        Route::delete('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'destroy'])
            ->middleware(['api.token:knowledge.write', 'can:manage-knowledge-bases'])
            ->name('api.v1.knowledge-bases.destroy');
        Route::post('knowledge-bases/{knowledgeBase}/articles', [KnowledgeBaseController::class, 'storeArticle'])
            ->middleware(['api.token:knowledge.write', 'can:manage-knowledge-bases'])
            ->name('api.v1.knowledge-bases.articles.store');
        Route::patch('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'updateArticle'])
            ->middleware(['api.token:knowledge.write', 'can:manage-knowledge-bases'])
            ->name('api.v1.knowledge-bases.articles.update');
        Route::delete('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'destroyArticle'])
            ->middleware(['api.token:knowledge.write', 'can:manage-knowledge-bases'])
            ->name('api.v1.knowledge-bases.articles.destroy');
    });

    Route::middleware('module.enabled:projects')->group(function (): void {
        Route::get('projects', [ProjectController::class, 'index'])
            ->middleware('api.token:projects.read')
            ->name('api.v1.projects.index');
        Route::post('projects', [ProjectController::class, 'store'])
            ->middleware('api.token:projects.write')
            ->name('api.v1.projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])
            ->middleware('api.token:projects.read')
            ->name('api.v1.projects.show');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])
            ->middleware('api.token:projects.write')
            ->name('api.v1.projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
            ->middleware('api.token:projects.write')
            ->name('api.v1.projects.destroy');

        Route::post('tasks', [ProjectController::class, 'storeTask'])
            ->middleware('api.token:tasks.write')
            ->name('api.v1.tasks.store');
        Route::get('tasks/{projectTask}', [ProjectController::class, 'showTask'])
            ->middleware('api.token:tasks.read')
            ->name('api.v1.tasks.show');
        Route::patch('tasks/{projectTask}', [ProjectController::class, 'updateTask'])
            ->middleware('api.token:tasks.write')
            ->name('api.v1.tasks.update');
        Route::delete('tasks/{projectTask}', [ProjectController::class, 'destroyTask'])
            ->middleware('api.token:tasks.write')
            ->name('api.v1.tasks.destroy');
    });

    Route::middleware('module.enabled:warehouses')->group(function (): void {
        Route::get('warehouses', [WarehouseController::class, 'index'])
            ->middleware('api.token:warehouses.read')
            ->name('api.v1.warehouses.index');
        Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])
            ->middleware('api.token:warehouses.read')
            ->name('api.v1.warehouses.show');
        Route::post('warehouses', [WarehouseController::class, 'store'])
            ->middleware('api.token:warehouses.write')
            ->name('api.v1.warehouses.store');
        Route::patch('warehouses/{warehouse}', [WarehouseController::class, 'update'])
            ->middleware('api.token:warehouses.write')
            ->name('api.v1.warehouses.update');
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])
            ->middleware('api.token:warehouses.write')
            ->name('api.v1.warehouses.destroy');
    });

    Route::middleware('module.enabled:equipment')->group(function (): void {
        Route::get('equipment', [EquipmentController::class, 'index'])
            ->middleware('api.token:equipment.read')
            ->name('api.v1.equipment.index');
        Route::get('equipment/{equipmentItem}', [EquipmentController::class, 'show'])
            ->middleware('api.token:equipment.read')
            ->name('api.v1.equipment.show');
        Route::post('equipment', [EquipmentController::class, 'store'])
            ->middleware('api.token:equipment.write')
            ->name('api.v1.equipment.store');
        Route::patch('equipment/{equipmentItem}', [EquipmentController::class, 'update'])
            ->middleware('api.token:equipment.write')
            ->name('api.v1.equipment.update');
    });

    Route::middleware('module.enabled:tsd')->group(function (): void {
        Route::get('tsd/scans', [ApiTsdController::class, 'index'])
            ->middleware('api.token:tsd.read')
            ->name('api.v1.tsd.index');
        Route::post('tsd/scans', [ApiTsdController::class, 'store'])
            ->middleware('api.token:tsd.write')
            ->name('api.v1.tsd.store');
    });

    Route::get('users', [UserController::class, 'index'])
        ->middleware(['api.token:users.read', 'can:view-users'])
        ->name('api.v1.users.index');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware(['api.token:users.read', 'can:view-users'])
        ->name('api.v1.users.show');
    Route::post('users', [UserController::class, 'store'])
        ->middleware(['api.token:users.write', 'can:manage-user-accounts'])
        ->name('api.v1.users.store');
    Route::patch('users/{user}/profile', [UserController::class, 'updateProfile'])
        ->middleware(['api.token:users.write', 'can:manage-user-accounts'])
        ->name('api.v1.users.profile.update');
    Route::patch('users/{user}/password', [UserController::class, 'resetPassword'])
        ->middleware(['api.token:users.write', 'can:manage-user-accounts'])
        ->name('api.v1.users.password.reset');
    Route::patch('users/{user}/activation', [UserController::class, 'updateActivation'])
        ->middleware(['api.token:users.write', 'can:manage-user-activation'])
        ->name('api.v1.users.activation.update');
    Route::patch('users/{user}/group', [UserController::class, 'updateGroup'])
        ->middleware(['api.token:users.write', 'can:manage-users'])
        ->name('api.v1.users.group.update');

    Route::get('groups', [UserGroupController::class, 'index'])
        ->middleware(['api.token:groups.read', 'can:manage-users'])
        ->name('api.v1.groups.index');
    Route::post('groups', [UserGroupController::class, 'store'])
        ->middleware(['api.token:groups.write', 'can:manage-users'])
        ->name('api.v1.groups.store');
    Route::patch('groups/{userGroup}/permissions', [UserGroupController::class, 'updatePermissions'])
        ->middleware(['api.token:groups.write', 'can:manage-users'])
        ->name('api.v1.groups.permissions.update');

    Route::get('menu', [MenuController::class, 'index'])
        ->middleware('api.token:menu.read')
        ->name('api.v1.menu.index');
    Route::post('menu/items', [MenuController::class, 'store'])
        ->middleware('api.token:menu.write')
        ->name('api.v1.menu.items.store');
    Route::patch('menu/items/{menuItem}', [MenuController::class, 'update'])
        ->middleware('api.token:menu.write')
        ->name('api.v1.menu.items.update');
    Route::patch('menu/built-in/{key}/visibility', [MenuController::class, 'updateBuiltInVisibility'])
        ->middleware('api.token:menu.write')
        ->name('api.v1.menu.built-in.visibility.update');
    Route::patch('menu/items/{menuItem}/visibility', [MenuController::class, 'updateVisibility'])
        ->middleware('api.token:menu.write')
        ->name('api.v1.menu.items.visibility.update');
    Route::delete('menu/items/{menuItem}', [MenuController::class, 'destroy'])
        ->middleware('api.token:menu.write')
        ->name('api.v1.menu.items.destroy');

    Route::get('portal', [PortalController::class, 'show'])
        ->middleware(['api.token:portal.read', 'can:manage-users'])
        ->name('api.v1.portal.show');
    Route::post('portal', [PortalController::class, 'update'])
        ->middleware(['api.token:portal.write', 'can:manage-users'])
        ->name('api.v1.portal.update');

    Route::middleware('module.enabled:integrations')->group(function (): void {
        Route::get('integrations', [MessengerIntegrationController::class, 'index'])
            ->middleware(['api.token:integrations.read', 'can:manage-messenger-integrations'])
            ->name('api.v1.integrations.index');
        Route::patch('integrations/{messengerIntegration}', [MessengerIntegrationController::class, 'update'])
            ->middleware(['api.token:integrations.write', 'can:manage-messenger-integrations'])
            ->name('api.v1.integrations.update');
    });

    Route::middleware('module.enabled:webhooks')->group(function (): void {
        Route::get('webhooks', [PortalWebhookController::class, 'index'])
            ->middleware(['api.token:webhooks.read', 'can:manage-webhooks'])
            ->name('api.v1.webhooks.index');
        Route::post('webhooks', [PortalWebhookController::class, 'store'])
            ->middleware(['api.token:webhooks.write', 'can:manage-webhooks'])
            ->name('api.v1.webhooks.store');
        Route::patch('webhooks/{portalWebhook}', [PortalWebhookController::class, 'update'])
            ->middleware(['api.token:webhooks.write', 'can:manage-webhooks'])
            ->name('api.v1.webhooks.update');
        Route::post('webhooks/{portalWebhook}/regenerate', [PortalWebhookController::class, 'regenerate'])
            ->middleware(['api.token:webhooks.write', 'can:manage-webhooks'])
            ->name('api.v1.webhooks.regenerate');
        Route::delete('webhooks/{portalWebhook}', [PortalWebhookController::class, 'destroy'])
            ->middleware(['api.token:webhooks.write', 'can:manage-webhooks'])
            ->name('api.v1.webhooks.destroy');
    });
});
