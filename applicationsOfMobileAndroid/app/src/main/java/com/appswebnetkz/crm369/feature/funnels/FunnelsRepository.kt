package com.appswebnetkz.crm369.feature.funnels

import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.network.ApiClient
import kotlinx.serialization.json.JsonObject

/** All funnel / deal API calls (`/api/mobile/v1/funnels`). Update calls replace the whole object. */
class FunnelsRepository(private val api: ApiClient) {

    /** `GET funnels` (board of the first visible funnel) or `GET funnels/{id}` (board of that funnel). */
    suspend fun page(funnelId: Long?): FunnelPage {
        val response = if (funnelId == null) api.get("funnels") else api.get("funnels/$funnelId")
        return FunnelPage.fromJson(response.dataObject())
    }

    suspend fun createFunnel(body: JsonObject): FunnelBoard =
        FunnelBoard.fromJson(api.post("funnels", body).dataObject())

    suspend fun updateFunnel(funnelId: Long, body: JsonObject): FunnelBoard =
        FunnelBoard.fromJson(api.patch("funnels/$funnelId", body).dataObject())

    suspend fun deleteFunnel(funnelId: Long) {
        api.delete("funnels/$funnelId")
    }

    /** Body: name, color, type, sort_order (always send sort_order: a missing value resets it to 0). */
    suspend fun createStage(funnelId: Long, body: JsonObject) {
        api.post("funnels/$funnelId/stages", body)
    }

    suspend fun updateStage(funnelId: Long, stageId: Long, body: JsonObject) {
        api.patch("funnels/$funnelId/stages/$stageId", body)
    }

    /** A stage that still has deals answers 422 (`errors.stage`). */
    suspend fun deleteStage(funnelId: Long, stageId: Long) {
        api.delete("funnels/$funnelId/stages/$stageId")
    }

    suspend fun deal(funnelId: Long, dealId: Long): DealPage =
        DealPage.fromJson(api.get("funnels/$funnelId/deals/$dealId").dataObject())

    suspend fun createDeal(funnelId: Long, body: JsonObject): Deal =
        Deal.fromJson(api.post("funnels/$funnelId/deals", body).dataObject())

    suspend fun updateDeal(funnelId: Long, dealId: Long, body: JsonObject): Deal =
        Deal.fromJson(api.patch("funnels/$funnelId/deals/$dealId", body).dataObject())

    suspend fun moveDeal(funnelId: Long, dealId: Long, stageId: Long): Deal =
        Deal.fromJson(
            api.patch("funnels/$funnelId/deals/$dealId/move", jsonObjectOf("crm_funnel_stage_id" to stageId)).dataObject(),
        )

    suspend fun deleteDeal(funnelId: Long, dealId: Long) {
        api.delete("funnels/$funnelId/deals/$dealId")
    }
}
