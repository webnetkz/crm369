<?php

use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ChatPageController;
use App\Http\Controllers\ChatSidebarController;
use App\Http\Controllers\ContactCommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CrmFunnelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EdoDocumentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPageController;
use App\Http\Controllers\PortalFormController;
use App\Http\Controllers\PortalWebhookContactController;
use App\Http\Controllers\PortalWebhookEdoController;
use App\Http\Controllers\PortalWebhookInvokeController;
use App\Http\Controllers\PortalWebhookUserController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskConversationController;
use App\Http\Controllers\PublicEdoSigningController;
use App\Http\Controllers\PublicPortalFormController;
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
    Route::get('notifications', NotificationPageController::class)->name('notifications.index');
    Route::post('api/language', [LanguageController::class, 'update'])->name('language.update');
    Route::patch('notifications/read-all', [NotificationController::class, 'updateAll'])->name('notifications.read-all.update');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'update'])->name('notifications.read.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::middleware('module.enabled:forms')->group(function () {
        Route::get('forms', [PortalFormController::class, 'index'])->name('forms.index');
        Route::post('forms', [PortalFormController::class, 'store'])->name('forms.store');
        Route::patch('forms/{portalForm}', [PortalFormController::class, 'update'])->name('forms.update');
        Route::delete('forms/{portalForm}', [PortalFormController::class, 'destroy'])->name('forms.destroy');
    });

    Route::middleware('module.enabled:contacts')->group(function () {
        Route::get('contacts', [ContactController::class, 'index'])
            ->middleware('can:access-contacts')
            ->name('contacts.index');
        Route::post('contacts', [ContactController::class, 'store'])
            ->middleware('can:access-contacts')
            ->name('contacts.store');
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

    Route::middleware('module.enabled:files')->group(function () {
        Route::get('files', [FileController::class, 'index'])->name('files.index');
        Route::post('files/directories', [FileController::class, 'storeDirectory'])->name('files.directories.store');
        Route::delete('files/directories/{fileDirectory}', [FileController::class, 'destroyDirectory'])->name('files.directories.destroy');
        Route::post('files/entries', [FileController::class, 'storeEntry'])->name('files.entries.store');
        Route::get('files/entries/{fileEntry}/download', [FileController::class, 'download'])->name('files.entries.download');
        Route::delete('files/entries/{fileEntry}', [FileController::class, 'destroyEntry'])->name('files.entries.destroy');
        Route::post('files/directories/{fileDirectory}/permissions', [FileController::class, 'storePermission'])->name('files.directories.permissions.store');
        Route::delete('files/directories/{fileDirectory}/permissions/{fileDirectoryPermission}', [FileController::class, 'destroyPermission'])->name('files.directories.permissions.destroy');
    });

    Route::middleware('module.enabled:edo')->group(function () {
        Route::get('edo', [EdoDocumentController::class, 'index'])->name('edo.index');
        Route::post('edo', [EdoDocumentController::class, 'store'])->name('edo.store');
        Route::patch('edo/{edoDocument}', [EdoDocumentController::class, 'update'])->name('edo.update');
        Route::delete('edo/{edoDocument}', [EdoDocumentController::class, 'destroy'])->name('edo.destroy');
        Route::get('edo/{edoDocument}/file', [EdoDocumentController::class, 'downloadDocumentFile'])->name('edo.file.download');
        Route::post('edo/{edoDocument}/public-link', [EdoDocumentController::class, 'issuePublicLink'])->name('edo.public-link.store');
    });

    Route::middleware('module.enabled:news')->group(function () {
        Route::get('news', [NewsController::class, 'index'])->name('news.index');
        Route::get('news/{news}', [NewsController::class, 'show'])->name('news.show');
    });

    Route::middleware('module.enabled:chats')->group(function () {
        Route::get('chats', ChatPageController::class)->name('chats.index');
        Route::get('chats/sidebar', [ChatSidebarController::class, 'index'])->name('chats.sidebar');
        Route::post('chats/direct', [ChatSidebarController::class, 'startDirect'])->name('chats.direct.store');
        Route::get('chats/users/{user}/profile', [ChatSidebarController::class, 'showUserProfile'])->name('chats.users.show');
        Route::post('chats/{chatConversation}/messages', [ChatMessageController::class, 'store'])->name('chats.messages.store');
        Route::get('chats/attachments/{chatMessageAttachment}/preview', [ChatMessageController::class, 'previewAttachment'])
            ->whereNumber('chatMessageAttachment')
            ->name('chats.attachments.preview');
        Route::get('chats/attachments/{chatMessageAttachment}/download', [ChatMessageController::class, 'downloadAttachment'])
            ->whereNumber('chatMessageAttachment')
            ->name('chats.attachments.download');
    });

    Route::middleware('module.enabled:knowledge-bases')->group(function () {
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

    Route::middleware('module.enabled:projects')->group(function () {
        Route::get('tasks', [ProjectController::class, 'tasksIndex'])->name('tasks.index');
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
        Route::get('projects/{project}/tasks/{projectTask}', [ProjectController::class, 'task'])->name('projects.tasks.show');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('projects/{project}/tasks', [ProjectController::class, 'storeTask'])->name('projects.tasks.store');
        Route::patch('projects/{project}/tasks/{projectTask}', [ProjectController::class, 'updateTask'])->name('projects.tasks.update');
        Route::delete('projects/{project}/tasks/{projectTask}', [ProjectController::class, 'destroyTask'])->name('projects.tasks.destroy');
    });

    Route::middleware('module.enabled:production')->group(function () {
        Route::get('production', [ProductionController::class, 'index'])->name('production.index');
        Route::get('production/{section}', [ProductionController::class, 'show'])->name('production.show');
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
