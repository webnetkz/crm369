<?php

namespace App\Http\Requests\Settings;

use App\Models\BusinessProcess;
use App\Support\BusinessProcessCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertBusinessProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $definition = BusinessProcess::normalizeDefinition(
            is_array($this->input('definition')) ? $this->input('definition') : null,
        );

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'description' => is_string($this->input('description'))
                ? trim(str_replace(["\r\n", "\r"], "\n", $this->input('description')))
                : null,
            'trigger_type' => is_string($this->input('trigger_type'))
                ? trim($this->input('trigger_type'))
                : $this->input('trigger_type'),
            'trigger_event' => is_string($this->input('trigger_event'))
                ? trim($this->input('trigger_event'))
                : $this->input('trigger_event'),
            'is_active' => $this->boolean('is_active', true),
            'definition' => $definition,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $catalog = app(BusinessProcessCatalog::class);

        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'trigger_type' => ['required', 'string', Rule::in(BusinessProcess::availableTriggerTypes())],
            'trigger_event' => ['required', 'string', 'max:120', Rule::in($catalog->triggerEventKeys())],
            'is_active' => ['required', 'boolean'],
            'definition' => ['required', 'array'],
            'definition.viewport' => ['required', 'array'],
            'definition.viewport.width' => ['required', 'integer', 'between:900,2400'],
            'definition.viewport.height' => ['required', 'integer', 'between:540,1600'],
            'definition.lanes' => ['required', 'array', 'list', 'min:1', 'max:8'],
            'definition.lanes.*.id' => ['required', 'string', 'max:60'],
            'definition.lanes.*.title' => ['required', 'string', 'max:120'],
            'definition.lanes.*.color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'definition.nodes' => ['required', 'array', 'list', 'min:2', 'max:60'],
            'definition.nodes.*.id' => ['required', 'string', 'max:80'],
            'definition.nodes.*.type' => ['required', 'string', Rule::in($catalog->nodeTypeKeys())],
            'definition.nodes.*.lane_id' => ['required', 'string', 'max:60'],
            'definition.nodes.*.label' => ['required', 'string', 'max:120'],
            'definition.nodes.*.description' => ['nullable', 'string', 'max:500'],
            'definition.nodes.*.x' => ['required', 'integer', 'min:0', 'max:2200'],
            'definition.nodes.*.y' => ['required', 'integer', 'min:0', 'max:1400'],
            'definition.nodes.*.config' => ['nullable', 'array'],
            'definition.nodes.*.config.code' => ['nullable', 'string', 'max:10000'],
            'definition.nodes.*.config.action_key' => ['nullable', 'string', 'max:200'],
            'definition.nodes.*.config.condition_expression' => ['nullable', 'string', 'max:2000'],
            'definition.nodes.*.config.notes' => ['nullable', 'string', 'max:2000'],
            'definition.nodes.*.config.input_mapping' => ['nullable', 'string', 'max:2000'],
            'definition.nodes.*.config.output_mapping' => ['nullable', 'string', 'max:2000'],
            'definition.nodes.*.config.retry_limit' => ['nullable', 'integer', 'between:0,10'],
            'definition.nodes.*.config.timeout_seconds' => ['nullable', 'integer', 'between:5,600'],
            'definition.edges' => ['required', 'array', 'list', 'min:1', 'max:120'],
            'definition.edges.*.id' => ['required', 'string', 'max:80'],
            'definition.edges.*.source' => ['required', 'string', 'max:80'],
            'definition.edges.*.target' => ['required', 'string', 'max:80'],
            'definition.edges.*.label' => ['nullable', 'string', 'max:120'],
            'definition.edges.*.condition' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $catalog = app(BusinessProcessCatalog::class);
                $definition = $this->validated('definition', []);
                $triggerType = $this->validated('trigger_type');
                $triggerEvent = collect($catalog->triggerEvents())
                    ->firstWhere('key', $this->validated('trigger_event'));

                if (is_array($triggerEvent) && $triggerEvent['type'] !== $triggerType) {
                    $validator->errors()->add('trigger_event', __('ui.business_processes.validation_trigger_type_mismatch'));
                }

                $lanes = collect($definition['lanes'] ?? [])->pluck('id')->filter()->values()->all();
                $nodes = collect($definition['nodes'] ?? [])->values();
                $nodeIds = $nodes->pluck('id')->filter()->values()->all();
                $nodeTypes = $nodes->pluck('type')->all();
                $apiActionKeys = $catalog->apiActionKeys();

                if (count($nodeIds) !== count(array_unique($nodeIds))) {
                    $validator->errors()->add('definition.nodes', __('ui.business_processes.validation_duplicate_nodes'));
                }

                if (count($lanes) !== count(array_unique($lanes))) {
                    $validator->errors()->add('definition.lanes', __('ui.business_processes.validation_duplicate_lanes'));
                }

                if (count(array_filter($nodeTypes, fn (string $type): bool => $type === 'startEvent')) !== 1) {
                    $validator->errors()->add('definition.nodes', __('ui.business_processes.validation_single_start'));
                }

                if (! in_array('endEvent', $nodeTypes, true)) {
                    $validator->errors()->add('definition.nodes', __('ui.business_processes.validation_end_required'));
                }

                $nodes->each(function (array $node) use ($validator, $lanes, $apiActionKeys): void {
                    if (! in_array($node['lane_id'], $lanes, true)) {
                        $validator->errors()->add('definition.nodes', __('ui.business_processes.validation_node_lane_missing'));
                    }

                    $config = is_array($node['config'] ?? null) ? $node['config'] : [];

                    if ($node['type'] === 'codeTask' && trim((string) ($config['code'] ?? '')) === '') {
                        $validator->errors()->add('definition.nodes', __('ui.business_processes.validation_code_required'));
                    }

                    if ($node['type'] === 'apiAction' && ! in_array((string) ($config['action_key'] ?? ''), $apiActionKeys, true)) {
                        $validator->errors()->add('definition.nodes', __('ui.business_processes.validation_action_required'));
                    }

                    if ($node['type'] === 'conditionGateway' && trim((string) ($config['condition_expression'] ?? '')) === '') {
                        $validator->errors()->add('definition.nodes', __('ui.business_processes.validation_condition_required'));
                    }
                });

                collect($definition['edges'] ?? [])->each(function (array $edge) use ($validator, $nodeIds): void {
                    if (! in_array($edge['source'], $nodeIds, true) || ! in_array($edge['target'], $nodeIds, true)) {
                        $validator->errors()->add('definition.edges', __('ui.business_processes.validation_edge_nodes_missing'));
                    }

                    if ($edge['source'] === $edge['target']) {
                        $validator->errors()->add('definition.edges', __('ui.business_processes.validation_edge_self_loop'));
                    }
                });
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return BusinessProcess::normalizeDefinition($this->validated('definition', []));
    }
}
