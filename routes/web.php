<?php

use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ChatPageController;
use App\Http\Controllers\ChatSidebarController;
use App\Http\Controllers\CompanyStructureController;
use App\Http\Controllers\ContactCommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CrmFunnelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\EdoDocumentController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MobileNotificationFeedController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPageController;
use App\Http\Controllers\PortalFormController;
use App\Http\Controllers\PortalWebhookCompanyStructureController;
use App\Http\Controllers\PortalWebhookContactController;
use App\Http\Controllers\PortalWebhookEdoController;
use App\Http\Controllers\PortalWebhookEquipmentController;
use App\Http\Controllers\PortalWebhookInvokeController;
use App\Http\Controllers\PortalWebhookReferenceDirectoryController;
use App\Http\Controllers\PortalWebhookTsdController;
use App\Http\Controllers\PortalWebhookUserController;
use App\Http\Controllers\PortalWebhookWarehouseController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskConversationController;
use App\Http\Controllers\PublicEdoSigningController;
use App\Http\Controllers\PublicPortalFormController;
use App\Http\Controllers\ReferenceDirectoryController;
use App\Http\Controllers\TsdController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', fn (Request $request) => Inertia::render('auth/Login', [
    'canResetPassword' => Features::enabled(Features::resetPasswords()),
    'status' => $request->session()->get('status'),
]))->middleware('guest')->name('home');

Route::match(['GET', 'POST'], 'portal-webhooks/{portalWebhook}', PortalWebhookInvokeController::class)
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook'])
    ->name('portal-webhooks.invoke');
Route::get('portal-webhooks/{portalWebhook}/users', [PortalWebhookUserController::class, 'index'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:users.read'])
    ->name('portal-webhooks.users.index');
Route::get('portal-webhooks/{portalWebhook}/users/{user}', [PortalWebhookUserController::class, 'show'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:users.read'])
    ->name('portal-webhooks.users.show');
Route::get('portal-webhooks/{portalWebhook}/company-structure', [PortalWebhookCompanyStructureController::class, 'index'])
    ->middleware(['module.enabled:webhooks', 'module.enabled:company-structure', 'throttle:30,1', 'portal.webhook:company-structure.read'])
    ->name('portal-webhooks.company-structure.index');
Route::get('portal-webhooks/{portalWebhook}/company-structure/users/{user}', [PortalWebhookCompanyStructureController::class, 'show'])
    ->middleware(['module.enabled:webhooks', 'module.enabled:company-structure', 'throttle:30,1', 'portal.webhook:company-structure.read'])
    ->name('portal-webhooks.company-structure.show');
Route::get('portal-webhooks/{portalWebhook}/contacts', [PortalWebhookContactController::class, 'index'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:contacts.read', 'module.enabled:contacts'])
    ->name('portal-webhooks.contacts.index');
Route::get('portal-webhooks/{portalWebhook}/contacts/{contact}', [PortalWebhookContactController::class, 'show'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:contacts.read', 'module.enabled:contacts'])
    ->name('portal-webhooks.contacts.show');
Route::post('portal-webhooks/{portalWebhook}/contacts', [PortalWebhookContactController::class, 'store'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:contacts.write', 'module.enabled:contacts'])
    ->name('portal-webhooks.contacts.store');
Route::patch('portal-webhooks/{portalWebhook}/contacts/{contact}', [PortalWebhookContactController::class, 'update'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:contacts.write', 'module.enabled:contacts'])
    ->name('portal-webhooks.contacts.update');
Route::delete('portal-webhooks/{portalWebhook}/contacts/{contact}', [PortalWebhookContactController::class, 'destroy'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:contacts.write', 'module.enabled:contacts'])
    ->name('portal-webhooks.contacts.destroy');
Route::get('portal-webhooks/{portalWebhook}/directories', [PortalWebhookReferenceDirectoryController::class, 'index'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.read', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.index');
Route::get('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}', [PortalWebhookReferenceDirectoryController::class, 'show'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.read', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.show');
Route::get('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}/export', [PortalWebhookReferenceDirectoryController::class, 'exportCsv'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.read', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.export');
Route::get('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}/template', [PortalWebhookReferenceDirectoryController::class, 'downloadCsvTemplate'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.read', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.template');
Route::post('portal-webhooks/{portalWebhook}/directories', [PortalWebhookReferenceDirectoryController::class, 'store'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.write', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.store');
Route::patch('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}', [PortalWebhookReferenceDirectoryController::class, 'update'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.write', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.update');
Route::delete('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}', [PortalWebhookReferenceDirectoryController::class, 'destroy'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.write', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.destroy');
Route::post('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}/records', [PortalWebhookReferenceDirectoryController::class, 'storeRecord'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.write', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.records.store');
Route::post('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}/import', [PortalWebhookReferenceDirectoryController::class, 'importCsv'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.write', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.import');
Route::patch('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}/records/{referenceDirectoryRecord}', [PortalWebhookReferenceDirectoryController::class, 'updateRecord'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.write', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.records.update');
Route::delete('portal-webhooks/{portalWebhook}/directories/{referenceDirectory}/records/{referenceDirectoryRecord}', [PortalWebhookReferenceDirectoryController::class, 'destroyRecord'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:directories.write', 'module.enabled:directories'])
    ->name('portal-webhooks.directories.records.destroy');
Route::get('portal-webhooks/{portalWebhook}/edo/documents', [PortalWebhookEdoController::class, 'index'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:edo.read', 'module.enabled:edo'])
    ->name('portal-webhooks.edo.index');
Route::get('portal-webhooks/{portalWebhook}/edo/documents/{edoDocument}', [PortalWebhookEdoController::class, 'show'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:edo.read', 'module.enabled:edo'])
    ->name('portal-webhooks.edo.show');
Route::post('portal-webhooks/{portalWebhook}/edo/documents', [PortalWebhookEdoController::class, 'store'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:edo.write', 'module.enabled:edo'])
    ->name('portal-webhooks.edo.store');
Route::patch('portal-webhooks/{portalWebhook}/edo/documents/{edoDocument}', [PortalWebhookEdoController::class, 'update'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:edo.write', 'module.enabled:edo'])
    ->name('portal-webhooks.edo.update');
Route::post('portal-webhooks/{portalWebhook}/edo/documents/{edoDocument}/public-link', [PortalWebhookEdoController::class, 'issuePublicLink'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:edo.write', 'module.enabled:edo'])
    ->name('portal-webhooks.edo.public-link.store');
Route::get('portal-webhooks/{portalWebhook}/warehouses', [PortalWebhookWarehouseController::class, 'index'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:warehouses.read', 'module.enabled:warehouses'])
    ->name('portal-webhooks.warehouses.index');
Route::get('portal-webhooks/{portalWebhook}/warehouses/{warehouse}', [PortalWebhookWarehouseController::class, 'show'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:warehouses.read', 'module.enabled:warehouses'])
    ->name('portal-webhooks.warehouses.show');
Route::get('portal-webhooks/{portalWebhook}/warehouses/{warehouse}/items', [PortalWebhookWarehouseController::class, 'items'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:warehouses.read', 'module.enabled:warehouses'])
    ->name('portal-webhooks.warehouses.items');
Route::post('portal-webhooks/{portalWebhook}/warehouses', [PortalWebhookWarehouseController::class, 'store'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:warehouses.write', 'module.enabled:warehouses'])
    ->name('portal-webhooks.warehouses.store');
Route::patch('portal-webhooks/{portalWebhook}/warehouses/{warehouse}', [PortalWebhookWarehouseController::class, 'update'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:warehouses.write', 'module.enabled:warehouses'])
    ->name('portal-webhooks.warehouses.update');
Route::delete('portal-webhooks/{portalWebhook}/warehouses/{warehouse}', [PortalWebhookWarehouseController::class, 'destroy'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:warehouses.write', 'module.enabled:warehouses'])
    ->name('portal-webhooks.warehouses.destroy');
Route::get('portal-webhooks/{portalWebhook}/equipment', [PortalWebhookEquipmentController::class, 'index'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:equipment.read', 'module.enabled:equipment'])
    ->name('portal-webhooks.equipment.index');
Route::get('portal-webhooks/{portalWebhook}/equipment/{equipmentItem}', [PortalWebhookEquipmentController::class, 'show'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:equipment.read', 'module.enabled:equipment'])
    ->name('portal-webhooks.equipment.show');
Route::post('portal-webhooks/{portalWebhook}/equipment', [PortalWebhookEquipmentController::class, 'store'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:equipment.write', 'module.enabled:equipment'])
    ->name('portal-webhooks.equipment.store');
Route::patch('portal-webhooks/{portalWebhook}/equipment/{equipmentItem}', [PortalWebhookEquipmentController::class, 'update'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:equipment.write', 'module.enabled:equipment'])
    ->name('portal-webhooks.equipment.update');
Route::get('portal-webhooks/{portalWebhook}/tsd/scans', [PortalWebhookTsdController::class, 'index'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:tsd.read', 'module.enabled:tsd'])
    ->name('portal-webhooks.tsd.index');
Route::post('portal-webhooks/{portalWebhook}/tsd/scans', [PortalWebhookTsdController::class, 'store'])
    ->middleware(['module.enabled:webhooks', 'throttle:30,1', 'portal.webhook:tsd.write', 'module.enabled:tsd'])
    ->name('portal-webhooks.tsd.store');

Route::get('forms/public/{portalForm:public_token}', [PublicPortalFormController::class, 'show'])
    ->middleware('module.enabled:forms')
    ->name('forms.public.show');
Route::post('forms/public/{portalForm:public_token}', [PublicPortalFormController::class, 'submit'])
    ->middleware(['throttle:20,1', 'module.enabled:forms'])
    ->name('forms.public.submit');
Route::get('edo/public/{edoDocument:public_token}', [PublicEdoSigningController::class, 'show'])
    ->middleware('module.enabled:edo')
    ->name('edo.public.show');
Route::post('edo/public/{edoDocument:public_token}', [PublicEdoSigningController::class, 'sign'])
    ->middleware(['throttle:20,1', 'module.enabled:edo'])
    ->name('edo.public.sign');
Route::get('edo/public/{edoDocument:public_token}/download', [PublicEdoSigningController::class, 'download'])
    ->middleware('module.enabled:edo')
    ->name('edo.public.download');

Route::middleware(['auth'])->group(function () {
    Route::get('mobile/notifications/feed', MobileNotificationFeedController::class)->name('mobile.notifications.feed');
    Route::get('notifications', NotificationPageController::class)->name('notifications.index');
    Route::post('api/language', [LanguageController::class, 'update'])->name('language.update');
    Route::patch('notifications/read-all', [NotificationController::class, 'updateAll'])->name('notifications.read-all.update');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'update'])->name('notifications.read.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('documentation', DocumentationController::class)->name('documentation.index');
    Route::middleware(['module.enabled:company-structure', 'can:access-company-structure'])->group(function () {
        Route::get('company-structure', CompanyStructureController::class)->name('company-structure.index');
    });

    Route::middleware(['module.enabled:forms', 'can:access-forms'])->group(function () {
        Route::get('forms', [PortalFormController::class, 'index'])->name('forms.index');
        Route::post('forms', [PortalFormController::class, 'store'])->name('forms.store');
        Route::patch('forms/{portalForm}', [PortalFormController::class, 'update'])->name('forms.update');
        Route::delete('forms/{portalForm}', [PortalFormController::class, 'destroy'])->name('forms.destroy');
    });

    Route::middleware('module.enabled:contacts')->group(function () {
        Route::get('contacts', [ContactController::class, 'index'])
            ->middleware('can:access-contacts')
            ->name('contacts.index');
        Route::get('contacts/export', [ContactController::class, 'exportCsv'])
            ->middleware('can:access-contacts')
            ->name('contacts.export');
        Route::get('contacts/template', [ContactController::class, 'downloadCsvTemplate'])
            ->middleware('can:access-contacts')
            ->name('contacts.template');
        Route::post('contacts', [ContactController::class, 'store'])
            ->middleware('can:access-contacts')
            ->name('contacts.store');
        Route::post('contacts/import', [ContactController::class, 'importCsv'])
            ->middleware('can:access-contacts')
            ->name('contacts.import');
        Route::post('contacts/{contact}/comments', [ContactCommentController::class, 'store'])
            ->middleware('can:access-contacts')
            ->name('contacts.comments.store');
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])
            ->middleware('can:access-contacts')
            ->name('contacts.update');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])
            ->middleware('can:access-contacts')
            ->name('contacts.destroy');
    });

    Route::middleware(['module.enabled:directories', 'can:access-directories'])->group(function () {
        Route::get('directories', [ReferenceDirectoryController::class, 'index'])->name('directories.index');
        Route::get('directories/{referenceDirectory}', [ReferenceDirectoryController::class, 'show'])->name('directories.show');
        Route::get('directories/{referenceDirectory}/export', [ReferenceDirectoryController::class, 'exportCsv'])->name('directories.export');
        Route::get('directories/{referenceDirectory}/template', [ReferenceDirectoryController::class, 'downloadCsvTemplate'])->name('directories.template');
    });

    Route::middleware(['module.enabled:directories', 'can:manage-directories'])->group(function () {
        Route::post('directories', [ReferenceDirectoryController::class, 'store'])->name('directories.store');
        Route::patch('directories/{referenceDirectory}', [ReferenceDirectoryController::class, 'update'])->name('directories.update');
        Route::delete('directories/{referenceDirectory}', [ReferenceDirectoryController::class, 'destroy'])->name('directories.destroy');
        Route::post('directories/{referenceDirectory}/import', [ReferenceDirectoryController::class, 'importCsv'])->name('directories.import');
        Route::post('directories/{referenceDirectory}/records', [ReferenceDirectoryController::class, 'storeRecord'])->name('directories.records.store');
        Route::patch('directories/{referenceDirectory}/records/{referenceDirectoryRecord}', [ReferenceDirectoryController::class, 'updateRecord'])->name('directories.records.update');
        Route::delete('directories/{referenceDirectory}/records/{referenceDirectoryRecord}', [ReferenceDirectoryController::class, 'destroyRecord'])->name('directories.records.destroy');
    });

    Route::middleware(['module.enabled:files', 'can:access-files'])->group(function () {
        Route::get('files', [FileController::class, 'index'])->name('files.index');
        Route::post('files/directories', [FileController::class, 'storeDirectory'])->name('files.directories.store');
        Route::delete('files/directories/{fileDirectory}', [FileController::class, 'destroyDirectory'])->name('files.directories.destroy');
        Route::post('files/entries', [FileController::class, 'storeEntry'])->name('files.entries.store');
        Route::get('files/entries/{fileEntry}/download', [FileController::class, 'download'])->name('files.entries.download');
        Route::delete('files/entries/{fileEntry}', [FileController::class, 'destroyEntry'])->name('files.entries.destroy');
        Route::post('files/directories/{fileDirectory}/permissions', [FileController::class, 'storePermission'])->name('files.directories.permissions.store');
        Route::delete('files/directories/{fileDirectory}/permissions/{fileDirectoryPermission}', [FileController::class, 'destroyPermission'])->name('files.directories.permissions.destroy');
    });

    Route::middleware(['module.enabled:edo', 'can:access-edo'])->group(function () {
        Route::get('edo', [EdoDocumentController::class, 'index'])->name('edo.index');
        Route::post('edo', [EdoDocumentController::class, 'store'])->name('edo.store');
        Route::patch('edo/{edoDocument}', [EdoDocumentController::class, 'update'])->name('edo.update');
        Route::delete('edo/{edoDocument}', [EdoDocumentController::class, 'destroy'])->name('edo.destroy');
        Route::get('edo/{edoDocument}/file', [EdoDocumentController::class, 'downloadDocumentFile'])->name('edo.file.download');
        Route::post('edo/{edoDocument}/public-link', [EdoDocumentController::class, 'issuePublicLink'])->name('edo.public-link.store');
    });

    Route::middleware(['module.enabled:news', 'can:access-news'])->group(function () {
        Route::get('news', [NewsController::class, 'index'])->name('news.index');
        Route::get('news/{news}', [NewsController::class, 'show'])->name('news.show');
    });

    Route::middleware(['module.enabled:chats', 'can:access-chats'])->group(function () {
        Route::get('chats', ChatPageController::class)->name('chats.index');
        Route::get('chats/sidebar', [ChatSidebarController::class, 'index'])->name('chats.sidebar');
        Route::post('chats/direct', [ChatSidebarController::class, 'startDirect'])->name('chats.direct.store');
        Route::get('chats/users/{user}/profile', [ChatSidebarController::class, 'showUserProfile'])->name('chats.users.show');
        Route::post('chats/{chatConversation}/messages', [ChatMessageController::class, 'store'])->name('chats.messages.store');
        Route::patch('chats/{chatConversation}/messages/{chatMessage}', [ChatMessageController::class, 'update'])->name('chats.messages.update');
        Route::delete('chats/{chatConversation}/messages/{chatMessage}', [ChatMessageController::class, 'destroy'])->name('chats.messages.destroy');
        Route::get('chats/attachments/{chatMessageAttachment}/preview', [ChatMessageController::class, 'previewAttachment'])
            ->whereNumber('chatMessageAttachment')
            ->name('chats.attachments.preview');
        Route::get('chats/attachments/{chatMessageAttachment}/download', [ChatMessageController::class, 'downloadAttachment'])
            ->whereNumber('chatMessageAttachment')
            ->name('chats.attachments.download');
    });

    Route::middleware(['module.enabled:knowledge-bases', 'can:access-knowledge-bases'])->group(function () {
        Route::get('knowledge-bases', [KnowledgeBaseController::class, 'index'])->name('knowledge-bases.index');
        Route::get('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'show'])->name('knowledge-bases.show');
        Route::get('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'article'])->name('knowledge-bases.articles.show');
    });

    Route::middleware('module.enabled:funnels')->group(function () {
        Route::get('funnels', [CrmFunnelController::class, 'index'])->name('funnels.index');
        Route::get('funnels/{crmFunnel}', [CrmFunnelController::class, 'show'])->name('funnels.show');
        Route::post('funnels', [CrmFunnelController::class, 'store'])->name('funnels.store');
        Route::patch('funnels/{crmFunnel}', [CrmFunnelController::class, 'update'])->name('funnels.update');
        Route::delete('funnels/{crmFunnel}', [CrmFunnelController::class, 'destroy'])->name('funnels.destroy');
        Route::post('funnels/{crmFunnel}/stages', [CrmFunnelController::class, 'storeStage'])->name('funnels.stages.store');
        Route::patch('funnels/{crmFunnel}/stages/{crmFunnelStage}', [CrmFunnelController::class, 'updateStage'])->name('funnels.stages.update');
        Route::delete('funnels/{crmFunnel}/stages/{crmFunnelStage}', [CrmFunnelController::class, 'destroyStage'])->name('funnels.stages.destroy');
        Route::post('funnels/{crmFunnel}/deals', [CrmFunnelController::class, 'storeDeal'])->name('funnels.deals.store');
        Route::patch('funnels/{crmFunnel}/deals/{crmDeal}', [CrmFunnelController::class, 'updateDeal'])->name('funnels.deals.update');
        Route::patch('funnels/{crmFunnel}/deals/{crmDeal}/move', [CrmFunnelController::class, 'moveDeal'])->name('funnels.deals.move');
        Route::delete('funnels/{crmFunnel}/deals/{crmDeal}', [CrmFunnelController::class, 'destroyDeal'])->name('funnels.deals.destroy');
    });

    Route::middleware(['module.enabled:projects', 'can:access-projects'])->group(function () {
        Route::get('tasks', [ProjectController::class, 'tasksIndex'])->name('tasks.index');
        Route::get('tasks/export', [ProjectController::class, 'exportStandaloneTasks'])->name('tasks.export');
        Route::get('tasks/template', [ProjectController::class, 'downloadStandaloneTasksTemplate'])->name('tasks.template');
        Route::post('tasks/import', [ProjectController::class, 'importStandaloneTasks'])->name('tasks.import');
        Route::get('tasks/{projectTask}', [ProjectController::class, 'showStandaloneTask'])->name('tasks.show');
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects/task-stages', [ProjectController::class, 'storeTaskStage'])->name('projects.task-stages.store');
        Route::patch('projects/task-stages/move', [ProjectController::class, 'moveTaskStages'])->name('projects.task-stages.move');
        Route::patch('projects/task-stages/{projectTaskStage}', [ProjectController::class, 'updateTaskStage'])->name('projects.task-stages.update');
        Route::post('projects/tasks', [ProjectController::class, 'storeWorkspaceTask'])->name('projects.workspace.tasks.store');
        Route::get('projects/tasks/{projectTask}/conversation', [ProjectTaskConversationController::class, 'show'])->name('projects.workspace.tasks.conversation.show');
        Route::get('projects/tasks/{projectTask}', [ProjectController::class, 'showWorkspaceTask'])->name('projects.workspace.tasks.show');
        Route::patch('projects/tasks/{projectTask}/move', [ProjectController::class, 'moveWorkspaceTask'])->name('projects.workspace.tasks.move');
        Route::patch('projects/tasks/{projectTask}', [ProjectController::class, 'updateWorkspaceTask'])->name('projects.workspace.tasks.update');
        Route::delete('projects/tasks/{projectTask}', [ProjectController::class, 'destroyWorkspaceTask'])->name('projects.workspace.tasks.destroy');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('projects/{project}/tasks/export', [ProjectController::class, 'exportProjectTasks'])->name('projects.tasks.export');
        Route::get('projects/{project}/tasks/template', [ProjectController::class, 'downloadProjectTasksTemplate'])->name('projects.tasks.template');
        Route::post('projects/{project}/tasks/import', [ProjectController::class, 'importProjectTasks'])->name('projects.tasks.import');
        Route::get('projects/{project}/tasks/{projectTask}', [ProjectController::class, 'task'])->name('projects.tasks.show');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('projects/{project}/tasks', [ProjectController::class, 'storeTask'])->name('projects.tasks.store');
        Route::patch('projects/{project}/tasks/{projectTask}', [ProjectController::class, 'updateTask'])->name('projects.tasks.update');
        Route::delete('projects/{project}/tasks/{projectTask}', [ProjectController::class, 'destroyTask'])->name('projects.tasks.destroy');
    });

    Route::middleware(['module.enabled:production', 'can:access-production'])->group(function () {
        Route::get('production', [ProductionController::class, 'index'])->name('production.index');
        Route::get('production/{section}', [ProductionController::class, 'show'])->name('production.show');
    });

    Route::middleware(['module.enabled:warehouses', 'can:access-warehouses'])->group(function () {
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::post('warehouses/scan', [WarehouseController::class, 'scan'])->name('warehouses.scan');
    });

    Route::middleware(['module.enabled:equipment', 'can:access-equipment'])->group(function () {
        Route::get('equipment', [EquipmentController::class, 'index'])->name('equipment.index');
        Route::get('equipment/export', [EquipmentController::class, 'exportCsv'])->name('equipment.export');
        Route::get('equipment/template', [EquipmentController::class, 'downloadCsvTemplate'])->name('equipment.template');
        Route::post('equipment/import', [EquipmentController::class, 'importCsv'])->name('equipment.import');
        Route::post('equipment', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::patch('equipment/{equipmentItem}', [EquipmentController::class, 'update'])->name('equipment.update');
    });

    Route::middleware(['module.enabled:tsd', 'can:access-tsd'])->group(function () {
        Route::get('qr', [TsdController::class, 'scan'])->name('qr.index');
        Route::get('tsd', [TsdController::class, 'index'])->name('tsd.index');
        Route::post('tsd/scans', [TsdController::class, 'store'])->name('tsd.store');
    });
});

Route::middleware(['auth', 'verified', 'can:manage-knowledge-bases', 'module.enabled:knowledge-bases'])->group(function () {
    Route::post('knowledge-bases', [KnowledgeBaseController::class, 'store'])->name('knowledge-bases.store');
    Route::patch('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'update'])->name('knowledge-bases.update');
    Route::delete('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge-bases.destroy');
    Route::post('knowledge-bases/{knowledgeBase}/articles', [KnowledgeBaseController::class, 'storeArticle'])->name('knowledge-bases.articles.store');
    Route::patch('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'updateArticle'])->name('knowledge-bases.articles.update');
    Route::delete('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'destroyArticle'])->name('knowledge-bases.articles.destroy');
});

Route::middleware(['auth', 'verified', 'can:manage-news', 'module.enabled:news'])->group(function () {
    Route::post('news', [NewsController::class, 'store'])->name('news.store');
    Route::patch('news/{news}', [NewsController::class, 'update'])->name('news.update');
    Route::delete('news/{news}', [NewsController::class, 'destroy'])->name('news.destroy');
});

require __DIR__.'/settings.php';
