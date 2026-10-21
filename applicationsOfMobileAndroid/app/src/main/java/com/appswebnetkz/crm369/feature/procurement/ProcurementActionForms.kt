package com.appswebnetkz.crm369.feature.procurement

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormFields
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

// ---------------------------------------------------------------------------------------------
// Supplier quotation for one request item (POST procurement/quotations, creates or updates).
// ---------------------------------------------------------------------------------------------

@Composable
fun ProcurementQuotationFormScreen(requestId: Long, itemId: Long) {
    val api = rememberApi()
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api, requestId, itemId) { repository.load() }
    val title = stringResource(R.string.procurement_add_quote)
    ProcurementGate(title = title, loadable = procurement) { data ->
        val request = data.requests.firstOrNull { it.id == requestId }
        val item = request?.items?.firstOrNull { it.id == itemId }
        if (request == null || item == null) {
            ProcurementNotFoundScreen(title = title)
        } else {
            QuotationForm(title = title, request = request, item = item, suppliers = data.suppliers, repository = repository)
        }
    }
}

@Composable
private fun QuotationForm(
    title: String,
    request: ProcurementRequest,
    item: ProcurementRequestItem,
    suppliers: List<ProcurementSupplier>,
    repository: ProcurementRepository,
) {
    val navigator = LocalNavigator.current
    val activeSuppliers = suppliers.filter { it.isActive }
    val firstSupplier = activeSuppliers.firstOrNull()
    val state = remember(item.id) {
        FormState(
            mapOf(
                "supplier_id" to firstSupplier?.id?.toString(),
                "tax_percent" to "12",
                "delivery_cost" to "0",
                "quoted_at" to todayIso(),
                "lead_time_days" to (firstSupplier?.leadTimeDays ?: 0).toString(),
            ),
        )
    }
    val supplierOptions = activeSuppliers.map { supplier ->
        Option(
            value = supplier.id.toString(),
            label = supplier.name,
            subtitle = listOfNotNull(supplier.contactPerson, supplier.rating?.let { Formatters.number(it, 2) }).joinToString(" · ").ifBlank { null },
        )
    }
    val specs = listOf(
        FieldSpec.Select("supplier_id", stringResource(R.string.procurement_supplier), supplierOptions, required = true, numeric = true),
        FieldSpec.Decimal("unit_price", stringResource(R.string.procurement_unit_price) + ", " + request.currency, required = true),
        FieldSpec.Decimal("tax_percent", stringResource(R.string.procurement_tax_percent), required = true),
        FieldSpec.Decimal("delivery_cost", stringResource(R.string.procurement_delivery_cost) + ", " + request.currency, required = true),
        FieldSpec.Number("lead_time_days", stringResource(R.string.procurement_lead_time_days), required = true),
        FieldSpec.Date("quoted_at", stringResource(R.string.procurement_quoted_at), required = true),
        FieldSpec.Date("valid_until", stringResource(R.string.procurement_valid_until)),
        FieldSpec.Multiline("notes", stringResource(R.string.procurement_notes)),
    )

    FormScreen(
        title = title,
        specs = specs,
        state = state,
        successMessage = stringResource(R.string.procurement_quote_saved),
        onSubmit = {
            val body = state.toJson(
                specs,
                extra = mapOf("purchase_request_item_id" to item.id, "currency" to request.currency),
            )
            repository.saveQuotation(body)
        },
        onSuccess = { navigator.back() },
        header = {
            InfoCard(title = item.itemName) {
                KeyValueRow(label = stringResource(R.string.procurement_request), value = request.number + " · " + request.title)
                KeyValueRow(label = stringResource(R.string.procurement_quantity), value = formatQuantity(item.quantity, item.unit))
                KeyValueRow(label = stringResource(R.string.procurement_target_unit_price), value = formatMoney(item.targetUnitPrice, request.currency))
                if (activeSuppliers.isEmpty()) {
                    ProcurementHint(text = stringResource(R.string.procurement_no_active_suppliers), color = MaterialTheme.colorScheme.error)
                } else {
                    ProcurementHint(text = stringResource(R.string.procurement_quote_hint))
                }
            }
        },
    )
}

// ---------------------------------------------------------------------------------------------
// Purchase order from an approved request (one quotation of the chosen supplier per item).
// ---------------------------------------------------------------------------------------------

@Composable
fun ProcurementOrderFormScreen(requestId: Long) {
    val api = rememberApi()
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api, requestId) { repository.load() }
    val title = stringResource(R.string.procurement_create_order)
    ProcurementGate(title = title, loadable = procurement) { data ->
        val request = data.requests.firstOrNull { it.id == requestId }
        if (request == null) {
            ProcurementNotFoundScreen(title = title)
        } else {
            OrderForm(title = title, request = request, suppliers = data.suppliers, repository = repository)
        }
    }
}

@Composable
private fun OrderForm(
    title: String,
    request: ProcurementRequest,
    suppliers: List<ProcurementSupplier>,
    repository: ProcurementRepository,
) {
    val navigator = LocalNavigator.current
    val runner = rememberActionRunner()
    val eligible = remember(request, suppliers) {
        suppliers.filter { supplier ->
            supplier.isActive && request.items.all { item -> item.quotations.any { it.supplierId == supplier.id } }
        }
    }
    val state = remember(request.id) {
        FormState(mapOf("supplier_id" to eligible.firstOrNull()?.id?.toString(), "ordered_at" to todayIso()))
    }
    val requiredMessage = stringResource(R.string.form_required)
    val noQuotesMessage = stringResource(R.string.procurement_order_quotes_missing)
    val createdMessage = stringResource(R.string.procurement_order_created)
    val supplierId = state.string("supplier_id").toLongOrNull()
    val chosen = request.items.map { item -> item to item.quotations.firstOrNull { it.supplierId == supplierId } }
    val total = chosen.sumOf { (_, quotation) -> quotation?.landedTotal ?: 0.0 }

    val supplierOptions = eligible.map { supplier ->
        val supplierTotal = request.items.sumOf { item -> item.quotations.firstOrNull { it.supplierId == supplier.id }?.landedTotal ?: 0.0 }
        Option(supplier.id.toString(), supplier.name, formatMoney(supplierTotal, request.currency))
    }
    val specs = listOf(
        FieldSpec.Select("supplier_id", stringResource(R.string.procurement_supplier), supplierOptions, required = true, numeric = true),
        FieldSpec.Date("ordered_at", stringResource(R.string.procurement_ordered_at), required = true),
        FieldSpec.Date("expected_at", stringResource(R.string.procurement_expected_at)),
        FieldSpec.Multiline("notes", stringResource(R.string.procurement_notes)),
    )

    ProcurementFormScaffold(
        title = title,
        submitLabel = stringResource(R.string.procurement_create_order),
        isBusy = runner.isBusy,
        generalError = state.generalError,
        onSubmit = {
            if (state.validate(specs, requiredMessage)) {
                val quotationIds = chosen.mapNotNull { (_, quotation) -> quotation?.id }
                if (quotationIds.size != request.items.size) {
                    state.generalError = noQuotesMessage
                } else {
                    val body = jsonObjectOf(
                        "purchase_request_id" to request.id,
                        "supplier_id" to supplierId,
                        "quotation_ids" to quotationIds,
                        "ordered_at" to state.string("ordered_at"),
                        "expected_at" to optionalText(state, "expected_at"),
                        "notes" to optionalText(state, "notes"),
                    )
                    runner.execute(
                        successMessage = createdMessage,
                        onError = { error -> state.applyErrors(error) },
                        onSuccess = { orderId: Long? ->
                            if (orderId != null) navigator.replace(ProcurementRoutes.order(orderId)) else navigator.back()
                        },
                    ) { repository.createOrder(body) }
                }
            }
        },
    ) {
        InfoCard(title = request.title) {
            KeyValueRow(label = stringResource(R.string.procurement_request), value = request.number)
            KeyValueRow(label = stringResource(R.string.procurement_budget), value = formatMoney(request.budgetAmount, request.currency))
            if (eligible.isEmpty()) {
                ProcurementHint(text = stringResource(R.string.procurement_no_eligible_suppliers), color = MaterialTheme.colorScheme.error)
            }
        }
        FormFields(specs = specs, state = state, enabled = !runner.isBusy)
        SectionHeader(title = stringResource(R.string.procurement_selected_quotes))
        InfoCard {
            chosen.forEach { (item, quotation) ->
                ListRow(
                    title = item.itemName,
                    subtitle = if (quotation != null) {
                        stringResource(
                            R.string.procurement_order_line,
                            formatQuantity(item.quantity, item.unit),
                            formatMoney(quotation.landedUnitPrice, quotation.currency),
                            formatMoney(quotation.landedTotal, quotation.currency),
                        )
                    } else {
                        stringResource(R.string.procurement_no_quote_for_item)
                    },
                )
            }
            Text(
                text = stringResource(R.string.procurement_order_total, formatMoney(total, request.currency)),
                style = MaterialTheme.typography.titleSmall,
                color = if (total > request.budgetAmount) MaterialTheme.colorScheme.error else LocalExtendedColors.current.success,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            )
            if (total > request.budgetAmount) {
                ProcurementHint(text = stringResource(R.string.procurement_order_over_budget), color = MaterialTheme.colorScheme.error)
            }
        }
    }
}

// ---------------------------------------------------------------------------------------------
// Goods receipt for a sent order: quantity (≤ remaining) and warehouse place per item.
// ---------------------------------------------------------------------------------------------

@Composable
fun ProcurementReceiptFormScreen(orderId: Long) {
    val api = rememberApi()
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api, orderId) { repository.load() }
    val title = stringResource(R.string.procurement_record_receipt)
    ProcurementGate(title = title, loadable = procurement) { data ->
        val order = data.orders.firstOrNull { it.id == orderId }
        if (order == null) {
            ProcurementNotFoundScreen(title = title)
        } else {
            ReceiptForm(title = title, order = order, places = data.places, repository = repository)
        }
    }
}

@Composable
private fun ReceiptForm(
    title: String,
    order: ProcurementOrder,
    places: List<ProcurementPlace>,
    repository: ProcurementRepository,
) {
    val navigator = LocalNavigator.current
    val runner = rememberActionRunner()
    val rows = remember(order) { order.items.filter { it.remainingQuantity > 0 } }
    val state = remember(order.id) {
        val initial = LinkedHashMap<String, Any?>()
        initial["received_at"] = todayIso()
        rows.forEachIndexed { index, item ->
            initial["items.$index.quantity"] = item.remainingQuantity.toString()
            initial["items.$index.warehouse_place_id"] = (item.warehousePlaceId ?: places.firstOrNull()?.id)?.toString()
        }
        FormState(initial)
    }
    val requiredMessage = stringResource(R.string.form_required)
    val invalidQuantity = stringResource(R.string.procurement_quantity_invalid)
    val nothingMessage = stringResource(R.string.procurement_nothing_selected)
    val createdMessage = stringResource(R.string.procurement_receipt_created)
    val exceedsTemplate = stringResource(R.string.procurement_quantity_exceeds)
    val placeOptions = places.map { place -> Option(place.id.toString(), place.path, place.name.takeIf { it != place.path }) }
    val mainSpecs = listOf(
        FieldSpec.Date("received_at", stringResource(R.string.procurement_received_at), required = true),
        FieldSpec.Text("external_reference", stringResource(R.string.procurement_external_reference), maxLength = 255),
        FieldSpec.Multiline("notes", stringResource(R.string.procurement_notes)),
    )
    val quantityLabel = stringResource(R.string.procurement_quantity)
    val placeLabel = stringResource(R.string.procurement_warehouse_place)

    ProcurementFormScaffold(
        title = title,
        submitLabel = stringResource(R.string.procurement_record_receipt),
        isBusy = runner.isBusy,
        generalError = state.generalError,
        onSubmit = {
            var valid = state.validate(mainSpecs, requiredMessage)
            val sentRows = ArrayList<Int>()
            rows.forEachIndexed { index, item ->
                val quantityKey = "items.$index.quantity"
                val text = state.string(quantityKey).trim()
                val quantity = if (text.isEmpty()) 0L else parseCountInput(text)
                when {
                    quantity == null || quantity < 0 -> {
                        state.errors[quantityKey] = invalidQuantity
                        valid = false
                    }
                    quantity > item.remainingQuantity -> {
                        state.errors[quantityKey] = String.format(exceedsTemplate, Formatters.number(item.remainingQuantity))
                        valid = false
                    }
                    quantity > 0 -> {
                        if (state.string("items.$index.warehouse_place_id").toLongOrNull() == null) {
                            state.errors["items.$index.warehouse_place_id"] = requiredMessage
                            valid = false
                        }
                        sentRows.add(index)
                    }
                    else -> Unit
                }
            }
            if (valid && sentRows.isEmpty()) {
                state.generalError = nothingMessage
                valid = false
            }
            if (valid) {
                val body = jsonObjectOf(
                    "purchase_order_id" to order.id,
                    "received_at" to state.string("received_at"),
                    "external_reference" to optionalText(state, "external_reference"),
                    "notes" to optionalText(state, "notes"),
                    "items" to sentRows.map { index ->
                        mapOf(
                            "purchase_order_item_id" to rows[index].id,
                            "warehouse_place_id" to state.string("items.$index.warehouse_place_id").toLongOrNull(),
                            "quantity" to parseCountInput(state.string("items.$index.quantity")),
                        )
                    },
                )
                runner.execute(
                    successMessage = createdMessage,
                    onError = { error ->
                        val handled = state.applyErrors(error)
                        if (handled) remapItemErrors(state, "items", sentRows)
                        handled
                    },
                    onSuccess = { receiptId: Long? ->
                        if (receiptId != null) navigator.replace(ProcurementRoutes.receipt(receiptId)) else navigator.back()
                    },
                ) { repository.createReceipt(body) }
            }
        },
    ) {
        InfoCard(title = order.supplierName) {
            KeyValueRow(label = stringResource(R.string.procurement_order), value = order.number)
            KeyValueRow(label = stringResource(R.string.procurement_request), value = order.requestTitle)
        }
        FormFields(specs = mainSpecs, state = state, enabled = !runner.isBusy)
        SectionHeader(title = stringResource(R.string.procurement_items_count, rows.size))
        ProcurementHint(text = stringResource(R.string.procurement_receipt_hint))
        rows.forEachIndexed { index, item ->
            InfoCard(title = item.itemName) {
                ProcurementHint(
                    text = stringResource(
                        R.string.procurement_receipt_item_hint,
                        formatQuantity(item.quantity, item.unit),
                        formatQuantity(item.remainingQuantity, item.unit),
                    ),
                )
                FormFields(
                    specs = listOf(
                        FieldSpec.Number("items.$index.quantity", quantityLabel),
                        FieldSpec.Select("items.$index.warehouse_place_id", placeLabel, placeOptions, numeric = true),
                    ),
                    state = state,
                    modifier = Modifier.padding(bottom = 8.dp),
                    enabled = !runner.isBusy,
                )
            }
        }
    }
}

// ---------------------------------------------------------------------------------------------
// Return of received goods: quantity (≤ returnable) per receipt item and a reason.
// ---------------------------------------------------------------------------------------------

@Composable
fun ProcurementReturnFormScreen(receiptId: Long) {
    val api = rememberApi()
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api, receiptId) { repository.load() }
    val title = stringResource(R.string.procurement_record_return)
    ProcurementGate(title = title, loadable = procurement) { data ->
        val receipt = data.receipts.firstOrNull { it.id == receiptId }
        if (receipt == null) {
            ProcurementNotFoundScreen(title = title)
        } else {
            ReturnForm(title = title, receipt = receipt, repository = repository)
        }
    }
}

@Composable
private fun ReturnForm(title: String, receipt: ProcurementReceipt, repository: ProcurementRepository) {
    val navigator = LocalNavigator.current
    val runner = rememberActionRunner()
    val rows = remember(receipt) { receipt.items.filter { it.returnableQuantity > 0 } }
    val state = remember(receipt.id) {
        val initial = LinkedHashMap<String, Any?>()
        initial["returned_at"] = todayIso()
        rows.forEachIndexed { index, item -> initial["items.$index.quantity"] = item.returnableQuantity.toString() }
        FormState(initial)
    }
    val requiredMessage = stringResource(R.string.form_required)
    val invalidQuantity = stringResource(R.string.procurement_quantity_invalid)
    val nothingMessage = stringResource(R.string.procurement_nothing_selected)
    val createdMessage = stringResource(R.string.procurement_return_created)
    val exceedsTemplate = stringResource(R.string.procurement_quantity_exceeds)
    val mainSpecs = listOf(
        FieldSpec.Date("returned_at", stringResource(R.string.procurement_returned_at), required = true),
        FieldSpec.Multiline("reason", stringResource(R.string.procurement_reason), required = true),
    )
    val quantityLabel = stringResource(R.string.procurement_return_quantity)

    ProcurementFormScaffold(
        title = title,
        submitLabel = stringResource(R.string.procurement_record_return),
        isBusy = runner.isBusy,
        generalError = state.generalError,
        onSubmit = {
            var valid = state.validate(mainSpecs, requiredMessage)
            val sentRows = ArrayList<Int>()
            rows.forEachIndexed { index, item ->
                val quantityKey = "items.$index.quantity"
                val text = state.string(quantityKey).trim()
                val quantity = if (text.isEmpty()) 0L else parseCountInput(text)
                when {
                    quantity == null || quantity < 0 -> {
                        state.errors[quantityKey] = invalidQuantity
                        valid = false
                    }
                    quantity > item.returnableQuantity -> {
                        state.errors[quantityKey] = String.format(exceedsTemplate, Formatters.number(item.returnableQuantity))
                        valid = false
                    }
                    quantity > 0 -> sentRows.add(index)
                    else -> Unit
                }
            }
            if (valid && sentRows.isEmpty()) {
                state.generalError = nothingMessage
                valid = false
            }
            if (valid) {
                val body = jsonObjectOf(
                    "purchase_order_id" to receipt.purchaseOrderId,
                    "returned_at" to state.string("returned_at"),
                    "reason" to state.string("reason").trim(),
                    "items" to sentRows.map { index ->
                        mapOf(
                            "goods_receipt_item_id" to rows[index].id,
                            "quantity" to parseCountInput(state.string("items.$index.quantity")),
                        )
                    },
                )
                runner.execute(
                    successMessage = createdMessage,
                    onError = { error ->
                        val handled = state.applyErrors(error)
                        if (handled) remapItemErrors(state, "items", sentRows)
                        handled
                    },
                    onSuccess = { returnId: Long? ->
                        if (returnId != null) navigator.replace(ProcurementRoutes.purchaseReturn(returnId)) else navigator.back()
                    },
                ) { repository.createReturn(body) }
            }
        },
    ) {
        InfoCard(title = receipt.supplierName) {
            KeyValueRow(label = stringResource(R.string.procurement_receipt), value = receipt.number)
            KeyValueRow(label = stringResource(R.string.procurement_order), value = receipt.orderNumber)
            KeyValueRow(label = stringResource(R.string.procurement_received_at), value = Formatters.date(receipt.receivedAt))
        }
        FormFields(specs = mainSpecs, state = state, enabled = !runner.isBusy)
        SectionHeader(title = stringResource(R.string.procurement_items_count, rows.size))
        ProcurementHint(text = stringResource(R.string.procurement_return_hint))
        rows.forEachIndexed { index, item ->
            InfoCard(title = item.itemName) {
                Column(modifier = Modifier.fillMaxWidth()) {
                    ProcurementHint(
                        text = stringResource(
                            R.string.procurement_return_item_hint,
                            formatQuantity(item.quantity, item.unit),
                            formatQuantity(item.returnableQuantity, item.unit),
                        ),
                    )
                    FormFields(
                        specs = listOf(FieldSpec.Number("items.$index.quantity", quantityLabel)),
                        state = state,
                        modifier = Modifier.padding(bottom = 8.dp),
                        enabled = !runner.isBusy,
                    )
                }
            }
        }
    }
}
