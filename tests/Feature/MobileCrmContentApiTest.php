<?php

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use App\Models\MobileAccessToken;
use App\Models\News;
use App\Models\PortalForm;
use App\Models\PortalFormSubmission;
use App\Models\PortalSetting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\CrmFunnelPageData;
use App\Support\DashboardPageData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Issue a mobile bearer token for the user. The resolved mobile user is cached by the guard
 * between requests of one test, so the guards are reset whenever another user signs in.
 */
function mobileCrmContentAs(User $user): string
{
    app('auth')->forgetGuards();

    return MobileAccessToken::issueToUser($user, 'android-crm-content-'.$user->id)['plain_text_token'];
}

function mobileCrmContentSuperAdmin(): User
{
    config(['admin.super_admin_email' => 'mobile-crm-content-admin@example.com']);

    return User::factory()->create([
        'email' => 'mobile-crm-content-admin@example.com',
        'language' => 'en',
    ]);
}

/**
 * @param  array<int, string>  $permissions
 * @param  array<int, string>  $configuredModules
 */
function mobileCrmContentGroupUser(array $permissions = [], array $configuredModules = []): User
{
    $group = UserGroup::factory()->create([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules($permissions, $configuredModules),
    ]);

    return User::factory()->create([
        'user_group_id' => $group->id,
        'language' => 'en',
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array{funnel: CrmFunnel, lead: CrmFunnelStage, won: CrmFunnelStage}
 */
function mobileCrmContentFunnelFor(UserGroup $group, array $attributes = []): array
{
    $funnel = CrmFunnel::factory()->create($attributes);
    $funnel->groups()->sync([$group->id]);

    return [
        'funnel' => $funnel,
        'lead' => CrmFunnelStage::factory()->create([
            'crm_funnel_id' => $funnel->id,
            'name' => 'Lead',
            'type' => CrmFunnelStage::TYPE_OPEN,
            'sort_order' => 0,
        ]),
        'won' => CrmFunnelStage::factory()->create([
            'crm_funnel_id' => $funnel->id,
            'name' => 'Won',
            'type' => CrmFunnelStage::TYPE_WON,
            'sort_order' => 1,
        ]),
    ];
}

/**
 * @return array{version: int, activeDashboardId: string, dashboards: list<array<string, mixed>>}
 */
function mobileCrmContentDashboardConfiguration(): array
{
    return [
        'version' => 1,
        'activeDashboardId' => 'sales_focus',
        'dashboards' => [
            [
                'id' => 'sales_focus',
                'name' => 'Sales and clients',
                'period' => 30,
                'density' => 'compact',
                'widgets' => [
                    ['id' => 'metrics', 'visible' => true, 'size' => 'full', 'chartType' => 'cards'],
                    ['id' => 'activity', 'visible' => true, 'size' => 'full', 'chartType' => 'line'],
                    ['id' => 'donuts', 'visible' => true, 'size' => 'wide', 'chartType' => 'progress'],
                    ['id' => 'bars', 'visible' => false, 'size' => 'wide', 'chartType' => 'bars'],
                    ['id' => 'radar', 'visible' => true, 'size' => 'standard', 'chartType' => 'progress'],
                    ['id' => 'highlights', 'visible' => true, 'size' => 'full', 'chartType' => 'cards'],
                ],
            ],
        ],
    ];
}

test('mobile crm content endpoints require a mobile session', function () {
    $this->getJson(route('mobile.v1.funnels.index'))->assertUnauthorized();
    $this->postJson(route('mobile.v1.funnels.store'))->assertUnauthorized();
    $this->getJson(route('mobile.v1.news.index'))->assertUnauthorized();
    $this->postJson(route('mobile.v1.news.store'))->assertUnauthorized();
    $this->getJson(route('mobile.v1.production.index'))->assertUnauthorized();
    $this->getJson(route('mobile.v1.dashboard.show'))->assertUnauthorized();
    $this->patchJson(route('mobile.v1.dashboard.configuration.update'))->assertUnauthorized();
});

test('mobile crm content endpoints are not found when their module is disabled', function () {
    $superAdmin = mobileCrmContentSuperAdmin();
    ['funnel' => $funnel] = mobileCrmContentFunnelFor(UserGroup::factory()->create());
    $news = News::factory()->create();

    PortalSetting::current()->update(['disabled_modules' => ['funnels', 'news', 'production']]);

    $this->withToken(mobileCrmContentAs($superAdmin));

    $this->getJson(route('mobile.v1.funnels.index'))->assertNotFound();
    $this->getJson(route('mobile.v1.funnels.show', $funnel))->assertNotFound();
    $this->postJson(route('mobile.v1.funnels.store'), ['name' => 'Hidden'])->assertNotFound();
    $this->getJson(route('mobile.v1.news.index'))->assertNotFound();
    $this->getJson(route('mobile.v1.news.show', $news))->assertNotFound();
    $this->postJson(route('mobile.v1.news.store'), ['title' => 'Hidden', 'content' => 'Hidden'])->assertNotFound();
    $this->deleteJson(route('mobile.v1.news.destroy', $news))->assertNotFound();
    $this->getJson(route('mobile.v1.production.index'))->assertNotFound();
    $this->getJson(route('mobile.v1.production.show', 'workshops'))->assertNotFound();
    $this->getJson(route('mobile.v1.dashboard.show'))->assertSuccessful();

    expect(CrmFunnel::query()->where('name', 'Hidden')->exists())->toBeFalse()
        ->and($news->fresh())->not->toBeNull();
});

test('the mobile funnels index and board mirror the web page props', function () {
    $member = mobileCrmContentGroupUser();
    [
        'funnel' => $funnel,
        'lead' => $lead,
        'won' => $won,
    ] = mobileCrmContentFunnelFor($member->group, ['name' => 'Alpha sales']);
    ['funnel' => $secondFunnel] = mobileCrmContentFunnelFor($member->group, ['name' => 'Beta partners']);
    $hiddenFunnel = CrmFunnel::factory()->create(['name' => 'Aardvark hidden']);

    $deal = CrmDeal::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'crm_funnel_stage_id' => $lead->id,
        'responsible_user_id' => $member->id,
        'amount' => 1500.5,
        'custom_fields' => ['source' => 'Website'],
    ]);
    $dealWithoutFields = CrmDeal::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'crm_funnel_stage_id' => $won->id,
        'custom_fields' => [],
    ]);

    $response = $this->withToken(mobileCrmContentAs($member))
        ->getJson(route('mobile.v1.funnels.index'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.funnels')
        ->assertJsonPath('data.funnels.0.id', $funnel->id)
        ->assertJsonPath('data.funnels.0.deals_count', 2)
        ->assertJsonPath('data.funnels.1.id', $secondFunnel->id)
        ->assertJsonPath('data.activeFunnel.id', $funnel->id)
        ->assertJsonPath('data.activeFunnel.group_ids', [$member->user_group_id])
        ->assertJsonPath('data.activeFunnel.stages.0.id', $lead->id)
        ->assertJsonPath('data.activeFunnel.stages.0.deals.0.id', $deal->id)
        ->assertJsonPath('data.activeFunnel.stages.0.deals.0.amount', 1500.5)
        ->assertJsonPath('data.activeFunnel.stages.0.deals.0.custom_fields.source', 'Website')
        ->assertJsonPath('data.activeFunnel.stages.0.deals.0.responsible_user.id', $member->id)
        ->assertJsonPath('data.activeFunnel.stages.1.deals.0.id', $dealWithoutFields->id)
        ->assertJsonPath('data.activeFunnel.stats.deals_count', 2)
        ->assertJsonPath('data.activeFunnel.stats.won_deals_count', 1)
        ->assertJsonPath('data.availableGroups', [])
        ->assertJsonPath('data.can.manageFunnels', false)
        ->assertJsonPath('data.can.createDeals', true)
        ->assertJsonStructure([
            'data' => [
                'funnels' => [['id', 'name', 'slug', 'description', 'color', 'is_active', 'stages_count', 'deals_count', 'deals_amount_sum']],
                'activeFunnel' => ['id', 'name', 'slug', 'description', 'color', 'is_active', 'deal_fields', 'group_ids', 'groups', 'stages', 'stats'],
                'availableUsers' => [['id', 'name', 'last_name', 'email']],
                'availableGroups',
                'can' => ['manageFunnels', 'createDeals'],
                'funnelOptions' => ['stageTypes' => [['value', 'label']], 'fieldTypes' => [['value', 'label']]],
            ],
        ]);

    expect($response->getContent())->toContain('"custom_fields":{}')
        ->and(collect($response->json('data.funnels'))->pluck('id'))->not->toContain($hiddenFunnel->id)
        ->and($response->json('data'))->toEqual(json_decode(json_encode(app(CrmFunnelPageData::class)->build($member->fresh())), true));

    $this->getJson(route('mobile.v1.funnels.show', $secondFunnel))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.funnels')
        ->assertJsonPath('data.activeFunnel.id', $secondFunnel->id)
        ->assertJsonPath('data.activeFunnel.stats.deals_count', 0);
});

test('mobile funnel visibility and deal access follow the web rules', function () {
    $member = mobileCrmContentGroupUser();
    $outsider = User::factory()->create(['language' => 'en']);
    ['funnel' => $funnel, 'lead' => $lead, 'won' => $won] = mobileCrmContentFunnelFor($member->group);
    $deal = CrmDeal::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'crm_funnel_stage_id' => $lead->id,
    ]);

    $this->withToken(mobileCrmContentAs($outsider));

    $this->getJson(route('mobile.v1.funnels.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.funnels', [])
        ->assertJsonPath('data.activeFunnel', null)
        ->assertJsonPath('data.can.createDeals', false);
    $this->getJson(route('mobile.v1.funnels.show', $funnel))->assertNotFound();
    $this->getJson(route('mobile.v1.funnels.show', 999999))->assertNotFound();
    $this->getJson(route('mobile.v1.funnels.deals.show', [$funnel, $deal]))->assertNotFound();
    $this->postJson(route('mobile.v1.funnels.deals.store', $funnel), [
        'crm_funnel_stage_id' => $lead->id,
        'title' => 'Intrusion',
    ])->assertForbidden();
    $this->patchJson(route('mobile.v1.funnels.deals.update', [$funnel, $deal]), [
        'crm_funnel_stage_id' => $lead->id,
        'title' => 'Intrusion',
    ])->assertForbidden();
    $this->patchJson(route('mobile.v1.funnels.deals.move', [$funnel, $deal]), [
        'crm_funnel_stage_id' => $won->id,
    ])->assertForbidden();
    $this->deleteJson(route('mobile.v1.funnels.deals.destroy', [$funnel, $deal]))->assertNotFound();

    $freshDeal = $deal->fresh();

    expect($freshDeal)->not->toBeNull()
        ->and($freshDeal->title)->not->toBe('Intrusion')
        ->and($freshDeal->crm_funnel_stage_id)->toBe($lead->id)
        ->and(CrmDeal::query()->count())->toBe(1);
});

test('super admins can create update and delete funnels through the mobile api', function () {
    $superAdmin = mobileCrmContentSuperAdmin();
    $group = UserGroup::factory()->create();

    $this->withToken(mobileCrmContentAs($superAdmin));

    $response = $this->postJson(route('mobile.v1.funnels.store'), [
        'name' => 'Mobile sales',
        'description' => 'Pipeline managed from the phone.',
        'color' => '#2563eb',
        'is_active' => true,
        'group_ids' => [$group->id],
        'deal_fields' => [
            ['key' => 'source', 'label' => 'Source', 'type' => 'text', 'is_required' => true],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.funnels.funnel_created_success', [], 'en'))
        ->assertJsonPath('data.name', 'Mobile sales')
        ->assertJsonPath('data.slug', 'mobile-sales')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.group_ids', [$group->id])
        ->assertJsonPath('data.groups.0.id', $group->id)
        ->assertJsonPath('data.deal_fields', [
            ['key' => 'source', 'label' => 'Source', 'type' => 'text', 'is_required' => true],
        ])
        ->assertJsonCount(3, 'data.stages')
        ->assertJsonPath('data.stages.0.name', trans('ui.funnels.default_stage_new', [], 'en'))
        ->assertJsonPath('data.stages.2.type', CrmFunnelStage::TYPE_WON)
        ->assertJsonPath('data.stages.0.deals', [])
        ->assertJsonPath('data.stats.deals_count', 0);

    $funnel = CrmFunnel::query()->findOrFail($response->json('data.id'));

    expect($funnel->created_by_user_id)->toBe($superAdmin->id)
        ->and($funnel->stages()->count())->toBe(3);

    $this->postJson(route('mobile.v1.funnels.store'), ['name' => 'Mobile sales'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');

    $this->patchJson(route('mobile.v1.funnels.update', $funnel), [
        'name' => 'Mobile sales EMEA',
        'slug' => 'mobile-sales-emea',
        'is_active' => false,
        'group_ids' => [],
        'deal_fields' => [],
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.funnels.funnel_updated_success', [], 'en'))
        ->assertJsonPath('data.id', $funnel->id)
        ->assertJsonPath('data.name', 'Mobile sales EMEA')
        ->assertJsonPath('data.slug', 'mobile-sales-emea')
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.group_ids', [])
        ->assertJsonPath('data.deal_fields', [])
        ->assertJsonCount(3, 'data.stages');

    expect($funnel->fresh()->updated_by_user_id)->toBe($superAdmin->id);

    $this->deleteJson(route('mobile.v1.funnels.destroy', $funnel))
        ->assertSuccessful()
        ->assertExactJson(['message' => trans('ui.funnels.funnel_deleted_success', [], 'en')]);

    expect(CrmFunnel::query()->whereKey($funnel->id)->exists())->toBeFalse();
});

test('funnel and stage management through the mobile api requires the manage funnels permission', function () {
    $member = mobileCrmContentGroupUser();
    ['funnel' => $funnel, 'lead' => $lead] = mobileCrmContentFunnelFor($member->group);

    $this->withToken(mobileCrmContentAs($member));

    $this->postJson(route('mobile.v1.funnels.store'), ['name' => 'Forbidden'])->assertForbidden();
    $this->patchJson(route('mobile.v1.funnels.update', $funnel), ['name' => 'Forbidden'])->assertForbidden();
    $this->deleteJson(route('mobile.v1.funnels.destroy', $funnel))->assertForbidden();
    $this->postJson(route('mobile.v1.funnels.stages.store', $funnel), ['name' => 'Forbidden'])->assertForbidden();
    $this->patchJson(route('mobile.v1.funnels.stages.update', [$funnel, $lead]), ['name' => 'Forbidden'])->assertForbidden();
    $this->deleteJson(route('mobile.v1.funnels.stages.destroy', [$funnel, $lead]))->assertForbidden();

    $manager = mobileCrmContentGroupUser([UserGroup::PERMISSION_MANAGE_FUNNELS]);

    $this->withToken(mobileCrmContentAs($manager));

    $this->getJson(route('mobile.v1.funnels.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.funnels', [])
        ->assertJsonPath('data.can.manageFunnels', true);
    $this->patchJson(route('mobile.v1.funnels.update', $funnel), ['name' => 'Renamed'])->assertNotFound();
    $this->deleteJson(route('mobile.v1.funnels.destroy', $funnel))->assertNotFound();

    expect($funnel->fresh()->name)->not->toBe('Renamed')
        ->and(CrmFunnel::query()->where('name', 'Forbidden')->exists())->toBeFalse()
        ->and($lead->fresh()->name)->toBe('Lead');
});

test('funnel managers can manage stages but cannot delete a stage that still has deals', function () {
    $manager = mobileCrmContentGroupUser([UserGroup::PERMISSION_MANAGE_FUNNELS]);
    ['funnel' => $funnel, 'won' => $won] = mobileCrmContentFunnelFor($manager->group);
    $deal = CrmDeal::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'crm_funnel_stage_id' => $won->id,
    ]);
    $foreignStage = CrmFunnelStage::factory()->create();

    $this->withToken(mobileCrmContentAs($manager));

    $stageId = $this->postJson(route('mobile.v1.funnels.stages.store', $funnel), [
        'name' => 'Negotiation',
        'color' => '#F59E0B',
        'type' => CrmFunnelStage::TYPE_OPEN,
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.funnels.stage_created_success', [], 'en'))
        ->assertJsonPath('data.crm_funnel_id', $funnel->id)
        ->assertJsonPath('data.name', 'Negotiation')
        ->assertJsonPath('data.color', '#F59E0B')
        ->assertJsonPath('data.type', CrmFunnelStage::TYPE_OPEN)
        ->assertJsonPath('data.sort_order', 2)
        ->assertJsonPath('data.deals_count', 0)
        ->assertJsonPath('data.deals', [])
        ->json('data.id');

    $this->postJson(route('mobile.v1.funnels.stages.store', $funnel), ['name' => 'Bad type', 'type' => 'unknown'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');

    $this->patchJson(route('mobile.v1.funnels.stages.update', [$funnel, $won]), [
        'name' => 'Closed won',
        'color' => '#16A34A',
        'type' => CrmFunnelStage::TYPE_WON,
        'sort_order' => 5,
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.funnels.stage_updated_success', [], 'en'))
        ->assertJsonPath('data.id', $won->id)
        ->assertJsonPath('data.name', 'Closed won')
        ->assertJsonPath('data.sort_order', 5)
        ->assertJsonPath('data.deals_count', 1)
        ->assertJsonPath('data.deals.0.id', $deal->id);

    $this->patchJson(route('mobile.v1.funnels.stages.update', [$funnel, $foreignStage]), [
        'name' => 'Foreign',
        'type' => CrmFunnelStage::TYPE_OPEN,
    ])->assertNotFound();
    $this->deleteJson(route('mobile.v1.funnels.stages.destroy', [$funnel, $foreignStage]))->assertNotFound();

    $this->deleteJson(route('mobile.v1.funnels.stages.destroy', [$funnel, $won]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['stage' => trans('ui.funnels.stage_has_deals_error', [], 'en')]);

    expect($won->fresh())->not->toBeNull()
        ->and($foreignStage->fresh()->name)->not->toBe('Foreign');

    $this->deleteJson(route('mobile.v1.funnels.stages.destroy', [$funnel, $stageId]))
        ->assertSuccessful()
        ->assertExactJson(['message' => trans('ui.funnels.stage_deleted_success', [], 'en')]);

    expect(CrmFunnelStage::query()->whereKey($stageId)->exists())->toBeFalse();
});

test('funnel members can create show update move and delete deals through the mobile api', function () {
    $member = mobileCrmContentGroupUser();
    ['funnel' => $funnel, 'lead' => $lead, 'won' => $won] = mobileCrmContentFunnelFor($member->group, [
        'deal_fields' => [
            ['key' => 'source', 'label' => 'Source', 'type' => 'text', 'is_required' => true],
            ['key' => 'budget', 'label' => 'Budget', 'type' => 'number', 'is_required' => false],
        ],
    ]);
    $responsible = User::factory()->create();
    $existingDeal = CrmDeal::factory()->create([
        'crm_funnel_id' => $funnel->id,
        'crm_funnel_stage_id' => $lead->id,
        'sort_order' => 0,
    ]);

    $this->withToken(mobileCrmContentAs($member));

    $response = $this->postJson(route('mobile.v1.funnels.deals.store', $funnel), [
        'crm_funnel_stage_id' => $lead->id,
        'responsible_user_id' => $responsible->id,
        'title' => '  Fleet contract  ',
        'company_name' => 'Acme',
        'contact_email' => 'buyer@example.com',
        'amount' => '12500.50',
        'currency' => 'kzt',
        'expected_close_at' => '2026-12-01',
        'custom_fields' => ['source' => 'Website', 'budget' => '15000'],
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.funnels.deal_created_success', [], 'en'))
        ->assertJsonPath('data.title', 'Fleet contract')
        ->assertJsonPath('data.crm_funnel_id', $funnel->id)
        ->assertJsonPath('data.crm_funnel_stage_id', $lead->id)
        ->assertJsonPath('data.stage', [
            'id' => $lead->id,
            'name' => 'Lead',
            'color' => $lead->color,
            'type' => CrmFunnelStage::TYPE_OPEN,
        ])
        ->assertJsonPath('data.company_name', 'Acme')
        ->assertJsonPath('data.contact_name', null)
        ->assertJsonPath('data.amount', 12500.5)
        ->assertJsonPath('data.currency', 'KZT')
        ->assertJsonPath('data.expected_close_at', '2026-12-01')
        ->assertJsonPath('data.custom_fields', ['source' => 'Website', 'budget' => '15000'])
        ->assertJsonPath('data.sort_order', 1)
        ->assertJsonPath('data.responsible_user.id', $responsible->id)
        ->assertJsonPath('data.creator.id', $member->id)
        ->assertJsonPath('data.updater.id', $member->id);

    $deal = CrmDeal::query()->findOrFail($response->json('data.id'));

    expect($response->json('data.created_at'))->toBeString()
        ->and($response->json('data.updated_at'))->toBeString();

    $this->postJson(route('mobile.v1.funnels.deals.store', $funnel), [
        'crm_funnel_stage_id' => $lead->id,
        'title' => 'Numeric amount deal',
        'amount' => 4200.75,
        'currency' => 'KZT',
        'custom_fields' => ['source' => 'Phone'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.amount', 4200.75);

    $this->getJson(route('mobile.v1.funnels.deals.show', [$funnel, $deal]))
        ->assertSuccessful()
        ->assertJsonPath('data.deal.id', $deal->id)
        ->assertJsonPath('data.deal.custom_fields.source', 'Website')
        ->assertJsonPath('data.deal.responsible_user.email', $responsible->email)
        ->assertJsonPath('data.funnel.id', $funnel->id)
        ->assertJsonPath('data.funnel.name', $funnel->name)
        ->assertJsonCount(2, 'data.stages')
        ->assertJsonPath('data.stages.0.id', $lead->id)
        ->assertJsonPath('data.stages.1.id', $won->id)
        ->assertJsonPath('data.stages.1.type', CrmFunnelStage::TYPE_WON)
        ->assertJsonPath('data.deal_fields.0', ['key' => 'source', 'label' => 'Source', 'type' => 'text', 'is_required' => true])
        ->assertJsonPath('data.deal_fields.1.type', 'number')
        ->assertJsonStructure([
            'data' => [
                'deal' => [
                    'id', 'crm_funnel_stage_id', 'title', 'company_name', 'contact_name', 'contact_phone',
                    'contact_email', 'amount', 'currency', 'expected_close_at', 'description', 'custom_fields',
                    'sort_order', 'responsible_user', 'updated_at', 'crm_funnel_id', 'stage', 'creator',
                    'updater', 'created_at',
                ],
                'funnel' => ['id', 'name', 'slug', 'color', 'is_active'],
                'stages' => [['id', 'name', 'color', 'type', 'sort_order']],
                'deal_fields' => [['key', 'label', 'type', 'is_required']],
                'available_users' => [['id', 'name', 'last_name', 'email']],
            ],
        ]);

    $this->patchJson(route('mobile.v1.funnels.deals.update', [$funnel, $deal]), [
        'crm_funnel_stage_id' => $lead->id,
        'title' => 'Fleet contract renewal',
        'amount' => '',
        'custom_fields' => ['source' => 'Referral'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.funnels.deal_updated_success', [], 'en'))
        ->assertJsonPath('data.title', 'Fleet contract renewal')
        ->assertJsonPath('data.amount', null)
        ->assertJsonPath('data.company_name', null)
        ->assertJsonPath('data.responsible_user', null)
        ->assertJsonPath('data.custom_fields', ['source' => 'Referral', 'budget' => null])
        ->assertJsonPath('data.sort_order', 1);

    $this->patchJson(route('mobile.v1.funnels.deals.move', [$funnel, $deal]), [
        'crm_funnel_stage_id' => $won->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $deal->id)
        ->assertJsonPath('data.crm_funnel_stage_id', $won->id)
        ->assertJsonPath('data.stage.id', $won->id)
        ->assertJsonPath('data.sort_order', 0)
        ->assertJsonPath('data.title', 'Fleet contract renewal');

    $this->deleteJson(route('mobile.v1.funnels.deals.destroy', [$funnel, $deal]))
        ->assertSuccessful()
        ->assertExactJson(['message' => trans('ui.funnels.deal_deleted_success', [], 'en')]);

    expect(CrmDeal::query()->whereKey($deal->id)->exists())->toBeFalse()
        ->and($existingDeal->fresh())->not->toBeNull();
});

test('mobile deal validation mirrors the web rules', function () {
    $member = mobileCrmContentGroupUser();
    ['funnel' => $funnel, 'lead' => $lead] = mobileCrmContentFunnelFor($member->group, [
        'deal_fields' => [
            ['key' => 'source', 'label' => 'Source', 'type' => 'text', 'is_required' => true],
        ],
    ]);
    ['funnel' => $otherFunnel, 'lead' => $otherStage] = mobileCrmContentFunnelFor($member->group);
    $otherDeal = CrmDeal::factory()->create([
        'crm_funnel_id' => $otherFunnel->id,
        'crm_funnel_stage_id' => $otherStage->id,
    ]);

    $this->withToken(mobileCrmContentAs($member));

    $this->postJson(route('mobile.v1.funnels.deals.store', $funnel), [
        'crm_funnel_stage_id' => $lead->id,
        'title' => 'Major contract',
        'custom_fields' => [],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('custom_fields.source');

    $this->postJson(route('mobile.v1.funnels.deals.store', $funnel), [
        'crm_funnel_stage_id' => $otherStage->id,
        'title' => 'Wrong stage',
        'custom_fields' => ['source' => 'Call'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('crm_funnel_stage_id');

    $this->getJson(route('mobile.v1.funnels.deals.show', [$funnel, $otherDeal]))->assertNotFound();
    $this->patchJson(route('mobile.v1.funnels.deals.update', [$funnel, $otherDeal]), [
        'crm_funnel_stage_id' => $lead->id,
        'title' => 'Hijacked',
        'custom_fields' => ['source' => 'Call'],
    ])->assertNotFound();
    $this->patchJson(route('mobile.v1.funnels.deals.move', [$funnel, $otherDeal]), [
        'crm_funnel_stage_id' => $lead->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('crm_funnel_stage_id');
    $this->deleteJson(route('mobile.v1.funnels.deals.destroy', [$funnel, $otherDeal]))->assertNotFound();

    $freshOtherDeal = $otherDeal->fresh();

    expect(CrmDeal::query()->where('crm_funnel_id', $funnel->id)->exists())->toBeFalse()
        ->and($freshOtherDeal)->not->toBeNull()
        ->and($freshOtherDeal->title)->not->toBe('Hijacked')
        ->and($freshOtherDeal->crm_funnel_stage_id)->toBe($otherStage->id);
});

test('the mobile news feed hides drafts from readers and opens published news', function () {
    $reader = User::factory()->create(['language' => 'en']);
    $published = News::factory()->create([
        'title' => 'Release notes',
        'slug' => 'release-notes',
        'image_path' => 'news/release-notes.png',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);
    $draft = News::factory()->create([
        'slug' => 'draft-roadmap',
        'is_published' => false,
        'published_at' => null,
    ]);

    $response = $this->withToken(mobileCrmContentAs($reader))
        ->getJson(route('mobile.v1.news.index', ['create' => 1]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.newsItems')
        ->assertJsonPath('data.newsItems.0.id', $published->id)
        ->assertJsonPath('data.newsItems.0.slug', 'release-notes')
        ->assertJsonPath('data.newsItems.0.image_url', Storage::disk('public')->url('news/release-notes.png'))
        ->assertJsonPath('data.activeNews', null)
        ->assertJsonPath('data.can.manage', false)
        ->assertJsonPath('data.editor.shouldOpenCreate', false)
        ->assertJsonStructure([
            'data' => [
                'newsItems' => [['id', 'title', 'slug', 'excerpt', 'image_url', 'is_published', 'published_at', 'updated_at']],
                'activeNews',
                'can' => ['manage'],
                'editor' => ['shouldOpenCreate'],
            ],
        ]);

    expect($response->json('data.newsItems.0.image_url'))->toStartWith('http');

    $this->getJson(route('mobile.v1.news.show', $published))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.newsItems')
        ->assertJsonPath('data.activeNews.slug', 'release-notes')
        ->assertJsonPath('data.activeNews.content', $published->content)
        ->assertJsonPath('data.activeNews.author', [
            'name' => $published->creator->name,
            'last_name' => $published->creator->last_name,
        ]);

    $this->getJson(route('mobile.v1.news.show', $draft))->assertNotFound();
    $this->getJson(route('mobile.v1.news.show', 'missing-news'))->assertNotFound();

    $superAdmin = mobileCrmContentSuperAdmin();

    $this->withToken(mobileCrmContentAs($superAdmin))
        ->getJson(route('mobile.v1.news.show', ['news' => $draft, 'create' => 1]))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.newsItems')
        ->assertJsonPath('data.activeNews.slug', 'draft-roadmap')
        ->assertJsonPath('data.activeNews.is_published', false)
        ->assertJsonPath('data.can.manage', true)
        ->assertJsonPath('data.editor.shouldOpenCreate', true);
});

test('news managers can create update and delete news with multipart image uploads', function () {
    Storage::fake('public');

    $manager = mobileCrmContentGroupUser([UserGroup::PERMISSION_MANAGE_NEWS]);

    $this->withToken(mobileCrmContentAs($manager))
        ->withHeader('Accept', 'application/json');

    $response = $this->post(route('mobile.v1.news.store'), [
        'title' => 'Mobile launch',
        'excerpt' => 'The native client is out.',
        'content' => "Line one.\r\n\r\nLine two.",
        'is_published' => '1',
        'image_file' => UploadedFile::fake()->image('launch.png'),
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.news.created_success', [], 'en'))
        ->assertJsonPath('data.title', 'Mobile launch')
        ->assertJsonPath('data.slug', 'mobile-launch')
        ->assertJsonPath('data.excerpt', 'The native client is out.')
        ->assertJsonPath('data.content', "Line one.\n\nLine two.")
        ->assertJsonPath('data.is_published', true)
        ->assertJsonPath('data.author', [
            'name' => $manager->name,
            'last_name' => $manager->last_name,
        ]);

    $news = News::query()->where('slug', 'mobile-launch')->firstOrFail();
    $firstImagePath = (string) $news->image_path;

    Storage::disk('public')->assertExists($firstImagePath);

    expect($response->json('data.id'))->toBe($news->id)
        ->and($response->json('data.image_url'))->toBe(url(Storage::disk('public')->url($firstImagePath)))
        ->and($response->json('data.image_url'))->toStartWith('http')
        ->and($response->json('data.published_at'))->toBeString()
        ->and($news->created_by_user_id)->toBe($manager->id);

    $this->post(route('mobile.v1.news.update', $news), [
        '_method' => 'PATCH',
        'title' => 'Mobile launch day',
        'slug' => 'mobile-launch',
        'content' => 'Updated body.',
        'is_published' => '0',
        'image_file' => UploadedFile::fake()->image('launch-day.png'),
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.news.updated_success', [], 'en'))
        ->assertJsonPath('data.title', 'Mobile launch day')
        ->assertJsonPath('data.slug', 'mobile-launch')
        ->assertJsonPath('data.excerpt', null)
        ->assertJsonPath('data.is_published', false)
        ->assertJsonPath('data.published_at', null);

    $news->refresh();
    $secondImagePath = (string) $news->image_path;

    Storage::disk('public')->assertMissing($firstImagePath);
    Storage::disk('public')->assertExists($secondImagePath);

    expect($secondImagePath)->not->toBe($firstImagePath);

    $this->patchJson(route('mobile.v1.news.update', $news), [
        'title' => 'Mobile launch day',
        'content' => 'Updated body.',
        'is_published' => true,
        'remove_image' => true,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.slug', 'mobile-launch-day')
        ->assertJsonPath('data.image_url', null)
        ->assertJsonPath('data.is_published', true);

    Storage::disk('public')->assertMissing($secondImagePath);

    $this->patchJson(route('mobile.v1.news.update', 'mobile-launch-day'), ['title' => '', 'content' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'content']);

    $this->deleteJson(route('mobile.v1.news.destroy', 'mobile-launch-day'))
        ->assertSuccessful()
        ->assertExactJson(['message' => trans('ui.news.deleted_success', [], 'en')]);

    expect(News::query()->whereKey($news->id)->exists())->toBeFalse();
});

test('news management through the mobile api requires the manage news permission', function () {
    $reader = mobileCrmContentGroupUser([UserGroup::PERMISSION_ACCESS_NEWS], ['news']);
    $news = News::factory()->create(['slug' => 'company-update', 'title' => 'Company update']);

    $this->withToken(mobileCrmContentAs($reader));

    $this->getJson(route('mobile.v1.news.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.can.manage', false);
    $this->postJson(route('mobile.v1.news.store'), ['title' => 'Forbidden', 'content' => 'Body'])->assertForbidden();
    $this->patchJson(route('mobile.v1.news.update', $news), ['title' => 'Forbidden', 'content' => 'Body'])->assertForbidden();
    $this->deleteJson(route('mobile.v1.news.destroy', $news))->assertForbidden();

    $restricted = mobileCrmContentGroupUser([], ['news']);

    $this->withToken(mobileCrmContentAs($restricted))
        ->getJson(route('mobile.v1.news.index'))
        ->assertForbidden();

    expect($news->fresh()->title)->toBe('Company update')
        ->and(News::query()->count())->toBe(1);
});

test('mobile production mirrors the web sections with their localized content', function () {
    $user = User::factory()->create(['language' => 'en']);

    $this->withToken(mobileCrmContentAs($user));

    $this->getJson(route('mobile.v1.production.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.activeSection', 'overview')
        ->assertJsonCount(7, 'data.sections')
        ->assertJsonPath('data.sections.1', 'workshops')
        ->assertJsonPath('data.labels.title', trans('ui.production.title', [], 'en'))
        ->assertJsonCount(7, 'data.sectionItems')
        ->assertJsonPath('data.sectionItems.6', [
            'key' => 'quality-control',
            'title' => trans('ui.production.sections.quality-control.title', [], 'en'),
            'summary' => trans('ui.production.sections.quality-control.summary', [], 'en'),
        ])
        ->assertJsonPath('data.activeSectionContent.key', 'overview')
        ->assertJsonPath('data.activeSectionContent.hero_title', trans('ui.production.sections.overview.hero_title', [], 'en'))
        ->assertJsonPath('data.activeSectionContent.metrics.0', trans('ui.production.sections.overview.metrics.0', [], 'en'))
        ->assertJsonStructure([
            'data' => [
                'labels' => ['title', 'description', 'eyebrow', 'navigation_title', 'navigation_description', 'cards_title', 'records_label', 'focus_label', 'next_steps_label'],
                'activeSectionContent' => [
                    'key', 'title', 'summary', 'hero_title', 'hero_description',
                    'metrics' => [['label', 'value', 'caption']],
                    'cards' => [['title', 'description']],
                    'records_title', 'records_description',
                    'records' => [['title', 'badge', 'description']],
                    'focus_title', 'focus_points', 'next_steps_title', 'next_steps',
                ],
            ],
        ]);

    $this->getJson(route('mobile.v1.production.show', 'quality-control'))
        ->assertSuccessful()
        ->assertJsonPath('data.activeSection', 'quality-control')
        ->assertJsonPath('data.sections.6', 'quality-control')
        ->assertJsonPath('data.activeSectionContent.title', trans('ui.production.sections.quality-control.title', [], 'en'))
        ->assertJsonPath('data.activeSectionContent.focus_points', trans('ui.production.sections.quality-control.focus_points', [], 'en'));

    $this->getJson(route('mobile.v1.production.show', 'warehouses'))->assertNotFound();

    $restricted = mobileCrmContentGroupUser([], ['production']);

    $this->withToken(mobileCrmContentAs($restricted))
        ->getJson(route('mobile.v1.production.index'))
        ->assertForbidden();
});

test('the mobile dashboard returns the same props as the web dashboard', function () {
    $user = User::factory()->create(['language' => 'ru']);
    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);
    $project->members()->sync([$user->id]);

    Project::factory()->create();

    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'status' => ProjectTask::STATUS_DONE,
        'completed_at' => now(),
    ]);
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'due_at' => now()->subDay(),
    ]);

    $conversation = ChatConversation::factory()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $user->id,
    ]);
    ChatConversationParticipant::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
    ]);
    ChatMessage::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
    ]);

    $form = PortalForm::factory()->create(['is_active' => true]);
    PortalFormSubmission::factory()->create(['portal_form_id' => $form->id]);

    $response = $this->withToken(mobileCrmContentAs($user))
        ->getJson(route('mobile.v1.dashboard.show'))
        ->assertSuccessful()
        ->assertJsonCount(4, 'data.dashboardStats.cards')
        ->assertJsonCount(3, 'data.dashboardStats.donuts')
        ->assertJsonCount(3, 'data.dashboardStats.activity.series')
        ->assertJsonCount(7, 'data.dashboardStats.activity.labels')
        ->assertJsonCount(4, 'data.dashboardStats.highlights')
        ->assertJsonPath('data.dashboardConfiguration.version', 1)
        ->assertJsonPath('data.dashboardConfiguration.activeDashboardId', 'overview')
        ->assertJsonCount(6, 'data.dashboardConfiguration.dashboards.0.widgets')
        ->assertJsonStructure([
            'data' => [
                'dashboardStats' => [
                    'eyebrow',
                    'subtitle',
                    'cards' => [['title', 'value', 'helper', 'icon']],
                    'donuts' => [['title', 'subtitle', 'total', 'totalLabel', 'highlight', 'highlightLabel', 'segments' => [['label', 'value', 'color']]]],
                    'activity' => ['title', 'subtitle', 'labels', 'series' => [['label', 'values', 'color']]],
                    'bars' => ['title', 'subtitle', 'items' => [['label', 'value', 'color']]],
                    'radar' => ['title', 'subtitle', 'items' => [['label', 'value', 'helper']]],
                    'highlights' => [['label', 'value', 'helper']],
                ],
                'dashboardConfiguration' => [
                    'version',
                    'activeDashboardId',
                    'dashboards' => [['id', 'name', 'period', 'density', 'widgets' => [['id', 'visible', 'size', 'chartType']]]],
                ],
            ],
        ]);

    $donuts = collect($response->json('data.dashboardStats.donuts'))->keyBy('title');

    expect(collect($response->json('data.dashboardStats.cards'))->pluck('title')->all())
        ->toContain('Проекты', 'Задачи', 'Коммуникации', 'Формы')
        ->and($donuts['Статусы задач']['total'])->toBe(2)
        ->and($response->json('data'))->toEqual(json_decode(json_encode(app(DashboardPageData::class)->build($user->fresh())), true));
});

test('mobile users can update their own dashboard configuration', function () {
    $user = User::factory()->create(['language' => 'en']);
    $otherUser = User::factory()->create();
    $configuration = mobileCrmContentDashboardConfiguration();

    $this->withToken(mobileCrmContentAs($user));

    $this->patchJson(route('mobile.v1.dashboard.configuration.update'), ['configuration' => $configuration])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.dashboard.configuration_saved', [], 'en'))
        ->assertJsonPath('data.dashboardConfiguration', $configuration)
        ->assertJsonCount(30, 'data.dashboardStats.activity.labels');

    expect($user->refresh()->dashboard_configuration)->toMatchArray($configuration)
        ->and($otherUser->refresh()->dashboard_configuration)->toBeNull();

    $invalidConfiguration = mobileCrmContentDashboardConfiguration();
    $invalidConfiguration['activeDashboardId'] = 'missing';
    $invalidConfiguration['dashboards'][0]['widgets'][1]['chartType'] = 'radar';

    $this->patchJson(route('mobile.v1.dashboard.configuration.update'), ['configuration' => $invalidConfiguration])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'configuration.activeDashboardId',
            'configuration.dashboards.0.widgets.1.chartType',
        ]);

    $this->getJson(route('mobile.v1.dashboard.show'))
        ->assertSuccessful()
        ->assertJsonPath('data.dashboardConfiguration.activeDashboardId', 'sales_focus')
        ->assertJsonPath('data.dashboardConfiguration.dashboards.0.period', 30);
});
