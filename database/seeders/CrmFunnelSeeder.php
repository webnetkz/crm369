<?php

namespace Database\Seeders;

use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class CrmFunnelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()->first() ?? User::factory()->create();
        $group = UserGroup::query()->first();

        $funnel = CrmFunnel::factory()->create([
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
            'name' => 'Sales funnel',
            'slug' => 'sales-funnel',
        ]);

        if ($group) {
            $funnel->groups()->sync([$group->id]);
        }

        $newStage = CrmFunnelStage::factory()->create([
            'crm_funnel_id' => $funnel->id,
            'name' => 'New',
            'type' => CrmFunnelStage::TYPE_OPEN,
            'sort_order' => 0,
        ]);

        CrmFunnelStage::factory()->create([
            'crm_funnel_id' => $funnel->id,
            'name' => 'Won',
            'type' => CrmFunnelStage::TYPE_WON,
            'sort_order' => 1,
        ]);

        CrmDeal::factory()->create([
            'crm_funnel_id' => $funnel->id,
            'crm_funnel_stage_id' => $newStage->id,
            'responsible_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
    }
}
