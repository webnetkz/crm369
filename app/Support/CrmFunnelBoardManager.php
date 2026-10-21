<?php

namespace App\Support;

use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;

class CrmFunnelBoardManager
{
    /**
     * Create the stages every new funnel starts with.
     */
    public function createDefaultStages(CrmFunnel $funnel): void
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

    /**
     * The sort order that places a new stage after the existing stages of the funnel.
     */
    public function nextStageSortOrder(CrmFunnel $funnel): int
    {
        return (int) (($funnel->stages()->max('sort_order') ?? -1) + 1);
    }

    /**
     * The sort order that places a deal at the end of the given stage.
     */
    public function nextDealSortOrder(CrmFunnel $funnel, int $stageId): int
    {
        return (int) (($funnel->deals()->where('crm_funnel_stage_id', $stageId)->max('sort_order') ?? -1) + 1);
    }
}
