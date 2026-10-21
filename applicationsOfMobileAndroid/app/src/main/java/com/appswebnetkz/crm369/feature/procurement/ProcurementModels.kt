package com.appswebnetkz.crm369.feature.procurement

import com.appswebnetkz.crm369.core.json.asDouble
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.double
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import kotlinx.serialization.json.JsonObject

/** `can` block of `GET procurement`. */
data class ProcurementPermissions(
    val manage: Boolean,
    val approveBudget: Boolean,
    val manageOrders: Boolean,
    val receiveOrders: Boolean,
    val returnGoods: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject?): ProcurementPermissions = ProcurementPermissions(
            manage = json.boolOr("manage", false),
            approveBudget = json.boolOr("approve_budget", false),
            manageOrders = json.boolOr("manage_orders", false),
            receiveOrders = json.boolOr("receive_orders", false),
            returnGoods = json.boolOr("return_goods", false),
        )
    }
}

data class ProcurementSummary(
    val pendingApprovals: Int,
    val activeOrders: Int,
    val monthReceipts: Map<String, Double>,
    val potentialSavings: Map<String, Double>,
) {
    companion object {
        fun fromJson(json: JsonObject?): ProcurementSummary = ProcurementSummary(
            pendingApprovals = json.int("pending_approvals") ?: 0,
            activeOrders = json.int("active_orders") ?: 0,
            monthReceipts = currencyMap(json.obj("month_receipts")),
            potentialSavings = currencyMap(json.obj("potential_savings")),
        )

        /** `{"KZT": 600}` (an empty PHP array arrives as `[]` and is treated as empty). */
        private fun currencyMap(json: JsonObject?): Map<String, Double> {
            if (json == null) return emptyMap()
            val result = LinkedHashMap<String, Double>()
            json.forEach { (currency, value) ->
                val amount = value.asDouble()
                if (amount != null) result[currency] = amount
            }
            return result
        }
    }
}

data class ProcurementSupplier(
    val id: Long,
    val contactId: Long?,
    val name: String,
    val bin: String?,
    val contactPerson: String?,
    val email: String?,
    val phone: String?,
    val currency: String,
    val paymentTermsDays: Int,
    val leadTimeDays: Int,
    val rating: Double?,
    val isActive: Boolean,
    val notes: String?,
    val quotationCount: Int,
    val orderCount: Int,
) {
    companion object {
        fun fromJson(json: JsonObject): ProcurementSupplier = ProcurementSupplier(
            id = json.long("id") ?: 0L,
            contactId = json.long("contact_id"),
            name = json.str("name").orEmpty(),
            bin = json.text("bin"),
            contactPerson = json.text("contact_person"),
            email = json.text("email"),
            phone = json.text("phone"),
            currency = json.text("currency") ?: DEFAULT_CURRENCY,
            paymentTermsDays = json.int("payment_terms_days") ?: 0,
            leadTimeDays = json.int("lead_time_days") ?: 0,
            rating = json.double("rating"),
            isActive = json.boolOr("is_active", true),
            notes = json.text("notes"),
            quotationCount = json.int("quotation_count") ?: 0,
            orderCount = json.int("order_count") ?: 0,
        )
    }
}

data class ProcurementQuotation(
    val id: Long,
    val supplierId: Long,
    val supplierName: String,
    val supplierRating: Double?,
    val unitPrice: Double,
    val taxPercent: Double,
    val deliveryCost: Double,
    val landedUnitPrice: Double,
    val landedTotal: Double,
    val currency: String,
    val quotedAt: String?,
    val validUntil: String?,
    val leadTimeDays: Int,
    val notes: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): ProcurementQuotation = ProcurementQuotation(
            id = json.long("id") ?: 0L,
            supplierId = json.long("supplier_id") ?: 0L,
            supplierName = json.str("supplier_name").orEmpty(),
            supplierRating = json.double("supplier_rating"),
            unitPrice = json.double("unit_price") ?: 0.0,
            taxPercent = json.double("tax_percent") ?: 0.0,
            deliveryCost = json.double("delivery_cost") ?: 0.0,
            landedUnitPrice = json.double("landed_unit_price") ?: 0.0,
            landedTotal = json.double("landed_total") ?: 0.0,
            currency = json.text("currency") ?: DEFAULT_CURRENCY,
            quotedAt = json.text("quoted_at"),
            validUntil = json.text("valid_until"),
            leadTimeDays = json.int("lead_time_days") ?: 0,
            notes = json.text("notes"),
        )
    }
}

data class ProcurementRequestItem(
    val id: Long,
    val itemName: String,
    val sku: String?,
    val unit: String,
    val quantity: Long,
    val targetUnitPrice: Double,
    val productionReference: String?,
    val warehousePlaceId: Long?,
    val warehousePlace: String?,
    val notes: String?,
    val bestQuotationId: Long?,
    val quotations: List<ProcurementQuotation>,
) {
    companion object {
        fun fromJson(json: JsonObject): ProcurementRequestItem = ProcurementRequestItem(
            id = json.long("id") ?: 0L,
            itemName = json.str("item_name").orEmpty(),
            sku = json.text("sku"),
            unit = json.text("unit") ?: DEFAULT_UNIT,
            quantity = json.long("quantity") ?: 0L,
            targetUnitPrice = json.double("target_unit_price") ?: 0.0,
            productionReference = json.text("production_reference"),
            warehousePlaceId = json.long("warehouse_place_id"),
            warehousePlace = json.text("warehouse_place"),
            notes = json.text("notes"),
            bestQuotationId = json.long("best_quotation_id"),
            quotations = json.objects("quotations").map { ProcurementQuotation.fromJson(it) },
        )
    }
}

/** Short reference of the order created from a request. */
data class ProcurementOrderRef(
    val id: Long,
    val number: String,
    val status: String,
    val totalAmount: Double,
)

data class ProcurementRequest(
    val id: Long,
    val number: String,
    val title: String,
    val status: String,
    val neededAt: String?,
    val budgetAmount: Double,
    val estimatedTotal: Double,
    val currency: String,
    val justification: String?,
    val rejectionReason: String?,
    val requestedBy: String?,
    val approvedBy: String?,
    val submittedAt: String?,
    val approvedAt: String?,
    val order: ProcurementOrderRef?,
    val items: List<ProcurementRequestItem>,
) {
    companion object {
        const val STATUS_PENDING_APPROVAL = "pending_approval"
        const val STATUS_APPROVED = "approved"

        fun fromJson(json: JsonObject): ProcurementRequest {
            val order = json.obj("order")
            return ProcurementRequest(
                id = json.long("id") ?: 0L,
                number = json.str("number").orEmpty(),
                title = json.str("title").orEmpty(),
                status = json.text("status") ?: "draft",
                neededAt = json.text("needed_at"),
                budgetAmount = json.double("budget_amount") ?: 0.0,
                estimatedTotal = json.double("estimated_total") ?: 0.0,
                currency = json.text("currency") ?: DEFAULT_CURRENCY,
                justification = json.text("justification"),
                rejectionReason = json.text("rejection_reason"),
                requestedBy = json.text("requested_by"),
                approvedBy = json.text("approved_by"),
                submittedAt = json.text("submitted_at"),
                approvedAt = json.text("approved_at"),
                order = if (order != null && order.long("id") != null) {
                    ProcurementOrderRef(
                        id = order.long("id") ?: 0L,
                        number = order.str("number").orEmpty(),
                        status = order.text("status") ?: "draft",
                        totalAmount = order.double("total_amount") ?: 0.0,
                    )
                } else {
                    null
                },
                items = json.objects("items").map { ProcurementRequestItem.fromJson(it) },
            )
        }
    }
}

data class ProcurementOrderItem(
    val id: Long,
    val itemName: String,
    val sku: String?,
    val unit: String,
    val quantity: Long,
    val receivedQuantity: Long,
    val remainingQuantity: Long,
    val returnedQuantity: Long,
    val returnableQuantity: Long,
    val unitPrice: Double,
    val taxPercent: Double,
    val lineTotal: Double,
    val warehousePlaceId: Long?,
    val warehousePlace: String?,
    val warehouseStock: Long?,
) {
    companion object {
        fun fromJson(json: JsonObject): ProcurementOrderItem = ProcurementOrderItem(
            id = json.long("id") ?: 0L,
            itemName = json.str("item_name").orEmpty(),
            sku = json.text("sku"),
            unit = json.text("unit") ?: DEFAULT_UNIT,
            quantity = json.long("quantity") ?: 0L,
            receivedQuantity = json.long("received_quantity") ?: 0L,
            remainingQuantity = json.long("remaining_quantity") ?: 0L,
            returnedQuantity = json.long("returned_quantity") ?: 0L,
            returnableQuantity = json.long("returnable_quantity") ?: 0L,
            unitPrice = json.double("unit_price") ?: 0.0,
            taxPercent = json.double("tax_percent") ?: 0.0,
            lineTotal = json.double("line_total") ?: 0.0,
            warehousePlaceId = json.long("warehouse_place_id"),
            warehousePlace = json.text("warehouse_place"),
            warehouseStock = json.long("warehouse_stock"),
        )
    }
}

data class ProcurementOrder(
    val id: Long,
    val number: String,
    val purchaseRequestId: Long?,
    val requestNumber: String?,
    val requestTitle: String?,
    val supplierId: Long?,
    val supplierName: String,
    val status: String,
    val currency: String,
    val orderedAt: String?,
    val expectedAt: String?,
    val sentAt: String?,
    val subtotal: Double,
    val taxAmount: Double,
    val deliveryAmount: Double,
    val totalAmount: Double,
    val notes: String?,
    val receiptCount: Int,
    val returnCount: Int,
    val items: List<ProcurementOrderItem>,
) {
    val canBeSent: Boolean get() = status == STATUS_DRAFT
    val isReceivable: Boolean
        get() = (status == STATUS_SENT || status == STATUS_PARTIALLY_RECEIVED) && items.any { it.remainingQuantity > 0 }

    companion object {
        const val STATUS_DRAFT = "draft"
        const val STATUS_SENT = "sent"
        const val STATUS_PARTIALLY_RECEIVED = "partially_received"

        fun fromJson(json: JsonObject): ProcurementOrder = ProcurementOrder(
            id = json.long("id") ?: 0L,
            number = json.str("number").orEmpty(),
            purchaseRequestId = json.long("purchase_request_id"),
            requestNumber = json.text("request_number"),
            requestTitle = json.text("request_title"),
            supplierId = json.long("supplier_id"),
            supplierName = json.str("supplier_name").orEmpty(),
            status = json.text("status") ?: STATUS_DRAFT,
            currency = json.text("currency") ?: DEFAULT_CURRENCY,
            orderedAt = json.text("ordered_at"),
            expectedAt = json.text("expected_at"),
            sentAt = json.text("sent_at"),
            subtotal = json.double("subtotal") ?: 0.0,
            taxAmount = json.double("tax_amount") ?: 0.0,
            deliveryAmount = json.double("delivery_amount") ?: 0.0,
            totalAmount = json.double("total_amount") ?: 0.0,
            notes = json.text("notes"),
            receiptCount = json.int("receipt_count") ?: 0,
            returnCount = json.int("return_count") ?: 0,
            items = json.objects("items").map { ProcurementOrderItem.fromJson(it) },
        )
    }
}

data class ProcurementReceiptItem(
    val id: Long,
    val purchaseOrderItemId: Long?,
    val itemName: String,
    val sku: String?,
    val unit: String,
    val warehouseItemName: String?,
    val quantity: Long,
    val returnedQuantity: Long,
    val returnableQuantity: Long,
    val unitPrice: Double,
    val lineTotal: Double,
) {
    companion object {
        fun fromJson(json: JsonObject): ProcurementReceiptItem = ProcurementReceiptItem(
            id = json.long("id") ?: 0L,
            purchaseOrderItemId = json.long("purchase_order_item_id"),
            itemName = json.str("item_name").orEmpty(),
            sku = json.text("sku"),
            unit = json.text("unit") ?: DEFAULT_UNIT,
            warehouseItemName = json.text("warehouse_item_name"),
            quantity = json.long("quantity") ?: 0L,
            returnedQuantity = json.long("returned_quantity") ?: 0L,
            returnableQuantity = json.long("returnable_quantity") ?: 0L,
            unitPrice = json.double("unit_price") ?: 0.0,
            lineTotal = json.double("line_total") ?: 0.0,
        )
    }
}

data class ProcurementReceipt(
    val id: Long,
    val number: String,
    val purchaseOrderId: Long,
    val orderNumber: String?,
    val supplierName: String,
    val currency: String,
    val status: String,
    val receivedAt: String?,
    val receivedBy: String?,
    val externalReference: String?,
    val notes: String?,
    val totalAmount: Double,
    val items: List<ProcurementReceiptItem>,
) {
    val isReturnable: Boolean get() = items.any { it.returnableQuantity > 0 }

    companion object {
        fun fromJson(json: JsonObject): ProcurementReceipt = ProcurementReceipt(
            id = json.long("id") ?: 0L,
            number = json.str("number").orEmpty(),
            purchaseOrderId = json.long("purchase_order_id") ?: 0L,
            orderNumber = json.text("order_number"),
            supplierName = json.str("supplier_name").orEmpty(),
            currency = json.text("currency") ?: DEFAULT_CURRENCY,
            status = json.text("status") ?: "posted",
            receivedAt = json.text("received_at"),
            receivedBy = json.text("received_by"),
            externalReference = json.text("external_reference"),
            notes = json.text("notes"),
            totalAmount = json.double("total_amount") ?: 0.0,
            items = json.objects("items").map { ProcurementReceiptItem.fromJson(it) },
        )
    }
}

data class ProcurementReturnItem(
    val id: Long,
    val itemName: String,
    val sku: String?,
    val unit: String,
    val quantity: Long,
    val unitPrice: Double,
    val lineTotal: Double,
    val warehouseStock: Long?,
) {
    companion object {
        fun fromJson(json: JsonObject): ProcurementReturnItem = ProcurementReturnItem(
            id = json.long("id") ?: 0L,
            itemName = json.str("item_name").orEmpty(),
            sku = json.text("sku"),
            unit = json.text("unit") ?: DEFAULT_UNIT,
            quantity = json.long("quantity") ?: 0L,
            unitPrice = json.double("unit_price") ?: 0.0,
            lineTotal = json.double("line_total") ?: 0.0,
            warehouseStock = json.long("warehouse_stock"),
        )
    }
}

data class ProcurementReturn(
    val id: Long,
    val number: String,
    val purchaseOrderId: Long,
    val orderNumber: String?,
    val supplierName: String,
    val currency: String,
    val status: String,
    val returnedAt: String?,
    val createdBy: String?,
    val reason: String?,
    val totalAmount: Double,
    val items: List<ProcurementReturnItem>,
) {
    companion object {
        fun fromJson(json: JsonObject): ProcurementReturn = ProcurementReturn(
            id = json.long("id") ?: 0L,
            number = json.str("number").orEmpty(),
            purchaseOrderId = json.long("purchase_order_id") ?: 0L,
            orderNumber = json.text("order_number"),
            supplierName = json.str("supplier_name").orEmpty(),
            currency = json.text("currency") ?: DEFAULT_CURRENCY,
            status = json.text("status") ?: "posted",
            returnedAt = json.text("returned_at"),
            createdBy = json.text("created_by"),
            reason = json.text("reason"),
            totalAmount = json.double("total_amount") ?: 0.0,
            items = json.objects("items").map { ProcurementReturnItem.fromJson(it) },
        )
    }
}

/** Warehouse place for receipts and request items (`path` = "Warehouse / Row / … / Place"). */
data class ProcurementPlace(val id: Long, val name: String, val path: String)

/** Company contact that can be linked to a supplier. */
data class ProcurementContact(val id: Long, val name: String, val contactPerson: String?)

/** Whole `GET procurement` payload. */
data class ProcurementData(
    val summary: ProcurementSummary,
    val suppliers: List<ProcurementSupplier>,
    val requests: List<ProcurementRequest>,
    val orders: List<ProcurementOrder>,
    val receipts: List<ProcurementReceipt>,
    val returns: List<ProcurementReturn>,
    val places: List<ProcurementPlace>,
    val contacts: List<ProcurementContact>,
    val can: ProcurementPermissions,
) {
    companion object {
        fun fromJson(json: JsonObject): ProcurementData = ProcurementData(
            summary = ProcurementSummary.fromJson(json.obj("summary")),
            suppliers = json.objects("suppliers").map { ProcurementSupplier.fromJson(it) },
            requests = json.objects("purchaseRequests").map { ProcurementRequest.fromJson(it) },
            orders = json.objects("purchaseOrders").map { ProcurementOrder.fromJson(it) },
            receipts = json.objects("goodsReceipts").map { ProcurementReceipt.fromJson(it) },
            returns = json.objects("purchaseReturns").map { ProcurementReturn.fromJson(it) },
            places = json.objects("warehousePlaces").map { place ->
                val name = place.str("name").orEmpty()
                ProcurementPlace(id = place.long("id") ?: 0L, name = name, path = place.text("path") ?: name)
            },
            contacts = json.objects("companyContacts").map { contact ->
                ProcurementContact(
                    id = contact.long("id") ?: 0L,
                    name = contact.str("name").orEmpty(),
                    contactPerson = contact.text("contact_person"),
                )
            },
            can = ProcurementPermissions.fromJson(json.obj("can")),
        )
    }
}

const val DEFAULT_CURRENCY = "KZT"
const val DEFAULT_UNIT = "pcs"
val PROCUREMENT_CURRENCIES: List<String> = listOf("KZT", "USD", "EUR", "RUB")
