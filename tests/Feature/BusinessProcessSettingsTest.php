<?php

use App\Models\BusinessProcess;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function businessProcessDefinition(array $overrides = []): array
{
    return array_replace_recursive(BusinessProcess::defaultDefinition(), $overrides);
}

test('business process settings are visible only to the super admin', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $user = User::factory()->create();
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('settings.business-processes.index'))
        ->assertForbidden();

    $this->actingAs($superAdmin)
        ->get(route('settings.business-processes.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/BusinessProcesses')
            ->where('summary.total', 0)
            ->where('defaults.trigger_type', BusinessProcess::TRIGGER_TYPE_MANUAL)
            ->where('defaults.trigger_event', 'manual.launch')
            ->has('catalog.triggerTypes')
            ->has('catalog.triggerEvents')
            ->has('catalog.nodeTypes')
            ->has('catalog.apiActions')
            ->has('catalog.templates', 3)
        );
});

test('super admin can create a business process with code and api nodes', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $definition = businessProcessDefinition([
        'nodes' => [
            [
                'id' => 'node_start',
                'type' => 'startEvent',
                'lane_id' => 'intake',
                'label' => 'Start',
                'description' => null,
                'x' => 80,
                'y' => 110,
                'config' => [],
            ],
            [
                'id' => 'node_code',
                'type' => 'codeTask',
                'lane_id' => 'processing',
                'label' => 'Normalize payload',
                'description' => 'Map payload fields',
                'x' => 320,
                'y' => 110,
                'config' => [
                    'code' => "return ['ok' => true];",
                    'notes' => 'Transform external payload',
                    'retry_limit' => 1,
                    'timeout_seconds' => 30,
                ],
            ],
            [
                'id' => 'node_api',
                'type' => 'apiAction',
                'lane_id' => 'delivery',
                'label' => 'Create contact',
                'description' => null,
                'x' => 620,
                'y' => 110,
                'config' => [
                    'action_key' => 'post.apiv1contacts',
                    'retry_limit' => 2,
                    'timeout_seconds' => 45,
                ],
            ],
            [
                'id' => 'node_end',
                'type' => 'endEvent',
                'lane_id' => 'delivery',
                'label' => 'Done',
                'description' => null,
                'x' => 930,
                'y' => 110,
                'config' => [],
            ],
        ],
        'edges' => [
            ['id' => 'edge_1', 'source' => 'node_start', 'target' => 'node_code', 'label' => null, 'condition' => null],
            ['id' => 'edge_2', 'source' => 'node_code', 'target' => 'node_api', 'label' => null, 'condition' => null],
            ['id' => 'edge_3', 'source' => 'node_api', 'target' => 'node_end', 'label' => null, 'condition' => null],
        ],
    ]);

    $this->actingAs($superAdmin)
        ->from(route('settings.business-processes.index'))
        ->post(route('settings.business-processes.store'), [
            'name' => 'Lead Router',
            'description' => 'Routes new leads by event.',
            'trigger_type' => BusinessProcess::TRIGGER_TYPE_API_EVENT,
            'trigger_event' => 'api.webhook.received',
            'is_active' => true,
            'definition' => $definition,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $process = BusinessProcess::query()->where('name', 'Lead Router')->firstOrFail();

    expect($process->created_by_user_id)->toBe($superAdmin->id)
        ->and($process->updated_by_user_id)->toBe($superAdmin->id)
        ->and($process->trigger_type)->toBe(BusinessProcess::TRIGGER_TYPE_API_EVENT)
        ->and($process->trigger_event)->toBe('api.webhook.received')
        ->and($process->is_active)->toBeTrue()
        ->and($process->version)->toBe(1)
        ->and($process->slug)->toBe('lead-router')
        ->and(data_get($process->definition, 'nodes.1.type'))->toBe('codeTask')
        ->and(data_get($process->definition, 'nodes.1.config.code'))->toContain("return ['ok' => true];")
        ->and(data_get($process->definition, 'nodes.2.config.action_key'))->toBe('post.apiv1contacts');

    $this->actingAs($superAdmin)
        ->get(route('settings.business-processes.index', ['process' => $process->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 1)
            ->where('summary.active', 1)
            ->where('summary.automated', 1)
            ->where('summary.codeNodes', 1)
            ->where('activeProcess.id', $process->id)
            ->where('activeProcess.name', 'Lead Router')
            ->where('activeProcess.definition.nodes.1.type', 'codeTask')
            ->where('activeProcess.definition.nodes.2.config.action_key', 'post.apiv1contacts')
        );
});

test('super admin can update and delete a business process', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $process = BusinessProcess::factory()->create([
        'created_by_user_id' => $superAdmin->id,
        'updated_by_user_id' => $superAdmin->id,
        'name' => 'Original Flow',
        'slug' => 'original-flow',
        'trigger_type' => BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT,
        'trigger_event' => 'contacts.created',
    ]);

    $updatedDefinition = businessProcessDefinition([
        'nodes' => [
            [
                'id' => 'node_start',
                'type' => 'startEvent',
                'lane_id' => 'intake',
                'label' => 'Start',
                'description' => null,
                'x' => 80,
                'y' => 110,
                'config' => [],
            ],
            [
                'id' => 'node_gateway',
                'type' => 'conditionGateway',
                'lane_id' => 'processing',
                'label' => 'Has budget?',
                'description' => null,
                'x' => 430,
                'y' => 110,
                'config' => [
                    'condition_expression' => 'payload.amount > 1000',
                    'retry_limit' => 0,
                    'timeout_seconds' => 30,
                ],
            ],
            [
                'id' => 'node_end',
                'type' => 'endEvent',
                'lane_id' => 'delivery',
                'label' => 'Done',
                'description' => null,
                'x' => 830,
                'y' => 110,
                'config' => [],
            ],
        ],
        'edges' => [
            ['id' => 'edge_1', 'source' => 'node_start', 'target' => 'node_gateway', 'label' => null, 'condition' => null],
            ['id' => 'edge_2', 'source' => 'node_gateway', 'target' => 'node_end', 'label' => 'Yes', 'condition' => 'payload.amount > 1000'],
        ],
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('settings.business-processes.update', $process), [
            'name' => 'Budget Gate',
            'description' => 'Updated description',
            'trigger_type' => BusinessProcess::TRIGGER_TYPE_SCHEDULE,
            'trigger_event' => 'schedule.daily',
            'is_active' => false,
            'definition' => $updatedDefinition,
        ])
        ->assertRedirect();

    $process->refresh();

    expect($process->name)->toBe('Budget Gate')
        ->and($process->version)->toBe(2)
        ->and($process->is_active)->toBeFalse()
        ->and($process->trigger_type)->toBe(BusinessProcess::TRIGGER_TYPE_SCHEDULE)
        ->and(data_get($process->definition, 'nodes.1.type'))->toBe('conditionGateway')
        ->and(data_get($process->definition, 'nodes.1.config.condition_expression'))->toBe('payload.amount > 1000');

    $this->actingAs($superAdmin)
        ->delete(route('settings.business-processes.destroy', $process))
        ->assertRedirect(route('settings.business-processes.index'));

    $this->assertModelMissing($process);
});

test('business process validation rejects incomplete code and invalid trigger combinations', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $invalidDefinition = businessProcessDefinition([
        'nodes' => [
            [
                'id' => 'node_start',
                'type' => 'startEvent',
                'lane_id' => 'intake',
                'label' => 'Start',
                'description' => null,
                'x' => 80,
                'y' => 110,
                'config' => [],
            ],
            [
                'id' => 'node_code',
                'type' => 'codeTask',
                'lane_id' => 'processing',
                'label' => 'Broken code',
                'description' => null,
                'x' => 360,
                'y' => 110,
                'config' => [
                    'code' => '',
                    'retry_limit' => 0,
                    'timeout_seconds' => 30,
                ],
            ],
            [
                'id' => 'node_end',
                'type' => 'endEvent',
                'lane_id' => 'delivery',
                'label' => 'Done',
                'description' => null,
                'x' => 760,
                'y' => 110,
                'config' => [],
            ],
        ],
        'edges' => [
            ['id' => 'edge_1', 'source' => 'node_start', 'target' => 'node_code', 'label' => null, 'condition' => null],
            ['id' => 'edge_2', 'source' => 'node_code', 'target' => 'node_end', 'label' => null, 'condition' => null],
        ],
    ]);

    $this->actingAs($superAdmin)
        ->from(route('settings.business-processes.index'))
        ->post(route('settings.business-processes.store'), [
            'name' => 'Broken Process',
            'description' => 'Should fail.',
            'trigger_type' => BusinessProcess::TRIGGER_TYPE_MANUAL,
            'trigger_event' => 'api.webhook.received',
            'is_active' => true,
            'definition' => $invalidDefinition,
        ])
        ->assertRedirect(route('settings.business-processes.index'))
        ->assertSessionHasErrors([
            'trigger_event',
            'definition.nodes',
        ]);

    expect(BusinessProcess::query()->where('name', 'Broken Process')->exists())
        ->toBeFalse();
});

test('business process settings page contains the visual bpm editor primitives', function () {
    $page = file_get_contents(
        resource_path('js/pages/settings/BusinessProcesses.vue'),
    );

    expect($page)->toContain('canvasEdges')
        ->and($page)->toContain('props.catalog.nodeTypes')
        ->and($page)->toContain('connectSelectedNode')
        ->and($page)->toContain('duplicateSelectedNode')
        ->and($page)->toContain('selectedNodeType?.supports_code')
        ->and($page)->toContain('id="node-code"');
});
