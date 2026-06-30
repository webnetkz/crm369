<?php

namespace App\Support;

use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use App\Models\User;
use App\Models\UserGroup;

class CrmFunnelPageData
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $viewer, ?CrmFunnel $activeFunnel = null): array
    {
        $funnels = CrmFunnel::query()
            ->visibleTo($viewer)
            ->withCount(['stages', 'deals'])
            ->withSum('deals as deals_amount_sum', 'amount')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $resolvedFunnel = $activeFunnel
            ? $funnels->firstWhere('id', $activeFunnel->id)
            : $funnels->first();

        if ($resolvedFunnel) {
            $resolvedFunnel->load([
                'groups:id,name,description',
                'stages' => fn ($query) => $query
                    ->with([
                        'deals' => fn ($dealQuery) => $dealQuery
                            ->with(['responsibleUser:id,name,last_name,email'])
                            ->orderBy('sort_order')
                            ->orderByDesc('updated_at'),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ]);
        }

        return [
            'funnels' => $funnels
                ->map(fn (CrmFunnel $funnel): array => [
                    'id' => $funnel->id,
                    'name' => $funnel->name,
                    'slug' => $funnel->slug,
                    'description' => $funnel->description,
                    'color' => $funnel->color,
                    'is_active' => $funnel->is_active,
                    'stages_count' => $funnel->stages_count,
                    'deals_count' => $funnel->deals_count,
                    'deals_amount_sum' => (float) ($funnel->deals_amount_sum ?? 0),
                ])
                ->values()
                ->all(),
            'activeFunnel' => $resolvedFunnel ? $this->activeFunnel($resolvedFunnel) : null,
            'availableUsers' => User::query()
                ->select(['id', 'name', 'last_name', 'email'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ])
                ->values()
                ->all(),
            'availableGroups' => $viewer->canManageFunnels()
                ? UserGroup::query()
                    ->withCount('users')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (UserGroup $group): array => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'display_name' => $group->displayName(),
                        'description' => $group->displayDescription(),
                        'users_count' => $group->users_count,
                    ])
                    ->values()
                    ->all()
                : [],
            'can' => [
                'manageFunnels' => $viewer->canManageFunnels(),
                'createDeals' => $resolvedFunnel ? $viewer->canAccessFunnel($resolvedFunnel) : false,
            ],
            'funnelOptions' => [
                'stageTypes' => collect(CrmFunnelStage::availableTypes())
                    ->map(fn (string $type): array => [
                        'value' => $type,
                        'label' => __('ui.funnels.stage_type_'.$type),
                    ])
                    ->values()
                    ->all(),
                'fieldTypes' => collect(CrmFunnel::availableFieldTypes())
                    ->map(fn (string $type): array => [
                        'value' => $type,
                        'label' => __('ui.funnels.field_type_'.$type),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeFunnel(CrmFunnel $funnel): array
    {
        $allDeals = $funnel->stages
            ->flatMap(fn (CrmFunnelStage $stage) => $stage->deals)
            ->values();

        return [
            'id' => $funnel->id,
            'name' => $funnel->name,
            'slug' => $funnel->slug,
            'description' => $funnel->description,
            'color' => $funnel->color,
            'is_active' => $funnel->is_active,
            'deal_fields' => $funnel->dealFieldDefinitions(),
            'group_ids' => $funnel->groups->pluck('id')->all(),
            'groups' => $funnel->groups
                ->map(fn (UserGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->displayName(),
                ])
                ->values()
                ->all(),
            'stages' => $funnel->stages
                ->map(fn (CrmFunnelStage $stage): array => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'type' => $stage->type,
                    'sort_order' => $stage->sort_order,
                    'deals_count' => $stage->deals->count(),
                    'deals' => $stage->deals
                        ->map(fn (CrmDeal $deal): array => [
                            'id' => $deal->id,
                            'crm_funnel_stage_id' => $deal->crm_funnel_stage_id,
                            'title' => $deal->title,
                            'company_name' => $deal->company_name,
                            'contact_name' => $deal->contact_name,
                            'contact_phone' => $deal->contact_phone,
                            'contact_email' => $deal->contact_email,
                            'amount' => $deal->amount !== null ? (float) $deal->amount : null,
                            'currency' => $deal->currency,
                            'expected_close_at' => $deal->expected_close_at?->toDateString(),
                            'description' => $deal->description,
                            'custom_fields' => $deal->custom_fields ?? [],
                            'sort_order' => $deal->sort_order,
                            'responsible_user' => $deal->responsibleUser
                                ? [
                                    'id' => $deal->responsibleUser->id,
                                    'name' => $deal->responsibleUser->name,
                                    'last_name' => $deal->responsibleUser->last_name,
                                    'email' => $deal->responsibleUser->email,
                                ]
                                : null,
                            'updated_at' => $deal->updated_at?->toISOString(),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'stats' => [
                'deals_count' => $allDeals->count(),
                'active_deals_count' => $funnel->stages
                    ->where('type', CrmFunnelStage::TYPE_OPEN)
                    ->sum(fn (CrmFunnelStage $stage): int => $stage->deals->count()),
                'won_deals_count' => $funnel->stages
                    ->where('type', CrmFunnelStage::TYPE_WON)
                    ->sum(fn (CrmFunnelStage $stage): int => $stage->deals->count()),
                'lost_deals_count' => $funnel->stages
                    ->where('type', CrmFunnelStage::TYPE_LOST)
                    ->sum(fn (CrmFunnelStage $stage): int => $stage->deals->count()),
                'amount_sum' => $allDeals->sum(fn (CrmDeal $deal): float => (float) ($deal->amount ?? 0)),
            ],
        ];
    }
}
