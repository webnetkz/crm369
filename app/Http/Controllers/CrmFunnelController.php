<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveCrmDealRequest;
use App\Http\Requests\StoreCrmDealRequest;
use App\Http\Requests\StoreCrmFunnelRequest;
use App\Http\Requests\StoreCrmFunnelStageRequest;
use App\Http\Requests\UpdateCrmDealRequest;
use App\Http\Requests\UpdateCrmFunnelRequest;
use App\Http\Requests\UpdateCrmFunnelStageRequest;
use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use App\Support\CrmFunnelPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmFunnelController extends Controller
{
    public function index(Request $request, CrmFunnelPageData $pageData): Response
    {
        return Inertia::render('funnels/Index', $pageData->build($request->user()));
    }

    public function show(Request $request, CrmFunnel $crmFunnel, CrmFunnelPageData $pageData): Response
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);

        return Inertia::render('funnels/Index', $pageData->build($request->user(), $visibleFunnel));
    }

    public function store(StoreCrmFunnelRequest $request): RedirectResponse
    {
        $user = $request->user();

        $funnel = CrmFunnel::query()->create([
            ...$request->funnelPayload(),
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        $funnel->groups()->sync($request->groupIds());
        $this->createDefaultStages($funnel);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.funnel_created_success')]);

        return redirect()->route('funnels.show', $funnel);
    }

    public function update(UpdateCrmFunnelRequest $request, CrmFunnel $crmFunnel): RedirectResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        abort_unless($request->user()->canManageFunnel($visibleFunnel), 403);

        $visibleFunnel->update([
            ...$request->funnelPayload(),
            'updated_by_user_id' => $request->user()->id,
        ]);

        $visibleFunnel->groups()->sync($request->groupIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.funnel_updated_success')]);

        return back();
    }

    public function destroy(Request $request, CrmFunnel $crmFunnel): RedirectResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        abort_unless($request->user()->canManageFunnel($visibleFunnel), 403);

        $visibleFunnel->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.funnel_deleted_success')]);

        return redirect()->route('funnels.index');
    }

    public function storeStage(StoreCrmFunnelStageRequest $request, CrmFunnel $crmFunnel): RedirectResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);

        $visibleFunnel->stages()->create([
            ...$request->stagePayload(),
            'sort_order' => $request->sortOrder() ?? (($visibleFunnel->stages()->max('sort_order') ?? -1) + 1),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.stage_created_success')]);

        return redirect()->route('funnels.show', $visibleFunnel);
    }

    public function updateStage(
        UpdateCrmFunnelStageRequest $request,
        CrmFunnel $crmFunnel,
        CrmFunnelStage $crmFunnelStage,
    ): RedirectResponse {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleStage = $this->visibleStage($visibleFunnel, $crmFunnelStage);

        $visibleStage->update($request->stagePayload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.stage_updated_success')]);

        return back();
    }

    public function destroyStage(Request $request, CrmFunnel $crmFunnel, CrmFunnelStage $crmFunnelStage): RedirectResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        abort_unless($request->user()->canManageFunnel($visibleFunnel), 403);

        $visibleStage = $this->visibleStage($visibleFunnel, $crmFunnelStage);

        if ($visibleStage->deals()->exists()) {
            return back()->withErrors([
                'stage' => __('ui.funnels.stage_has_deals_error'),
            ]);
        }

        $visibleStage->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.stage_deleted_success')]);

        return back();
    }

    public function storeDeal(StoreCrmDealRequest $request, CrmFunnel $crmFunnel): RedirectResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);

        CrmDeal::query()->create([
            ...$request->dealPayload($visibleFunnel),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
            'sort_order' => $request->sortOrder() ?? $this->nextDealSortOrder($visibleFunnel, (int) $request->validated('crm_funnel_stage_id')),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.deal_created_success')]);

        return redirect()->route('funnels.show', $visibleFunnel);
    }

    public function updateDeal(UpdateCrmDealRequest $request, CrmFunnel $crmFunnel, CrmDeal $crmDeal): RedirectResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleDeal = $this->visibleDeal($visibleFunnel, $crmDeal);

        $visibleDeal->update([
            ...$request->dealPayload($visibleFunnel),
            'updated_by_user_id' => $request->user()->id,
            'sort_order' => $request->sortOrder() ?? $visibleDeal->sort_order,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.deal_updated_success')]);

        return back();
    }

    public function moveDeal(MoveCrmDealRequest $request, CrmFunnel $crmFunnel, CrmDeal $crmDeal): RedirectResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleDeal = $this->visibleDeal($visibleFunnel, $crmDeal);

        $visibleDeal->update([
            'crm_funnel_stage_id' => $request->stageId(),
            'sort_order' => $request->sortOrder() ?? $this->nextDealSortOrder($visibleFunnel, $request->stageId()),
            'updated_by_user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function destroyDeal(Request $request, CrmFunnel $crmFunnel, CrmDeal $crmDeal): RedirectResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleDeal = $this->visibleDeal($visibleFunnel, $crmDeal);

        $visibleDeal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.funnels.deal_deleted_success')]);

        return back();
    }

    private function visibleFunnel(Request $request, CrmFunnel $funnel): CrmFunnel
    {
        abort_unless($request->user()->canAccessFunnel($funnel), 404);

        return $funnel;
    }

    private function visibleStage(CrmFunnel $funnel, CrmFunnelStage $stage): CrmFunnelStage
    {
        abort_unless($stage->crm_funnel_id === $funnel->id, 404);

        return $stage;
    }

    private function visibleDeal(CrmFunnel $funnel, CrmDeal $deal): CrmDeal
    {
        abort_unless($deal->crm_funnel_id === $funnel->id, 404);

        return $deal;
    }

    private function nextDealSortOrder(CrmFunnel $funnel, int $stageId): int
    {
        return (int) (($funnel->deals()->where('crm_funnel_stage_id', $stageId)->max('sort_order') ?? -1) + 1);
    }

    private function createDefaultStages(CrmFunnel $funnel): void
    {
        $funnel->stages()->createMany([
            [
                'name' => __('ui.funnels.default_stage_new'),
                'color' => '#64748B',
                'type' => CrmFunnelStage::TYPE_OPEN,
                'sort_order' => 0,
            ],
            [
                'name' => __('ui.funnels.default_stage_in_progress'),
                'color' => '#2563EB',
                'type' => CrmFunnelStage::TYPE_OPEN,
                'sort_order' => 1,
            ],
            [
                'name' => __('ui.funnels.default_stage_won'),
                'color' => '#16A34A',
                'type' => CrmFunnelStage::TYPE_WON,
                'sort_order' => 2,
            ],
        ]);
    }
}
