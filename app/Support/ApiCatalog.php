<?php

namespace App\Support;

use App\Models\ApiAccessToken;

class ApiCatalog
{
    /**
     * @return array<int, array{title: string, description: string, endpoints: array<int, array{method: string, path: string, summary: string, permission: string, access: string, content_type: string, target_user: string}>}>
     */
    public function sections(): array
    {
        return [
            [
                'title' => __('ui.api.section_profile'),
                'description' => __('ui.api.section_profile_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/profile', 'ui.api.endpoint_profile_show', ApiAccessToken::PERMISSION_PROFILE_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/profile', 'ui.api.endpoint_profile_update', ApiAccessToken::PERMISSION_PROFILE_WRITE, 'ui.api.access_scope_based', 'multipart/form-data', 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/profile/language', 'ui.api.endpoint_profile_language_update', ApiAccessToken::PERMISSION_PROFILE_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/profile/appearance', 'ui.api.endpoint_profile_appearance_update', ApiAccessToken::PERMISSION_PROFILE_WRITE, 'ui.api.access_scope_based', 'multipart/form-data', 'ui.api.target_user_supported'),
                ],
            ],
            [
                'title' => __('ui.api.section_notifications'),
                'description' => __('ui.api.section_notifications_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/notifications', 'ui.api.endpoint_notifications_index', ApiAccessToken::PERMISSION_NOTIFICATIONS_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/notifications/read-all', 'ui.api.endpoint_notifications_read_all', ApiAccessToken::PERMISSION_NOTIFICATIONS_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/notifications/{notification}/read', 'ui.api.endpoint_notifications_read_one', ApiAccessToken::PERMISSION_NOTIFICATIONS_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                ],
            ],
            [
                'title' => __('ui.api.section_chat'),
                'description' => __('ui.api.section_chat_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/chats', 'ui.api.endpoint_chats_index', ApiAccessToken::PERMISSION_CHAT_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('POST', '/api/v1/chats/direct', 'ui.api.endpoint_chats_direct_store', ApiAccessToken::PERMISSION_CHAT_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('POST', '/api/v1/chats/{chatConversation}/messages', 'ui.api.endpoint_chats_messages_store', ApiAccessToken::PERMISSION_CHAT_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                ],
            ],
            [
                'title' => __('ui.api.section_knowledge'),
                'description' => __('ui.api.section_knowledge_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/knowledge-bases', 'ui.api.endpoint_knowledge_index', ApiAccessToken::PERMISSION_KNOWLEDGE_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('GET', '/api/v1/knowledge-bases/{knowledgeBase}', 'ui.api.endpoint_knowledge_show', ApiAccessToken::PERMISSION_KNOWLEDGE_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('GET', '/api/v1/knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', 'ui.api.endpoint_knowledge_article_show', ApiAccessToken::PERMISSION_KNOWLEDGE_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('POST', '/api/v1/knowledge-bases', 'ui.api.endpoint_knowledge_store', ApiAccessToken::PERMISSION_KNOWLEDGE_WRITE, 'ui.api.access_super_admin_only'),
                    $this->endpoint('PATCH', '/api/v1/knowledge-bases/{knowledgeBase}', 'ui.api.endpoint_knowledge_update', ApiAccessToken::PERMISSION_KNOWLEDGE_WRITE, 'ui.api.access_super_admin_only'),
                    $this->endpoint('DELETE', '/api/v1/knowledge-bases/{knowledgeBase}', 'ui.api.endpoint_knowledge_destroy', ApiAccessToken::PERMISSION_KNOWLEDGE_WRITE, 'ui.api.access_super_admin_only'),
                    $this->endpoint('POST', '/api/v1/knowledge-bases/{knowledgeBase}/articles', 'ui.api.endpoint_knowledge_article_store', ApiAccessToken::PERMISSION_KNOWLEDGE_WRITE, 'ui.api.access_super_admin_only', 'multipart/form-data'),
                    $this->endpoint('PATCH', '/api/v1/knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', 'ui.api.endpoint_knowledge_article_update', ApiAccessToken::PERMISSION_KNOWLEDGE_WRITE, 'ui.api.access_super_admin_only', 'multipart/form-data'),
                    $this->endpoint('DELETE', '/api/v1/knowledge-bases/{knowledgeBase}/articles/{knowledgeBaseArticle}', 'ui.api.endpoint_knowledge_article_destroy', ApiAccessToken::PERMISSION_KNOWLEDGE_WRITE, 'ui.api.access_super_admin_only'),
                ],
            ],
            [
                'title' => __('ui.api.section_projects'),
                'description' => __('ui.api.section_projects_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/projects', 'ui.api.endpoint_projects_index', ApiAccessToken::PERMISSION_PROJECTS_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('POST', '/api/v1/projects', 'ui.api.endpoint_projects_store', ApiAccessToken::PERMISSION_PROJECTS_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('GET', '/api/v1/projects/{project}', 'ui.api.endpoint_projects_show', ApiAccessToken::PERMISSION_PROJECTS_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/projects/{project}', 'ui.api.endpoint_projects_update', ApiAccessToken::PERMISSION_PROJECTS_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('DELETE', '/api/v1/projects/{project}', 'ui.api.endpoint_projects_destroy', ApiAccessToken::PERMISSION_PROJECTS_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('POST', '/api/v1/tasks', 'ui.api.endpoint_tasks_store', ApiAccessToken::PERMISSION_TASKS_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('GET', '/api/v1/tasks/{projectTask}', 'ui.api.endpoint_tasks_show', ApiAccessToken::PERMISSION_TASKS_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/tasks/{projectTask}', 'ui.api.endpoint_tasks_update', ApiAccessToken::PERMISSION_TASKS_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('DELETE', '/api/v1/tasks/{projectTask}', 'ui.api.endpoint_tasks_destroy', ApiAccessToken::PERMISSION_TASKS_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                ],
            ],
            [
                'title' => __('ui.api.section_users'),
                'description' => __('ui.api.section_users_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/users', 'ui.api.endpoint_users_index', ApiAccessToken::PERMISSION_USERS_READ, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('GET', '/api/v1/users/{user}', 'ui.api.endpoint_users_show', ApiAccessToken::PERMISSION_USERS_READ, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('POST', '/api/v1/users', 'ui.api.endpoint_users_store', ApiAccessToken::PERMISSION_USERS_WRITE, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('PATCH', '/api/v1/users/{user}/profile', 'ui.api.endpoint_users_profile_update', ApiAccessToken::PERMISSION_USERS_WRITE, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('PATCH', '/api/v1/users/{user}/password', 'ui.api.endpoint_users_password_reset', ApiAccessToken::PERMISSION_USERS_WRITE, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('PATCH', '/api/v1/users/{user}/activation', 'ui.api.endpoint_users_activation_update', ApiAccessToken::PERMISSION_USERS_WRITE, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('PATCH', '/api/v1/users/{user}/group', 'ui.api.endpoint_users_group_update', ApiAccessToken::PERMISSION_USERS_WRITE, 'ui.api.access_super_admin_only'),
                ],
            ],
            [
                'title' => __('ui.api.section_groups'),
                'description' => __('ui.api.section_groups_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/groups', 'ui.api.endpoint_groups_index', ApiAccessToken::PERMISSION_GROUPS_READ, 'ui.api.access_super_admin_only'),
                    $this->endpoint('POST', '/api/v1/groups', 'ui.api.endpoint_groups_store', ApiAccessToken::PERMISSION_GROUPS_WRITE, 'ui.api.access_super_admin_only'),
                    $this->endpoint('PATCH', '/api/v1/groups/{userGroup}/permissions', 'ui.api.endpoint_groups_permissions_update', ApiAccessToken::PERMISSION_GROUPS_WRITE, 'ui.api.access_super_admin_only'),
                ],
            ],
            [
                'title' => __('ui.api.section_menu'),
                'description' => __('ui.api.section_menu_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/menu', 'ui.api.endpoint_menu_show', ApiAccessToken::PERMISSION_MENU_READ, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('POST', '/api/v1/menu/items', 'ui.api.endpoint_menu_store', ApiAccessToken::PERMISSION_MENU_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/menu/built-in/{key}/visibility', 'ui.api.endpoint_menu_built_in_visibility', ApiAccessToken::PERMISSION_MENU_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('PATCH', '/api/v1/menu/items/{menuItem}/visibility', 'ui.api.endpoint_menu_item_visibility', ApiAccessToken::PERMISSION_MENU_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                    $this->endpoint('DELETE', '/api/v1/menu/items/{menuItem}', 'ui.api.endpoint_menu_destroy', ApiAccessToken::PERMISSION_MENU_WRITE, 'ui.api.access_scope_based', targetUserKey: 'ui.api.target_user_supported'),
                ],
            ],
            [
                'title' => __('ui.api.section_platform'),
                'description' => __('ui.api.section_platform_description'),
                'endpoints' => [
                    $this->endpoint('GET', '/api/v1/portal', 'ui.api.endpoint_portal_show', ApiAccessToken::PERMISSION_PORTAL_READ, 'ui.api.access_super_admin_only'),
                    $this->endpoint('POST', '/api/v1/portal', 'ui.api.endpoint_portal_update', ApiAccessToken::PERMISSION_PORTAL_WRITE, 'ui.api.access_super_admin_only', 'multipart/form-data'),
                    $this->endpoint('GET', '/api/v1/integrations', 'ui.api.endpoint_integrations_index', ApiAccessToken::PERMISSION_INTEGRATIONS_READ, 'ui.api.access_super_admin_only'),
                    $this->endpoint('PATCH', '/api/v1/integrations/{messengerIntegration}', 'ui.api.endpoint_integrations_update', ApiAccessToken::PERMISSION_INTEGRATIONS_WRITE, 'ui.api.access_super_admin_only'),
                    $this->endpoint('GET', '/api/v1/webhooks', 'ui.api.endpoint_webhooks_index', ApiAccessToken::PERMISSION_WEBHOOKS_READ, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('POST', '/api/v1/webhooks', 'ui.api.endpoint_webhooks_store', ApiAccessToken::PERMISSION_WEBHOOKS_WRITE, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('PATCH', '/api/v1/webhooks/{portalWebhook}', 'ui.api.endpoint_webhooks_update', ApiAccessToken::PERMISSION_WEBHOOKS_WRITE, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('POST', '/api/v1/webhooks/{portalWebhook}/regenerate', 'ui.api.endpoint_webhooks_regenerate', ApiAccessToken::PERMISSION_WEBHOOKS_WRITE, 'ui.api.access_admin_or_super_admin'),
                    $this->endpoint('DELETE', '/api/v1/webhooks/{portalWebhook}', 'ui.api.endpoint_webhooks_destroy', ApiAccessToken::PERMISSION_WEBHOOKS_WRITE, 'ui.api.access_admin_or_super_admin'),
                ],
            ],
        ];
    }

    /**
     * @return array{method: string, path: string, summary: string, permission: string, access: string, content_type: string, target_user: string}
     */
    private function endpoint(
        string $method,
        string $path,
        string $summaryKey,
        string $permission,
        string $accessKey,
        string $contentType = 'application/json',
        string $targetUserKey = 'ui.api.target_user_not_supported',
    ): array {
        return [
            'method' => $method,
            'path' => $path,
            'summary' => __($summaryKey),
            'permission' => $permission,
            'access' => __($accessKey),
            'content_type' => $contentType,
            'target_user' => __($targetUserKey),
        ];
    }
}
