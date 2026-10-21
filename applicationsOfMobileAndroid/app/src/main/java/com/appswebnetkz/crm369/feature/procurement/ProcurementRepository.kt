package com.appswebnetkz.crm369.feature.procurement

import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.network.ApiClient
import kotlinx.serialization.json.JsonObject

/**
 * Procurement API (`/api/mobile/v1/procurement`). Write endpoints return raw database rows, so
 * screens re-read `GET procurement` after every write; creating calls only return the new id.
 */
class ProcurementRepository(private val api: ApiClient) {

    suspend fun load(): ProcurementData = ProcurementData.fromJson(api.get("procurement").dataObject())

    /** Creates a purchase request (it is submitted for approval); returns its id. */
    suspend fun createRequest(body: JsonObject): Long? =
        api.post("procurement/requests", body).dataObject().long("id")

    suspend fun approve(requestId: Long) {
        api.patch(
            "procurement/requests/$requestId/decision",
            jsonObjectOf("decision" to "approve", "rejection_reason" to null),
        )
    }

    suspend fun reject(requestId: Long, reason: String) {
        api.patch(
            "procurement/requests/$requestId/decision",
            jsonObjectOf("decision" to "reject", "rejection_reason" to reason),
        )
    }

    suspend fun createSupplier(body: JsonObject) {
        api.post("procurement/suppliers", body)
    }

    /** Full update of a supplier (every field is replaced). */
    suspend fun updateSupplier(supplierId: Long, body: JsonObject) {
        api.patch("procurement/suppliers/$supplierId", body)
    }

    /** Creates or updates the quotation of a supplier for a request item. */
    suspend fun saveQuotation(body: JsonObject) {
        api.post("procurement/quotations", body)
    }

    suspend fun createOrder(body: JsonObject): Long? =
        api.post("procurement/orders", body).dataObject().long("id")

    suspend fun sendOrder(orderId: Long) {
        api.patch("procurement/orders/$orderId/send")
    }

    suspend fun createReceipt(body: JsonObject): Long? =
        api.post("procurement/receipts", body).dataObject().long("id")

    suspend fun createReturn(body: JsonObject): Long? =
        api.post("procurement/returns", body).dataObject().long("id")
}
