<?php

namespace App\Support;

use App\Models\BusinessProcess;
use Illuminate\Support\Str;

class BusinessProcessCatalog
{
    /**
     * @return array<int, array{key: string, title: string, description: string}>
     */
    public function triggerTypes(): array
    {
        return [
            [
                'key' => BusinessProcess::TRIGGER_TYPE_MANUAL,
                'title' => __('ui.business_processes.trigger_type_manual'),
                'description' => __('ui.business_processes.trigger_type_manual_description'),
            ],
            [
                'key' => BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT,
                'title' => __('ui.business_processes.trigger_type_domain_event'),
                'description' => __('ui.business_processes.trigger_type_domain_event_description'),
            ],
            [
                'key' => BusinessProcess::TRIGGER_TYPE_API_EVENT,
                'title' => __('ui.business_processes.trigger_type_api_event'),
                'description' => __('ui.business_processes.trigger_type_api_event_description'),
            ],
            [
                'key' => BusinessProcess::TRIGGER_TYPE_SCHEDULE,
                'title' => __('ui.business_processes.trigger_type_schedule'),
                'description' => __('ui.business_processes.trigger_type_schedule_description'),
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, type: string, category: string, title: string, description: string}>
     */
    public function triggerEvents(): array
    {
        return [
            $this->triggerEvent('manual.launch', BusinessProcess::TRIGGER_TYPE_MANUAL, 'manual', 'trigger_event_manual_launch', 'trigger_event_manual_launch_description'),
            $this->triggerEvent('contacts.created', BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT, 'crm', 'trigger_event_contacts_created', 'trigger_event_contacts_created_description'),
            $this->triggerEvent('contacts.updated', BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT, 'crm', 'trigger_event_contacts_updated', 'trigger_event_contacts_updated_description'),
            $this->triggerEvent('forms.submitted', BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT, 'portal', 'trigger_event_forms_submitted', 'trigger_event_forms_submitted_description'),
            $this->triggerEvent('projects.task.completed', BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT, 'projects', 'trigger_event_task_completed', 'trigger_event_task_completed_description'),
            $this->triggerEvent('funnels.deal.moved', BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT, 'crm', 'trigger_event_deal_moved', 'trigger_event_deal_moved_description'),
            $this->triggerEvent('api.webhook.received', BusinessProcess::TRIGGER_TYPE_API_EVENT, 'api', 'trigger_event_webhook_received', 'trigger_event_webhook_received_description'),
            $this->triggerEvent('api.portal.requested', BusinessProcess::TRIGGER_TYPE_API_EVENT, 'api', 'trigger_event_portal_requested', 'trigger_event_portal_requested_description'),
            $this->triggerEvent('integrations.telephony.completed', BusinessProcess::TRIGGER_TYPE_API_EVENT, 'integrations', 'trigger_event_telephony_completed', 'trigger_event_telephony_completed_description'),
            $this->triggerEvent('schedule.hourly', BusinessProcess::TRIGGER_TYPE_SCHEDULE, 'schedule', 'trigger_event_schedule_hourly', 'trigger_event_schedule_hourly_description'),
            $this->triggerEvent('schedule.daily', BusinessProcess::TRIGGER_TYPE_SCHEDULE, 'schedule', 'trigger_event_schedule_daily', 'trigger_event_schedule_daily_description'),
            $this->triggerEvent('schedule.cron', BusinessProcess::TRIGGER_TYPE_SCHEDULE, 'schedule', 'trigger_event_schedule_cron', 'trigger_event_schedule_cron_description'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function triggerEventKeys(): array
    {
        return collect($this->triggerEvents())->pluck('key')->all();
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     shape: string,
     *     accent: string,
     *     supports_code: bool,
     *     supports_action: bool,
     *     supports_condition: bool
     * }>
     */
    public function nodeTypes(): array
    {
        return [
            $this->nodeType('startEvent', 'node_type_start', 'node_type_start_description', 'circle', '#10B981'),
            $this->nodeType('userTask', 'node_type_user_task', 'node_type_user_task_description', 'rounded', '#3B82F6'),
            $this->nodeType('apiAction', 'node_type_api_action', 'node_type_api_action_description', 'rounded', '#0F766E', supportsAction: true),
            $this->nodeType('codeTask', 'node_type_code_task', 'node_type_code_task_description', 'rounded', '#7C3AED', supportsCode: true),
            $this->nodeType('conditionGateway', 'node_type_condition_gateway', 'node_type_condition_gateway_description', 'diamond', '#F59E0B', supportsCondition: true),
            $this->nodeType('notificationTask', 'node_type_notification_task', 'node_type_notification_task_description', 'rounded', '#EC4899'),
            $this->nodeType('endEvent', 'node_type_end', 'node_type_end_description', 'circle', '#EF4444'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function nodeTypeKeys(): array
    {
        return collect($this->nodeTypes())->pluck('key')->all();
    }

    /**
     * @return array<int, array{key: string, category: string, method: string, path: string, title: string, description: string, permission: string}>
     */
    public function apiActions(): array
    {
        return collect(app(ApiCatalog::class)->sections())
            ->flatMap(function (array $section): array {
                return collect($section['endpoints'])
                    ->map(function (array $endpoint) use ($section): array {
                        $key = $this->apiActionKey($endpoint['method'], $endpoint['path']);

                        return [
                            'key' => $key,
                            'category' => $section['title'],
                            'method' => $endpoint['method'],
                            'path' => $endpoint['path'],
                            'title' => $endpoint['summary'],
                            'description' => $section['description'],
                            'permission' => $endpoint['permission'],
                        ];
                    })
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function apiActionKeys(): array
    {
        return collect($this->apiActions())->pluck('key')->all();
    }

    /**
     * @return array<int, array{name: string, description: string, trigger_type: string, trigger_event: string, definition: array<string, mixed>}>
     */
    public function templates(): array
    {
        return [
            [
                'name' => __('ui.business_processes.template_lead_name'),
                'description' => __('ui.business_processes.template_lead_description'),
                'trigger_type' => BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT,
                'trigger_event' => 'contacts.created',
                'definition' => $this->leadQualificationDefinition(),
            ],
            [
                'name' => __('ui.business_processes.template_intake_name'),
                'description' => __('ui.business_processes.template_intake_description'),
                'trigger_type' => BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT,
                'trigger_event' => 'forms.submitted',
                'definition' => $this->portalIntakeDefinition(),
            ],
            [
                'name' => __('ui.business_processes.template_control_name'),
                'description' => __('ui.business_processes.template_control_description'),
                'trigger_type' => BusinessProcess::TRIGGER_TYPE_SCHEDULE,
                'trigger_event' => 'schedule.daily',
                'definition' => $this->operationsControlDefinition(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leadQualificationDefinition(): array
    {
        return [
            'viewport' => ['width' => 1320, 'height' => 720],
            'lanes' => BusinessProcess::defaultDefinition()['lanes'],
            'nodes' => [
                ['id' => 'lead_start', 'type' => 'startEvent', 'lane_id' => 'intake', 'label' => __('ui.business_processes.default_node_start'), 'description' => null, 'x' => 70, 'y' => 140, 'config' => []],
                ['id' => 'lead_score', 'type' => 'codeTask', 'lane_id' => 'processing', 'label' => __('ui.business_processes.template_node_score'), 'description' => null, 'x' => 300, 'y' => 120, 'config' => ['code' => "return [\n    'lead_score' => data_get(\$payload, 'amount', 0) > 100000 ? 'high' : 'normal',\n];", 'retry_limit' => 1, 'timeout_seconds' => 30]],
                ['id' => 'lead_gateway', 'type' => 'conditionGateway', 'lane_id' => 'processing', 'label' => __('ui.business_processes.template_node_gateway'), 'description' => null, 'x' => 560, 'y' => 130, 'config' => ['condition_expression' => "payload.lead_score === 'high'"]],
                ['id' => 'lead_notify', 'type' => 'notificationTask', 'lane_id' => 'delivery', 'label' => __('ui.business_processes.template_node_notify'), 'description' => null, 'x' => 780, 'y' => 70, 'config' => ['notes' => __('ui.business_processes.template_node_notify_notes')]],
                ['id' => 'lead_api', 'type' => 'apiAction', 'lane_id' => 'delivery', 'label' => __('ui.business_processes.template_node_sync'), 'description' => null, 'x' => 780, 'y' => 220, 'config' => ['action_key' => $this->apiActionKey('POST', '/api/v1/contacts'), 'timeout_seconds' => 30]],
                ['id' => 'lead_end', 'type' => 'endEvent', 'lane_id' => 'delivery', 'label' => __('ui.business_processes.default_node_end'), 'description' => null, 'x' => 1050, 'y' => 140, 'config' => []],
            ],
            'edges' => [
                ['id' => 'lead_edge_1', 'source' => 'lead_start', 'target' => 'lead_score', 'label' => null, 'condition' => null],
                ['id' => 'lead_edge_2', 'source' => 'lead_score', 'target' => 'lead_gateway', 'label' => null, 'condition' => null],
                ['id' => 'lead_edge_3', 'source' => 'lead_gateway', 'target' => 'lead_notify', 'label' => __('ui.business_processes.edge_yes'), 'condition' => "payload.lead_score === 'high'"],
                ['id' => 'lead_edge_4', 'source' => 'lead_gateway', 'target' => 'lead_api', 'label' => __('ui.business_processes.edge_no'), 'condition' => "payload.lead_score !== 'high'"],
                ['id' => 'lead_edge_5', 'source' => 'lead_notify', 'target' => 'lead_end', 'label' => null, 'condition' => null],
                ['id' => 'lead_edge_6', 'source' => 'lead_api', 'target' => 'lead_end', 'label' => null, 'condition' => null],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function portalIntakeDefinition(): array
    {
        return [
            'viewport' => ['width' => 1320, 'height' => 720],
            'lanes' => BusinessProcess::defaultDefinition()['lanes'],
            'nodes' => [
                ['id' => 'intake_start', 'type' => 'startEvent', 'lane_id' => 'intake', 'label' => __('ui.business_processes.default_node_start'), 'description' => null, 'x' => 70, 'y' => 130, 'config' => []],
                ['id' => 'intake_parse', 'type' => 'codeTask', 'lane_id' => 'processing', 'label' => __('ui.business_processes.template_node_parse'), 'description' => null, 'x' => 300, 'y' => 110, 'config' => ['code' => "return [\n    'contact_name' => data_get(\$payload, 'fields.name'),\n    'contact_phone' => data_get(\$payload, 'fields.phone'),\n];", 'retry_limit' => 0, 'timeout_seconds' => 25]],
                ['id' => 'intake_contact', 'type' => 'apiAction', 'lane_id' => 'processing', 'label' => __('ui.business_processes.template_node_create_contact'), 'description' => null, 'x' => 560, 'y' => 110, 'config' => ['action_key' => $this->apiActionKey('POST', '/api/v1/contacts'), 'timeout_seconds' => 30]],
                ['id' => 'intake_task', 'type' => 'userTask', 'lane_id' => 'delivery', 'label' => __('ui.business_processes.template_node_assign_manager'), 'description' => null, 'x' => 820, 'y' => 110, 'config' => ['notes' => __('ui.business_processes.template_node_assign_manager_notes')]],
                ['id' => 'intake_end', 'type' => 'endEvent', 'lane_id' => 'delivery', 'label' => __('ui.business_processes.default_node_end'), 'description' => null, 'x' => 1070, 'y' => 130, 'config' => []],
            ],
            'edges' => [
                ['id' => 'intake_edge_1', 'source' => 'intake_start', 'target' => 'intake_parse', 'label' => null, 'condition' => null],
                ['id' => 'intake_edge_2', 'source' => 'intake_parse', 'target' => 'intake_contact', 'label' => null, 'condition' => null],
                ['id' => 'intake_edge_3', 'source' => 'intake_contact', 'target' => 'intake_task', 'label' => null, 'condition' => null],
                ['id' => 'intake_edge_4', 'source' => 'intake_task', 'target' => 'intake_end', 'label' => null, 'condition' => null],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operationsControlDefinition(): array
    {
        return [
            'viewport' => ['width' => 1320, 'height' => 720],
            'lanes' => BusinessProcess::defaultDefinition()['lanes'],
            'nodes' => [
                ['id' => 'ops_start', 'type' => 'startEvent', 'lane_id' => 'intake', 'label' => __('ui.business_processes.default_node_start'), 'description' => null, 'x' => 70, 'y' => 130, 'config' => []],
                ['id' => 'ops_fetch', 'type' => 'apiAction', 'lane_id' => 'processing', 'label' => __('ui.business_processes.template_node_fetch_tasks'), 'description' => null, 'x' => 320, 'y' => 110, 'config' => ['action_key' => $this->apiActionKey('GET', '/api/v1/tasks/{projectTask}'), 'timeout_seconds' => 30]],
                ['id' => 'ops_filter', 'type' => 'conditionGateway', 'lane_id' => 'processing', 'label' => __('ui.business_processes.template_node_filter_overdue'), 'description' => null, 'x' => 600, 'y' => 125, 'config' => ['condition_expression' => 'payload.meta.total > 0']],
                ['id' => 'ops_alert', 'type' => 'notificationTask', 'lane_id' => 'delivery', 'label' => __('ui.business_processes.template_node_alert_team'), 'description' => null, 'x' => 830, 'y' => 70, 'config' => ['notes' => __('ui.business_processes.template_node_alert_team_notes')]],
                ['id' => 'ops_code', 'type' => 'codeTask', 'lane_id' => 'delivery', 'label' => __('ui.business_processes.template_node_archive'), 'description' => null, 'x' => 830, 'y' => 220, 'config' => ['code' => "return [\n    'status' => 'no_overdue_tasks',\n];", 'retry_limit' => 0, 'timeout_seconds' => 15]],
                ['id' => 'ops_end', 'type' => 'endEvent', 'lane_id' => 'delivery', 'label' => __('ui.business_processes.default_node_end'), 'description' => null, 'x' => 1080, 'y' => 130, 'config' => []],
            ],
            'edges' => [
                ['id' => 'ops_edge_1', 'source' => 'ops_start', 'target' => 'ops_fetch', 'label' => null, 'condition' => null],
                ['id' => 'ops_edge_2', 'source' => 'ops_fetch', 'target' => 'ops_filter', 'label' => null, 'condition' => null],
                ['id' => 'ops_edge_3', 'source' => 'ops_filter', 'target' => 'ops_alert', 'label' => __('ui.business_processes.edge_yes'), 'condition' => 'payload.meta.total > 0'],
                ['id' => 'ops_edge_4', 'source' => 'ops_filter', 'target' => 'ops_code', 'label' => __('ui.business_processes.edge_no'), 'condition' => 'payload.meta.total === 0'],
                ['id' => 'ops_edge_5', 'source' => 'ops_alert', 'target' => 'ops_end', 'label' => null, 'condition' => null],
                ['id' => 'ops_edge_6', 'source' => 'ops_code', 'target' => 'ops_end', 'label' => null, 'condition' => null],
            ],
        ];
    }

    /**
     * @return array{key: string, type: string, category: string, title: string, description: string}
     */
    private function triggerEvent(
        string $key,
        string $type,
        string $category,
        string $titleKey,
        string $descriptionKey,
    ): array {
        return [
            'key' => $key,
            'type' => $type,
            'category' => __('ui.business_processes.category_'.$category),
            'title' => __('ui.business_processes.'.$titleKey),
            'description' => __('ui.business_processes.'.$descriptionKey),
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     shape: string,
     *     accent: string,
     *     supports_code: bool,
     *     supports_action: bool,
     *     supports_condition: bool
     * }
     */
    private function nodeType(
        string $key,
        string $titleKey,
        string $descriptionKey,
        string $shape,
        string $accent,
        bool $supportsCode = false,
        bool $supportsAction = false,
        bool $supportsCondition = false,
    ): array {
        return [
            'key' => $key,
            'title' => __('ui.business_processes.'.$titleKey),
            'description' => __('ui.business_processes.'.$descriptionKey),
            'shape' => $shape,
            'accent' => $accent,
            'supports_code' => $supportsCode,
            'supports_action' => $supportsAction,
            'supports_condition' => $supportsCondition,
        ];
    }

    private function apiActionKey(string $method, string $path): string
    {
        return Str::slug($method.' '.$path, '.');
    }
}
