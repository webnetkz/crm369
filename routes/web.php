<?php

use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ChatPageController;
use App\Http\Controllers\ChatSidebarController;
use App\Http\Controllers\CrmFunnelController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPageController;
use App\Http\Controllers\PortalWebhookInvokeController;
use App\Http\Controllers\PortalWebhookUserController;
use App\Http\Controllers\PortalFormController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskConversationController;
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
    ->middleware(['throttle:30,1', 'portal.webhook'])
    ->name('portal-webhooks.invoke');
Route::get('portal-webhooks/{portalWebhook}/users', [PortalWebhookUserController::class, 'index'])
    ->middleware(['throttle:30,1', 'portal.webhook:users.read'])
    ->name('portal-webhooks.users.index');
Route::get('portal-webhooks/{portalWebhook}/users/{user}', [PortalWebhookUserController::class, 'show'])
    ->middleware(['throttle:30,1', 'portal.webhook:users.read'])
    ->name('portal-webhooks.users.show');

Route::get('forms/public/{portalForm:public_token}', [PublicPortalFormController::class, 'show'])
    ->name('forms.public.show');
Route::post('forms/public/{portalForm:public_token}', [PublicPortalFormController::class, 'submit'])
    ->middleware('throttle:20,1')
    ->name('forms.public.submit');

Route::middleware(['auth'])->group(function () {
    Route::get('notifications', NotificationPageController::class)->name('notifications.index');
    Route::post('api/language', [LanguageController::class, 'update'])->name('language.update');
    Route::patch('notifications/read-all', [NotificationController::class, 'updateAll'])->name('notifications.read-all.update');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'update'])->name('notifications.read.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('forms', [PortalFormController::class, 'index'])->name('forms.index');
    Route::post('forms', [PortalFormController::class, 'store'])->name('forms.store');
    Route::patch('forms/{portalForm}', [PortalFormController::class, 'update'])->name('forms.update');
    Route::delete('forms/{portalForm}', [PortalFormController::class, 'destroy'])->name('forms.destroy');
    Route::get('files', [FileController::class, 'index'])->name('files.index');
    Route::post('files/directories', [FileController::class, 'storeDirectory'])->name('files.directories.store');
    Route::delete('files/directories/{fileDirectory}', [FileController::class, 'destroyDirectory'])->name('files.directories.destroy');
    Route::post('files/entries', [FileController::class, 'storeEntry'])->name('files.entries.store');
    Route::get('files/entries/{fileEntry}/download', [FileController::class, 'download'])->name('files.entries.download');
    Route::delete('files/entries/{fileEntry}', [FileController::class, 'destroyEntry'])->name('files.entries.destroy');
    Route::post('files/directories/{fileDirectory}/permissions', [FileController::class, 'storePermission'])->name('files.directories.permissions.store');
    Route::delete('files/directories/{fileDirectory}/permissions/{fileDirectoryPermission}', [FileController::class, 'destroyPermission'])->name('files.directories.permissions.destroy');
    Route::inertia('news', 'news/Index')->name('news.index');
    Route::get('chats', ChatPageController::class)->name('chats.index');
    Route::get('knowledge-bases', [KnowledgeBaseController::class, 'index'])->name('knowledge-bases.index');
    Route::get('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'show'])->name('knowledge-bases.show');
    Route::get('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'article'])->name('knowledge-bases.articles.show');
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
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects/tasks', [ProjectController::class, 'storeWorkspaceTask'])->name('projects.workspace.tasks.store');
    Route::get('projects/tasks/{projectTask}/conversation', [ProjectTaskConversationController::class, 'show'])->name('projects.workspace.tasks.conversation.show');
    Route::get('projects/tasks/{projectTask}', [ProjectController::class, 'showWorkspaceTask'])->name('projects.workspace.tasks.show');
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
    Route::get('chats/sidebar', [ChatSidebarController::class, 'index'])->name('chats.sidebar');
    Route::post('chats/direct', [ChatSidebarController::class, 'startDirect'])->name('chats.direct.store');
    Route::post('chats/{chatConversation}/messages', [ChatMessageController::class, 'store'])->name('chats.messages.store');
});

Route::middleware(['auth', 'verified', 'can:manage-knowledge-bases'])->group(function () {
    Route::post('knowledge-bases', [KnowledgeBaseController::class, 'store'])->name('knowledge-bases.store');
    Route::patch('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'update'])->name('knowledge-bases.update');
    Route::delete('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge-bases.destroy');
    Route::post('knowledge-bases/{knowledgeBase}/articles', [KnowledgeBaseController::class, 'storeArticle'])->name('knowledge-bases.articles.store');
    Route::patch('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'updateArticle'])->name('knowledge-bases.articles.update');
    Route::delete('knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', [KnowledgeBaseController::class, 'destroyArticle'])->name('knowledge-bases.articles.destroy');
});

require __DIR__.'/settings.php';
