<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
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
use App\Models\User;
use App\Support\CrmFunnelBoardManager;
use App\Support\CrmFunnelPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use stdClass;

class MobileCrmFunnelController extends Controller
{
    /**
     * The funnels visible to the user and the board of the first visible funnel (the web funnels page props).
     */
    public function index(Request $request, CrmFunnelPageData $pageData): JsonResponse
    {
        return response()->json([
            'data' => $this->pageData($pageData->build($request->user())),
        ]);
    }

    /**
     * The funnels visible to the user and the board of the requested funnel.
     */
    public function show(Request $request, CrmFunnel $crmFunnel, CrmFunnelPageData $pageData): JsonResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);

        return response()->json([
            'data' => $this->pageData($pageData->build($request->user(), $visibleFunnel)),
        ]);
    }

    public function store(
        StoreCrmFunnelRequest $request,
        CrmFunnelBoardManager $boardManager,
        CrmFunnelPageData $pageData,
    ): JsonResponse {
        $user = $request->user();

        $funnel = CrmFunnel::query()->create([
            ...$request->funnelPayload(),
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        $funnel->groups()->sync($request->groupIds());
        $boardManager->createDefaultStages($funnel);

        return response()->json([
            'message' => __('ui.funnels.funnel_created_success'),
            'data' => $this->funnelBoard($funnel, $pageData),
        ], 201);
    }

    public function update(UpdateCrmFunnelRequest $request, CrmFunnel $crmFunnel, CrmFunnelPageData $pageData): JsonResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        abort_unless($request->user()->canManageFunnel($visibleFunnel), 403);

        $visibleFunnel->update([
            ...$request->funnelPayload(),
            'updated_by_user_id' => $request->user()->id,
        ]);

        $visibleFunnel->groups()->sync($request->groupIds());

        return response()->json([
            'message' => __('ui.funnels.funnel_updated_success'),
            'data' => $this->funnelBoard($visibleFunnel, $pageData),
        ]);
    }

    public function destroy(Request $request, CrmFunnel $crmFunnel): JsonResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        abort_unless($request->user()->canManageFunnel($visibleFunnel), 403);

        $visibleFunnel->delete();

        return response()->json([
            'message' => __('ui.funnels.funnel_deleted_success'),
        ]);
    }

    public function storeStage(
        StoreCrmFunnelStageRequest $request,
        CrmFunnel $crmFunnel,
        CrmFunnelBoardManager $boardManager,
        CrmFunnelPageData $pageData,
    ): JsonResponse {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);

        $stage = $visibleFunnel->stages()->create([
            ...$request->stagePayload(),
            'sort_order' => $request->sortOrder() ?? $boardManager->nextStageSortOrder($visibleFunnel),
        ]);

        return response()->json([
            'message' => __('ui.funnels.stage_created_success'),
            'data' => $this->stage($stage, $pageData),
        ], 201);
    }

    public function updateStage(
        UpdateCrmFunnelStageRequest $request,
        CrmFunnel $crmFunnel,
        CrmFunnelStage $crmFunnelStage,
        CrmFunnelPageData $pageData,
    ): JsonResponse {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleStage = $this->visibleStage($visibleFunnel, $crmFunnelStage);

        $visibleStage->update($request->stagePayload());

        return response()->json([
            'message' => __('ui.funnels.stage_updated_success'),
            'data' => $this->stage($visibleStage, $pageData),
        ]);
    }

    public function destroyStage(Request $request, CrmFunnel $crmFunnel, CrmFunnelStage $crmFunnelStage): JsonResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        abort_unless($request->user()->canManageFunnel($visibleFunnel), 403);

        $visibleStage = $this->visibleStage($visibleFunnel, $crmFunnelStage);

        if ($visibleStage->deals()->exists()) {
            throw ValidationException::withMessages([
                'stage' => __('ui.funnels.stage_has_deals_error'),
            ]);
        }

        $visibleStage->delete();

        return response()->json([
            'message' => __('ui.funnels.stage_deleted_success'),
        ]);
    }

    public function storeDeal(
        StoreCrmDealRequest $request,
        CrmFunnel $crmFunnel,
        CrmFunnelBoardManager $boardManager,
        CrmFunnelPageData $pageData,
    ): JsonResponse {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);

        $deal = CrmDeal::query()->create([
            ...$request->dealPayload($visibleFunnel),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
            'sort_order' => $request->sortOrder() ?? $boardManager->nextDealSortOrder($visibleFunnel, (int) $request->validated('crm_funnel_stage_id')),
        ]);

        return response()->json([
            'message' => __('ui.funnels.deal_created_success'),
            'data' => $this->deal($deal, $pageData),
        ], 201);
    }

    /**
     * A deal with everything the application needs to render its detail screen and edit form.
     */
    public function showDeal(Request $request, CrmFunnel $crmFunnel, CrmDeal $crmDeal, CrmFunnelPageData $pageData): JsonResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleDeal = $this->visibleDeal($visibleFunnel, $crmDeal);

        return response()->json([
            'data' => [
                'deal' => $this->deal($visibleDeal, $pageData),
                'funnel' => [
                    'id' => $visibleFunnel->id,
                    'name' => $visibleFunnel->name,
                    'slug' => $visibleFunnel->slug,
                    'color' => $visibleFunnel->color,
                    'is_active' => $visibleFunnel->is_active,
                ],
                'stages' => $visibleFunnel->stages()
                    ->get()
                    ->map(fn (CrmFunnelStage $stage): array => $this->stageSummary($stage) + ['sort_order' => $stage->sort_order])
                    ->values()
                    ->all(),
                'deal_fields' => $visibleFunnel->dealFieldDefinitions(),
                'available_users' => $pageData->availableUsers(),
            ],
        ]);
    }

    public function updateDeal(
        UpdateCrmDealRequest $request,
        CrmFunnel $crmFunnel,
        CrmDeal $crmDeal,
        CrmFunnelPageData $pageData,
    ): JsonResponse {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleDeal = $this->visibleDeal($visibleFunnel, $crmDeal);

        $visibleDeal->update([
            ...$request->dealPayload($visibleFunnel),
            'updated_by_user_id' => $request->user()->id,
            'sort_order' => $request->sortOrder() ?? $visibleDeal->sort_order,
        ]);

        return response()->json([
            'message' => __('ui.funnels.deal_updated_success'),
            'data' => $this->deal($visibleDeal, $pageData),
        ]);
    }

    public function moveDeal(
        MoveCrmDealRequest $request,
        CrmFunnel $crmFunnel,
        CrmDeal $crmDeal,
        CrmFunnelBoardManager $boardManager,
        CrmFunnelPageData $pageData,
    ): JsonResponse {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleDeal = $this->visibleDeal($visibleFunnel, $crmDeal);

        $visibleDeal->update([
            'crm_funnel_stage_id' => $request->stageId(),
            'sort_order' => $request->sortOrder() ?? $boardManager->nextDealSortOrder($visibleFunnel, $request->stageId()),
            'updated_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => __('ui.funnels.deal_updated_success'),
            'data' => $this->deal($visibleDeal, $pageData),
        ]);
    }

    public function destroyDeal(Request $request, CrmFunnel $crmFunnel, CrmDeal $crmDeal): JsonResponse
    {
        $visibleFunnel = $this->visibleFunnel($request, $crmFunnel);
        $visibleDeal = $this->visibleDeal($visibleFunnel, $crmDeal);

        $visibleDeal->delete();

        return response()->json([
            'message' => __('ui.funnels.deal_deleted_success'),
        ]);
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

    /**
     * The web page props with deal custom field values always encoded as JSON objects.
     *
     * @param  array<string, mixed>  $pageData
     * @return array<string, mixed>
     */
    private function pageData(array $pageData): array
    {
        if (is_array($pageData['activeFunnel'] ?? null)) {
            $pageData['activeFunnel'] = $this->normalizeBoard($pageData['activeFunnel']);
        }

        return $pageData;
    }

    /**
     * @return array<string, mixed>
     */
    private function funnelBoard(CrmFunnel $funnel, CrmFunnelPageData $pageData): array
    {
        return $this->normalizeBoard($pageData->board($funnel->refresh()));
    }

    /**
     * @param  array<mixed>  $board
     * @return array<mixed>
     */
    private function normalizeBoard(array $board): array
    {
        $stages = $board['stages'] ?? null;

        if (is_array($stages)) {
            $board['stages'] = array_map(
                fn (mixed $stage): mixed => is_array($stage) ? $this->normalizeStage($stage) : $stage,
                $stages,
            );
        }

        return $board;
    }

    /**
     * @param  array<mixed>  $stage
     * @return array<mixed>
     */
    private function normalizeStage(array $stage): array
    {
        $deals = $stage['deals'] ?? null;

        if (is_array($deals)) {
            $stage['deals'] = array_map(
                fn (mixed $deal): mixed => is_array($deal)
                    ? [...$deal, 'custom_fields' => $this->customFieldValues($deal['custom_fields'] ?? null)]
                    : $deal,
                $deals,
            );
        }

        return $stage;
    }

    /**
     * A board stage (with its deals) extended with the funnel it belongs to.
     *
     * @return array<string, mixed>
     */
    private function stage(CrmFunnelStage $stage, CrmFunnelPageData $pageData): array
    {
        $stage->refresh()->load([
            'deals' => fn ($dealQuery) => $dealQuery->with(['responsibleUser:id,name,last_name,email']),
        ]);

        return $this->normalizeStage([
            ...$pageData->stage($stage),
            'crm_funnel_id' => $stage->crm_funnel_id,
        ]);
    }

    /**
     * The board deal card extended with its funnel, stage, authors and creation time.
     *
     * @return array<string, mixed>
     */
    private function deal(CrmDeal $deal, CrmFunnelPageData $pageData): array
    {
        $deal->refresh()->load([
            'stage',
            'responsibleUser:id,name,last_name,email',
            'creator:id,name,last_name,email',
            'updater:id,name,last_name,email',
        ]);

        return [
            ...$pageData->deal($deal),
            'crm_funnel_id' => $deal->crm_funnel_id,
            'custom_fields' => $this->customFieldValues($deal->custom_fields),
            'stage' => $deal->stage ? $this->stageSummary($deal->stage) : null,
            'creator' => $this->user($deal->creator),
            'updater' => $this->user($deal->updater),
            'created_at' => $deal->created_at?->toISOString(),
        ];
    }

    /**
     * @return array{id: int, name: string, color: string|null, type: string}
     */
    private function stageSummary(CrmFunnelStage $stage): array
    {
        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'color' => $stage->color,
            'type' => $stage->type,
        ];
    }

    /**
     * @return array{id: int, name: string, last_name: string|null, email: string}|null
     */
    private function user(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ];
    }

    /**
     * Custom field values keyed by the funnel field key, encoded as a JSON object even when empty.
     *
     * @return array<string, mixed>|stdClass
     */
    private function customFieldValues(mixed $values): array|stdClass
    {
        return is_array($values) && $values !== [] ? $values : new stdClass;
    }
}
