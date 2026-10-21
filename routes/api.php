<?php

use App\Http\Controllers\Api\Mobile\V1\MobileBootstrapController;
use App\Http\Controllers\Api\Mobile\V1\MobileChatController;
use App\Http\Controllers\Api\Mobile\V1\MobileConferenceController;
use App\Http\Controllers\Api\Mobile\V1\MobileContactCommentController;
use App\Http\Controllers\Api\Mobile\V1\MobileCrmFunnelController;
use App\Http\Controllers\Api\Mobile\V1\MobileDashboardController;
use App\Http\Controllers\Api\Mobile\V1\MobileDeviceController;
use App\Http\Controllers\Api\Mobile\V1\MobileEdoDocumentController;
use App\Http\Controllers\Api\Mobile\V1\MobileFileController;
use App\Http\Controllers\Api\Mobile\V1\MobileNewsController;
use App\Http\Controllers\Api\Mobile\V1\MobilePasswordController;
use App\Http\Controllers\Api\Mobile\V1\MobilePortalController;
use App\Http\Controllers\Api\Mobile\V1\MobilePortalFormController;
use App\Http\Controllers\Api\Mobile\V1\MobileProductionController;
use App\Http\Controllers\Api\Mobile\V1\MobileProjectTaskController;
use App\Http\Controllers\Api\Mobile\V1\MobileSessionController;
use App\Http\Controllers\Api\Mobile\V1\MobileWarehouseController;
use App\Http\Controllers\Api\Mobile\V1\MobileWebSessionController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CompanyStructureController as ApiCompanyStructureController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\EdoDocumentController as ApiEdoDocumentController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\KnowledgeBaseController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\MessengerIntegrationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\PortalWebhookController;
use App\Http\Controllers\Api\V1\ProcurementController as ApiProcurementController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ReferenceDirectoryController as ApiReferenceDirectoryController;
use App\Http\Controllers\Api\V1\TsdController as ApiTsdController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserGroupController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\WarehouseController as WebWarehouseController;
use App\Http\Middleware\ResolveApiSubjectUser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Native mobile API
|--------------------------------------------------------------------------
|
| Endpoints consumed by the native Android application. The application is
| bound to a portal domain, discovers the portal through the public portal
| endpoint, signs in with a persistent bearer session and then uses the
| authenticated module endpoints. Module endpoints mirror the web
| application's module, gate, and visibility rules.
|
*/

Route::prefix('mobile/v1')->name('mobile.v1.')->middleware('mobile.language')->group(function (): void {
    Route::get('portal', [MobilePortalController::class, 'show'])
        ->middleware('throttle:mobile-portal')
        ->name('portal');
    Route::post('login', [MobileSessionController::class, 'store'])
        ->middleware('throttle:mobile-login')
        ->name('login');
    Route::post('two-factor-challenge', [MobileSessionController::class, 'challenge'])
        ->middleware('throttle:mobile-two-factor')
        ->name('two-factor.challenge');

    Route::middleware(['auth:mobile', 'mobile.language', 'throttle:mobile-api'])->whereNumber([
        'chatConversation',
        'chatMessage',
        'contact',
        'conference',
        'crmDeal',
        'crmFunnel',
        'crmFunnelStage',
        'edoDocument',
        'equipmentItem',
        'fileDirectory',
        'fileDirectoryPermission',
        'fileEntry',
        'knowledgeBase',
        'knowledgeBaseArticle',
        'portalForm',
        'project',
        'projectTask',
        'projectTaskStage',
        'purchaseOrder',
        'purchaseRequest',
        'referenceDirectory',
        'referenceDirectoryRecord',
        'supplier',
        'user',
        'warehouse',
        'warehouseFloor',
    ])->group(function (): void {
        Route::get('me', [MobileSessionController::class, 'show'])->name('me');
        Route::delete('logout', [MobileSessionController::class, 'destroy'])->name('logout');
        Route::put('device', [MobileDeviceController::class, 'store'])->name('device.store');
        Route::delete('device', [MobileDeviceController::class, 'destroy'])->name('device.destroy');
        Route::get('bootstrap', [MobileBootstrapController::class, 'show'])->name('bootstrap');
        Route::post('web-session', [MobileWebSessionController::class, 'store'])
            ->middleware('throttle:mobile-web-session')
            ->name('web-session.store');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language.update');
        Route::patch('profile/appearance', [ProfileController::class, 'updateAppearance'])->name('profile.appearance.update');
        Route::put('profile/password', [MobilePasswordController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('profile.password.update');

        Route::get('dashboard', [MobileDashboardController::class, 'show'])->name('dashboard.show');
        Route::patch('dashboard/configuration', [MobileDashboardController::class, 'updateConfiguration'])->name('dashboard.configuration.update');

        Route::get('menu', [MenuController::class, 'index'])->name('menu.index');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/read-all', [NotificationController::class, 'updateAll'])->name('notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'update'])->name('notifications.read');

        Route::middleware(['module.enabled:chats', 'can:access-chats'])->group(function (): void {
            Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
            Route::post('chats/direct', [ChatController::class, 'storeDirect'])->name('chats.direct.store');
            Route::get('chats/users/{user}/profile', [MobileChatController::class, 'showUserProfile'])
                ->whereNumber('user')
                ->name('chats.users.show');
            Route::get('chats/attachments/{chatMessageAttachment}/preview', [MobileChatController::class, 'previewAttachment'])
                ->whereNumber('chatMessageAttachment')
                ->name('chats.attachments.preview');
            Route::get('chats/attachments/{chatMessageAttachment}/download', [MobileChatController::class, 'downloadAttachment'])
                ->whereNumber('chatMessageAttachment')
                ->name('chats.attachments.download');
            Route::post('chats/{chatConversation}/messages', [ChatController::class, 'storeMessage'])->name('chats.messages.store');
            Route::patch('chats/{chatConversation}/messages/{chatMessage}', [ChatController::class, 'updateMessage'])->name('chats.messages.update');
            Route::delete('chats/{chatConversation}/messages/{chatMessage}', [ChatController::class, 'destroyMessage'])->name('chats.messages.destroy');
            Route::patch('chats/{chatConversation}/messages/{chatMessage}/pin', [MobileChatController::class, 'pin'])->name('chats.messages.pin');
            Route::delete('chats/{chatConversation}/messages/{chatMessage}/pin', [MobileChatController::class, 'unpin'])->name('chats.messages.unpin');
        });

        Route::middleware(['module.enabled:calendar', 'can:access-calendar'])->group(function (): void {
            Route::get('calendar/events', [CalendarController::class, 'index'])->name('calendar.events');
        });

        Route::middleware(['module.enabled:company-structure', 'can:access-company-structure'])->group(function (): void {
            Route::get('company-structure', [ApiCompanyStructureController::class, 'index'])->name('company-structure.index');
            Route::get('company-structure/users/{user}', [ApiCompanyStructureController::class, 'show'])->name('company-structure.show');
        });

        Route::middleware(['module.enabled:contacts', 'can:access-contacts'])->group(function (): void {
            Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
            Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
            Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
            Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
            Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
            Route::get('contacts/{contact}/comments', [MobileContactCommentController::class, 'index'])->name('contacts.comments.index');
            Route::post('contacts/{contact}/comments', [MobileContactCommentController::class, 'store'])->name('contacts.comments.store');
        });

        Route::middleware(['module.enabled:directories', 'can:access-directories'])->group(function (): void {
            Route::get('directories', [ApiReferenceDirectoryController::class, 'index'])->name('directories.index');
            Route::get('directories/{referenceDirectory}', [ApiReferenceDirectoryController::class, 'show'])->name('directories.show');
            Route::get('directories/{referenceDirectory}/export', [ApiReferenceDirectoryController::class, 'exportCsv'])->name('directories.export');
            Route::get('directories/{referenceDirectory}/template', [ApiReferenceDirectoryController::class, 'downloadCsvTemplate'])->name('directories.template');
        });

        Route::middleware(['module.enabled:directories', 'can:manage-directories'])->group(function (): void {
            Route::post('directories', [ApiReferenceDirectoryController::class, 'store'])->name('directories.store');
            Route::patch('directories/{referenceDirectory}', [ApiReferenceDirectoryController::class, 'update'])->name('directories.update');
            Route::delete('directories/{referenceDirectory}', [ApiReferenceDirectoryController::class, 'destroy'])->name('directories.destroy');
            Route::post('directories/{referenceDirectory}/import', [ApiReferenceDirectoryController::class, 'importCsv'])->name('directories.import');
            Route::post('directories/{referenceDirectory}/records', [ApiReferenceDirectoryController::class, 'storeRecord'])->name('directories.records.store');
            Route::patch('directories/{referenceDirectory}/records/{referenceDirectoryRecord}', [ApiReferenceDirectoryController::class, 'updateRecord'])->name('directories.records.update');
            Route::delete('directories/{referenceDirectory}/records/{referenceDirectoryRecord}', [ApiReferenceDirectoryController::class, 'destroyRecord'])->name('directories.records.destroy');
        });

        Route::middleware(['module.enabled:edo', 'can:access-edo'])->group(function (): void {
            Route::get('edo/documents', [ApiEdoDocumentController::class, 'index'])->name('edo.index');
            Route::post('edo/documents', [ApiEdoDocumentController::class, 'store'])->name('edo.store');
            Route::get('edo/documents/{edoDocument}', [ApiEdoDocumentController::class, 'show'])->name('edo.show');
            Route::patch('edo/documents/{edoDocument}', [ApiEdoDocumentController::class, 'update'])->name('edo.update');
            Route::delete('edo/documents/{edoDocument}', [ApiEdoDocumentController::class, 'destroy'])->name('edo.destroy');
            Route::post('edo/documents/{edoDocument}/public-link', [ApiEdoDocumentController::class, 'issuePublicLink'])->name('edo.public-link.store');
            Route::get('edo/documents/{edoDocument}/file', [MobileEdoDocumentController::class, 'downloadFile'])->name('edo.file.download');
        });

        Route::middleware(['module.enabled:files', 'can:access-files'])->group(function (): void {
            Route::get('files', [MobileFileController::class, 'index'])->name('files.index');
            Route::post('files/directories', [MobileFileController::class, 'storeDirectory'])->name('files.directories.store');
            Route::delete('files/directories/{fileDirectory}', [MobileFileController::class, 'destroyDirectory'])->name('files.directories.destroy');
            Route::post('files/directories/{fileDirectory}/permissions', [MobileFileController::class, 'storePermission'])->name('files.directories.permissions.store');
            Route::delete('files/directories/{fileDirectory}/permissions/{fileDirectoryPermission}', [MobileFileController::class, 'destroyPermission'])->name('files.directories.permissions.destroy');
            Route::post('files/entries', [MobileFileController::class, 'storeEntry'])->name('files.entries.store');
            Route::get('files/entries/{fileEntry}/download', [MobileFileController::class, 'download'])->name('files.entries.download');
            Route::delete('files/entries/{fileEntry}', [MobileFileController::class, 'destroyEntry'])->name('files.entries.destroy');
        });

        Route::middleware(['module.enabled:news', 'can:access-news'])->group(function (): void {
            Route::get('news', [MobileNewsController::class, 'index'])->name('news.index');
            Route::get('news/{news}', [MobileNewsController::class, 'show'])->name('news.show');
        });

        Route::middleware(['module.enabled:news', 'can:manage-news'])->group(function (): void {
            Route::post('news', [MobileNewsController::class, 'store'])->name('news.store');
            Route::patch('news/{news}', [MobileNewsController::class, 'update'])->name('news.update');
            Route::delete('news/{news}', [MobileNewsController::class, 'destroy'])->name('news.destroy');
        });

        Route::middleware(['module.enabled:knowledge-bases', 'can:access-knowledge-bases'])->group(function (): void {
            Route::get('knowledge-bases', [KnowledgeBaseController::class, 'index'])->name('knowledge-bases.index');
            Route::get('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'show'])->name('knowledge-bases.show');
            Route::get('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'showArticle'])->name('knowledge-bases.articles.show');
        });

        Route::middleware(['module.enabled:knowledge-bases', 'can:manage-knowledge-bases'])->group(function (): void {
            Route::post('knowledge-bases', [KnowledgeBaseController::class, 'store'])->name('knowledge-bases.store');
            Route::patch('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'update'])->name('knowledge-bases.update');
            Route::delete('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge-bases.destroy');
            Route::post('knowledge-bases/{knowledgeBase}/articles', [KnowledgeBaseController::class, 'storeArticle'])->name('knowledge-bases.articles.store');
            Route::patch('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'updateArticle'])->name('knowledge-bases.articles.update');
            Route::delete('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'destroyArticle'])->name('knowledge-bases.articles.destroy');
        });

        Route::middleware('module.enabled:funnels')->group(function (): void {
            Route::get('funnels', [MobileCrmFunnelController::class, 'index'])->name('funnels.index');
            Route::post('funnels', [MobileCrmFunnelController::class, 'store'])->name('funnels.store');
            Route::get('funnels/{crmFunnel}', [MobileCrmFunnelController::class, 'show'])->name('funnels.show');
            Route::patch('funnels/{crmFunnel}', [MobileCrmFunnelController::class, 'update'])->name('funnels.update');
            Route::delete('funnels/{crmFunnel}', [MobileCrmFunnelController::class, 'destroy'])->name('funnels.destroy');
            Route::post('funnels/{crmFunnel}/stages', [MobileCrmFunnelController::class, 'storeStage'])->name('funnels.stages.store');
            Route::patch('funnels/{crmFunnel}/stages/{crmFunnelStage}', [MobileCrmFunnelController::class, 'updateStage'])->name('funnels.stages.update');
            Route::delete('funnels/{crmFunnel}/stages/{crmFunnelStage}', [MobileCrmFunnelController::class, 'destroyStage'])->name('funnels.stages.destroy');
            Route::post('funnels/{crmFunnel}/deals', [MobileCrmFunnelController::class, 'storeDeal'])->name('funnels.deals.store');
            Route::get('funnels/{crmFunnel}/deals/{crmDeal}', [MobileCrmFunnelController::class, 'showDeal'])->name('funnels.deals.show');
            Route::patch('funnels/{crmFunnel}/deals/{crmDeal}', [MobileCrmFunnelController::class, 'updateDeal'])->name('funnels.deals.update');
            Route::patch('funnels/{crmFunnel}/deals/{crmDeal}/move', [MobileCrmFunnelController::class, 'moveDeal'])->name('funnels.deals.move');
            Route::delete('funnels/{crmFunnel}/deals/{crmDeal}', [MobileCrmFunnelController::class, 'destroyDeal'])->name('funnels.deals.destroy');
        });

        Route::middleware(['module.enabled:forms', 'can:access-forms'])->group(function (): void {
            Route::get('forms', [MobilePortalFormController::class, 'index'])->name('forms.index');
            Route::post('forms', [MobilePortalFormController::class, 'store'])->name('forms.store');
            Route::get('forms/{portalForm}', [MobilePortalFormController::class, 'show'])->name('forms.show');
            Route::patch('forms/{portalForm}', [MobilePortalFormController::class, 'update'])->name('forms.update');
            Route::delete('forms/{portalForm}', [MobilePortalFormController::class, 'destroy'])->name('forms.destroy');
        });

        Route::middleware(['module.enabled:conferences', 'can:access-conferences'])->group(function (): void {
            Route::get('conferences', [MobileConferenceController::class, 'index'])->name('conferences.index');
            Route::post('conferences', [MobileConferenceController::class, 'store'])->name('conferences.store');
            Route::get('conferences/{conference}', [MobileConferenceController::class, 'show'])->name('conferences.show');
            Route::post('conferences/{conference}/invitations', [MobileConferenceController::class, 'storeInvitation'])->name('conferences.invitations.store');
            Route::patch('conferences/{conference}/end', [MobileConferenceController::class, 'end'])->name('conferences.end');
        });

        Route::middleware(['module.enabled:projects', 'can:access-projects'])->group(function (): void {
            Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
            Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
            Route::get('projects/workspace', [MobileProjectTaskController::class, 'workspace'])->name('projects.workspace');
            Route::get('projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
            Route::patch('projects/{project}', [ProjectController::class, 'update'])->whereNumber('project')->name('projects.update');
            Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->whereNumber('project')->name('projects.destroy');

            Route::post('task-stages', [MobileProjectTaskController::class, 'storeStage'])->name('task-stages.store');
            Route::patch('task-stages/move', [MobileProjectTaskController::class, 'moveStages'])->name('task-stages.move');
            Route::patch('task-stages/{projectTaskStage}', [MobileProjectTaskController::class, 'updateStage'])->whereNumber('projectTaskStage')->name('task-stages.update');

            Route::get('tasks', [MobileProjectTaskController::class, 'index'])->name('tasks.index');
            Route::post('tasks', [ProjectController::class, 'storeTask'])->name('tasks.store');
            Route::get('tasks/{projectTask}', [ProjectController::class, 'showTask'])->whereNumber('projectTask')->name('tasks.show');
            Route::patch('tasks/{projectTask}', [ProjectController::class, 'updateTask'])->whereNumber('projectTask')->name('tasks.update');
            Route::delete('tasks/{projectTask}', [ProjectController::class, 'destroyTask'])->whereNumber('projectTask')->name('tasks.destroy');
            Route::patch('tasks/{projectTask}/move', [MobileProjectTaskController::class, 'move'])->whereNumber('projectTask')->name('tasks.move');
            Route::get('tasks/{projectTask}/conversation', [MobileProjectTaskController::class, 'conversation'])->whereNumber('projectTask')->name('tasks.conversation.show');
        });

        Route::middleware(['module.enabled:production', 'can:access-production'])->group(function (): void {
            Route::get('production', [MobileProductionController::class, 'index'])->name('production.index');
            Route::get('production/{section}', [MobileProductionController::class, 'show'])->name('production.show');
        });

        Route::middleware(['module.enabled:procurement', 'can:access-procurement'])->prefix('procurement')->name('procurement.')->group(function (): void {
            Route::get('/', [ApiProcurementController::class, 'index'])->name('index');
            Route::post('requests', [ApiProcurementController::class, 'storeRequest'])->name('requests.store');
            Route::patch('requests/{purchaseRequest}/decision', [ApiProcurementController::class, 'decideRequest'])
                ->middleware('can:approve-procurement-budget')
                ->name('requests.decision.update');
            Route::post('suppliers', [ApiProcurementController::class, 'storeSupplier'])
                ->middleware('can:manage-procurement')
                ->name('suppliers.store');
            Route::patch('suppliers/{supplier}', [ApiProcurementController::class, 'updateSupplier'])
                ->middleware('can:manage-procurement')
                ->name('suppliers.update');
            Route::post('quotations', [ApiProcurementController::class, 'storeQuotation'])
                ->middleware('can:manage-procurement')
                ->name('quotations.store');
            Route::post('orders', [ApiProcurementController::class, 'storeOrder'])
                ->middleware('can:manage-procurement-orders')
                ->name('orders.store');
            Route::patch('orders/{purchaseOrder}/send', [ApiProcurementController::class, 'sendOrder'])
                ->middleware('can:manage-procurement-orders')
                ->name('orders.send');
            Route::post('receipts', [ApiProcurementController::class, 'storeReceipt'])
                ->middleware('can:receive-procurement-orders')
                ->name('receipts.store');
            Route::post('returns', [ApiProcurementController::class, 'storeReturn'])
                ->middleware('can:return-procurement-goods')
                ->name('returns.store');
        });

        Route::middleware(['module.enabled:warehouses', 'can:access-warehouses'])->group(function (): void {
            Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
            Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
            Route::post('warehouses/scan', [MobileWarehouseController::class, 'scan'])->name('warehouses.scan');
            Route::get('warehouses/qr/{qrCode}', [WebWarehouseController::class, 'qr'])->name('warehouses.qr.show');
            Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->whereNumber('warehouse')->name('warehouses.show');
            Route::get('warehouses/{warehouse}/items', [WarehouseController::class, 'items'])->whereNumber('warehouse')->name('warehouses.items');
            Route::get('warehouses/{warehouse}/floors/{warehouseFloor}', [MobileWarehouseController::class, 'floor'])
                ->whereNumber(['warehouse', 'warehouseFloor'])
                ->name('warehouses.floors.show');
        });

        Route::middleware(['module.enabled:equipment', 'can:access-equipment'])->group(function (): void {
            Route::get('equipment', [EquipmentController::class, 'index'])->name('equipment.index');
            Route::post('equipment', [EquipmentController::class, 'store'])->name('equipment.store');
            Route::get('equipment/{equipmentItem}', [EquipmentController::class, 'show'])->name('equipment.show');
            Route::patch('equipment/{equipmentItem}', [EquipmentController::class, 'update'])->name('equipment.update');
        });

        Route::middleware(['module.enabled:tsd', 'can:access-tsd'])->group(function (): void {
            Route::get('tsd/scans', [ApiTsdController::class, 'index'])->name('tsd.index');
            Route::post('tsd/scans', [ApiTsdController::class, 'store'])->name('tsd.store');
        });
    });
});

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
        Route::patch('chats/{chatConversation}/messages/{chatMessage}', [ChatController::class, 'updateMessage'])
            ->middleware('api.token:chat.write')
            ->name('api.v1.chats.messages.update');
        Route::delete('chats/{chatConversation}/messages/{chatMessage}', [ChatController::class, 'destroyMessage'])
            ->middleware('api.token:chat.write')
            ->name('api.v1.chats.messages.destroy');
    });

    Route::middleware('module.enabled:calendar')->group(function (): void {
        Route::get('calendar/events', [CalendarController::class, 'index'])
            ->middleware('api.token:calendar.read')
            ->name('api.v1.calendar.events.index');
    });

    Route::middleware('module.enabled:company-structure')->group(function (): void {
        Route::get('company-structure', [ApiCompanyStructureController::class, 'index'])
            ->middleware('api.token:company-structure.read')
            ->name('api.v1.company-structure.index');
        Route::get('company-structure/users/{user}', [ApiCompanyStructureController::class, 'show'])
            ->middleware('api.token:company-structure.read')
            ->name('api.v1.company-structure.show');
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

    Route::middleware('module.enabled:directories')->group(function (): void {
        Route::get('directories', [ApiReferenceDirectoryController::class, 'index'])
            ->middleware(['api.token:directories.read', 'can:access-directories'])
            ->name('api.v1.directories.index');
        Route::get('directories/{referenceDirectory}', [ApiReferenceDirectoryController::class, 'show'])
            ->middleware(['api.token:directories.read', 'can:access-directories'])
            ->name('api.v1.directories.show');
        Route::get('directories/{referenceDirectory}/export', [ApiReferenceDirectoryController::class, 'exportCsv'])
            ->middleware(['api.token:directories.read', 'can:access-directories'])
            ->name('api.v1.directories.export');
        Route::get('directories/{referenceDirectory}/template', [ApiReferenceDirectoryController::class, 'downloadCsvTemplate'])
            ->middleware(['api.token:directories.read', 'can:access-directories'])
            ->name('api.v1.directories.template');
        Route::post('directories', [ApiReferenceDirectoryController::class, 'store'])
            ->middleware(['api.token:directories.write', 'can:manage-directories'])
            ->name('api.v1.directories.store');
        Route::patch('directories/{referenceDirectory}', [ApiReferenceDirectoryController::class, 'update'])
            ->middleware(['api.token:directories.write', 'can:manage-directories'])
            ->name('api.v1.directories.update');
        Route::delete('directories/{referenceDirectory}', [ApiReferenceDirectoryController::class, 'destroy'])
            ->middleware(['api.token:directories.write', 'can:manage-directories'])
            ->name('api.v1.directories.destroy');
        Route::post('directories/{referenceDirectory}/import', [ApiReferenceDirectoryController::class, 'importCsv'])
            ->middleware(['api.token:directories.write', 'can:manage-directories'])
            ->name('api.v1.directories.import');
        Route::post('directories/{referenceDirectory}/records', [ApiReferenceDirectoryController::class, 'storeRecord'])
            ->middleware(['api.token:directories.write', 'can:manage-directories'])
            ->name('api.v1.directories.records.store');
        Route::patch('directories/{referenceDirectory}/records/{referenceDirectoryRecord}', [ApiReferenceDirectoryController::class, 'updateRecord'])
            ->middleware(['api.token:directories.write', 'can:manage-directories'])
            ->name('api.v1.directories.records.update');
        Route::delete('directories/{referenceDirectory}/records/{referenceDirectoryRecord}', [ApiReferenceDirectoryController::class, 'destroyRecord'])
            ->middleware(['api.token:directories.write', 'can:manage-directories'])
            ->name('api.v1.directories.records.destroy');
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
        Route::get('warehouses/{warehouse}/items', [WarehouseController::class, 'items'])
            ->middleware('api.token:warehouses.read')
            ->name('api.v1.warehouses.items');
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

    Route::middleware('module.enabled:procurement')->prefix('procurement')->group(function (): void {
        Route::get('/', [ApiProcurementController::class, 'index'])
            ->middleware(['api.token:procurement.read', 'can:access-procurement'])
            ->name('api.v1.procurement.index');
        Route::post('suppliers', [ApiProcurementController::class, 'storeSupplier'])
            ->middleware(['api.token:procurement.write', 'can:manage-procurement'])
            ->name('api.v1.procurement.suppliers.store');
        Route::patch('suppliers/{supplier}', [ApiProcurementController::class, 'updateSupplier'])
            ->middleware(['api.token:procurement.write', 'can:manage-procurement'])
            ->name('api.v1.procurement.suppliers.update');
        Route::post('requests', [ApiProcurementController::class, 'storeRequest'])
            ->middleware(['api.token:procurement.write', 'can:access-procurement'])
            ->name('api.v1.procurement.requests.store');
        Route::patch('requests/{purchaseRequest}/decision', [ApiProcurementController::class, 'decideRequest'])
            ->middleware(['api.token:procurement.write', 'can:approve-procurement-budget'])
            ->name('api.v1.procurement.requests.decision.update');
        Route::post('quotations', [ApiProcurementController::class, 'storeQuotation'])
            ->middleware(['api.token:procurement.write', 'can:manage-procurement'])
            ->name('api.v1.procurement.quotations.store');
        Route::post('orders', [ApiProcurementController::class, 'storeOrder'])
            ->middleware(['api.token:procurement.write', 'can:manage-procurement-orders'])
            ->name('api.v1.procurement.orders.store');
        Route::patch('orders/{purchaseOrder}/send', [ApiProcurementController::class, 'sendOrder'])
            ->middleware(['api.token:procurement.write', 'can:manage-procurement-orders'])
            ->name('api.v1.procurement.orders.send');
        Route::post('receipts', [ApiProcurementController::class, 'storeReceipt'])
            ->middleware(['api.token:procurement.write', 'can:receive-procurement-orders'])
            ->name('api.v1.procurement.receipts.store');
        Route::post('returns', [ApiProcurementController::class, 'storeReturn'])
            ->middleware(['api.token:procurement.write', 'can:return-procurement-goods'])
            ->name('api.v1.procurement.returns.store');
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
