<?php

use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use App\Models\User;
use App\Models\UserGroup;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin can create a funnel with default stages and custom fields', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create(['email' => 'super@example.com']);
    $group = UserGroup::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('funnels.store'), [
            'name' => 'Sales team',
            'slug' => 'sales-team',
            'description' => 'Main sales pipeline.',
            'color' => '#2563eb',
            'is_active' => true,
            'group_ids' => [$group->id],
            'deal_fields' => [
                [
                    'key' => 'source',
                    'label' => 'Source',
                    'type' => 'text',
                    'is_required' => true,
                ],
            ],
        ])
        ->assertRedirect();

    $funnel = CrmFunnel::query()->where('slug', 'sales-team')->firstOrFail();

    expect($funnel->groups()->pluck('user_groups.id')->all())
        ->toBe([$group->id])
        ->and($funnel->dealFieldDefinitions())
        ->toBe([
            [
                'key' => 'source',
                'label' => 'Source',
                'type' => 'text',
                'is_required' => true,
            ],
        ])
        ->and($funnel->stages()->count())
        ->toBe(3)
        ->and($funnel->stages()->where('type', CrmFunnelStage::TYPE_WON)->exists())
        ->toBeTrue();
});

test('group member can open an assigned funnel while outsider gets 404', function () {
    $group = UserGroup::factory()->create();
    $member = User::factory()->create(['user_group_id' => $group->id]);
    $outsider = User::factory()->create();

    $funnel = CrmFunnel::factory()->create();
    $funnel->groups()->sync([$group->id]);

    CrmFunnelStage::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'name' => 'Lead',
        'type' => CrmFunnelStage::TYPE_OPEN,
        'sort_order' => 0,
    ]);

    $this->actingAs($member)
        ->get(route('funnels.show', $funnel))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('funnels/Index')
            ->where('activeFunnel.id', $funnel->id)
        );

    $this->actingAs($outsider)
        ->get(route('funnels.show', $funnel))
        ->assertNotFound();
});

test('authenticated user without assigned funnel groups can still open funnels index but sees no accessible funnels', function () {
    $outsider = User::factory()->create();

    $funnel = CrmFunnel::factory()->create();
    $group = UserGroup::factory()->create();
    $funnel->groups()->sync([$group->id]);

    CrmFunnelStage::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'name' => 'Lead',
        'type' => CrmFunnelStage::TYPE_OPEN,
        'sort_order' => 0,
    ]);

    $this->actingAs($outsider)
        ->get(route('funnels.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('funnels/Index')
            ->where('funnels', [])
            ->where('activeFunnel', null)
            ->where('can.createDeals', false)
        );
});

test('group member can move a deal between funnel stages', function () {
    $group = UserGroup::factory()->create();
    $member = User::factory()->create(['user_group_id' => $group->id]);

    $funnel = CrmFunnel::factory()->create();
    $funnel->groups()->sync([$group->id]);

    $leadStage = CrmFunnelStage::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'name' => 'Lead',
        'type' => CrmFunnelStage::TYPE_OPEN,
        'sort_order' => 0,
    ]);

    $wonStage = CrmFunnelStage::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'name' => 'Won',
        'type' => CrmFunnelStage::TYPE_WON,
        'sort_order' => 1,
    ]);

    $deal = CrmDeal::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'crm_funnel_stage_id' => $leadStage->id,
    ]);

    $this->actingAs($member)
        ->from(route('funnels.show', $funnel))
        ->patch(route('funnels.deals.move', [$funnel, $deal]), [
            'crm_funnel_stage_id' => $wonStage->id,
        ])
        ->assertRedirect(route('funnels.show', $funnel));

    expect($deal->fresh()->crm_funnel_stage_id)->toBe($wonStage->id);
});

test('required custom deal field is validated on deal creation', function () {
    $group = UserGroup::factory()->create();
    $member = User::factory()->create(['user_group_id' => $group->id]);

    $funnel = CrmFunnel::factory()->create([
        'deal_fields' => [
            [
                'key' => 'source',
                'label' => 'Source',
                'type' => 'text',
                'is_required' => true,
            ],
        ],
    ]);
    $funnel->groups()->sync([$group->id]);

    $stage = CrmFunnelStage::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'name' => 'Lead',
        'type' => CrmFunnelStage::TYPE_OPEN,
        'sort_order' => 0,
    ]);

    $this->actingAs($member)
        ->from(route('funnels.show', $funnel))
        ->post(route('funnels.deals.store', $funnel), [
            'crm_funnel_stage_id' => $stage->id,
            'title' => 'Major contract',
            'custom_fields' => [],
        ])
        ->assertRedirect(route('funnels.show', $funnel))
        ->assertSessionHasErrors('custom_fields.source');
});

test('funnel sheet content keeps inner padding in the sidebar form', function () {
    $page = file_get_contents(resource_path('js/pages/funnels/Index.vue'));

    expect($page)->toContain('<SheetContent class="w-full overflow-y-auto p-6 sm:max-w-2xl">');
});

test('funnels sidebar item is wired for all users without a global access gate', function () {
    $menuItemModel = file_get_contents(app_path('Models/MenuItem.php'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($menuItemModel)->toContain("'title_key' => 'ui.funnels.title'")
        ->and($menuItemModel)->toContain("'url' => '/funnels'")
        ->and($menuItemModel)->toContain("'funnels',")
        ->and($sidebar)->toContain("isMenuItemVisible('funnels')")
        ->and($sidebar)->toContain('funnelsIndex()')
        ->and($sidebar)->not->toContain("page.props.auth.canAccessFunnels && isMenuItemVisible('funnels')");
});
