package com.appswebnetkz.crm369.feature.procurement

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.TextInputDialog
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Purchase request: budget, decision, items with supplier quotations and the follow-up actions. */
@Composable
fun ProcurementRequestScreen(requestId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { ProcurementRepository(api) }
    val runner = rememberActionRunner()
    val procurement = rememberLoadable(api, requestId) { repository.load() }
    var confirmApprove by remember { mutableStateOf(false) }
    var showReject by remember { mutableStateOf(false) }
    val decisionMessage = stringResource(R.string.procurement_decision_saved)
    val request = procurement.data?.requests?.firstOrNull { it.id == requestId }

    CrmScaffold(
        title = request?.number ?: stringResource(R.string.procurement_request),
        subtitle = request?.let { procurementStatusLabel(it.status) },
    ) { padding ->
        LoadableContent(loadable = procurement, modifier = Modifier.padding(padding)) { data ->
            val item = data.requests.firstOrNull { it.id == requestId }
            if (item == null) {
                ProcurementNotFound()
            } else {
                val can = data.can
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState()),
                ) {
                    ProcurementHeader(status = item.status, number = item.number, title = item.title)

                    InfoCard(title = stringResource(R.string.procurement_section_budget)) {
                        KeyValueRow(
                            label = stringResource(R.string.procurement_budget),
                            value = formatMoney(item.budgetAmount, item.currency),
                            icon = AppIcons.Money,
                        )
                        KeyValueRow(
                            label = stringResource(R.string.procurement_estimated_total),
                            value = formatMoney(item.estimatedTotal, item.currency),
                            icon = AppIcons.Receipt,
                        )
                        if (item.budgetAmount > 0) {
                            KeyValueRow(
                                label = stringResource(R.string.procurement_budget_usage),
                                value = Formatters.number(item.estimatedTotal / item.budgetAmount * 100, 0) + "%",
                                icon = AppIcons.PieChart,
                            )
                        }
                        KeyValueRow(label = stringResource(R.string.procurement_needed_at), value = Formatters.date(item.neededAt), icon = AppIcons.Calendar)
                        KeyValueRow(label = stringResource(R.string.procurement_requested_by), value = item.requestedBy, icon = AppIcons.Person)
                        KeyValueRow(label = stringResource(R.string.procurement_submitted_at), value = Formatters.dateTime(item.submittedAt), icon = AppIcons.Time)
                        KeyValueRow(label = stringResource(R.string.procurement_approved_by), value = item.approvedBy, icon = AppIcons.Done)
                        KeyValueRow(label = stringResource(R.string.procurement_approved_at), value = Formatters.dateTime(item.approvedAt), icon = AppIcons.History)
                        val order = item.order
                        if (order != null) {
                            KeyValueRow(
                                label = stringResource(R.string.procurement_order),
                                value = listOf(order.number, procurementStatusLabel(order.status), formatMoney(order.totalAmount, item.currency))
                                    .joinToString(" · "),
                                icon = AppIcons.Shipping,
                                onClick = { navigator.open(ProcurementRoutes.order(order.id)) },
                            )
                        }
                    }

                    val justification = item.justification
                    if (justification != null) {
                        InfoCard(title = stringResource(R.string.procurement_justification)) {
                            Text(
                                text = justification,
                                style = MaterialTheme.typography.bodyMedium,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                            )
                        }
                    }
                    val rejectionReason = item.rejectionReason
                    if (rejectionReason != null) {
                        InfoCard(title = stringResource(R.string.procurement_rejection_reason)) {
                            Text(
                                text = rejectionReason,
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.error,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                            )
                        }
                    }

                    if (can.approveBudget && item.status == ProcurementRequest.STATUS_PENDING_APPROVAL) {
                        Spacer(Modifier.height(4.dp))
                        ProcurementActionButton(
                            text = stringResource(R.string.procurement_approve),
                            icon = AppIcons.Check,
                            enabled = !runner.isBusy,
                        ) { confirmApprove = true }
                        ProcurementActionButton(
                            text = stringResource(R.string.procurement_reject),
                            icon = AppIcons.Block,
                            enabled = !runner.isBusy,
                            outlined = true,
                        ) { showReject = true }
                    }
                    if (can.manageOrders && item.status == ProcurementRequest.STATUS_APPROVED && item.order == null) {
                        Spacer(Modifier.height(4.dp))
                        ProcurementActionButton(
                            text = stringResource(R.string.procurement_create_order),
                            icon = AppIcons.Shipping,
                            enabled = !runner.isBusy,
                        ) { navigator.open(ProcurementRoutes.newOrder(requestId)) }
                    }

                    SectionHeader(title = stringResource(R.string.procurement_items_count, item.items.size))
                    item.items.forEach { requestItem ->
                        RequestItemCard(
                            requestItem = requestItem,
                            currency = item.currency,
                            canQuote = can.manage,
                            onAddQuote = { navigator.open(ProcurementRoutes.newQuotation(requestId, requestItem.id)) },
                        )
                    }
                    Spacer(Modifier.height(24.dp))
                }
            }
        }
    }

    if (confirmApprove) {
        ConfirmDialog(
            title = stringResource(R.string.procurement_approve_title),
            text = request?.let { stringResource(R.string.procurement_approve_text, formatMoney(it.budgetAmount, it.currency)) },
            confirmLabel = stringResource(R.string.procurement_approve),
            onDismiss = { confirmApprove = false },
            onConfirm = {
                confirmApprove = false
                runner.execute(successMessage = decisionMessage, onSuccess = { _: Unit -> procurement.refresh() }) {
                    repository.approve(requestId)
                }
            },
        )
    }

    if (showReject) {
        val reasonRequired = stringResource(R.string.procurement_reason_required)
        TextInputDialog(
            title = stringResource(R.string.procurement_reject_title),
            label = stringResource(R.string.procurement_rejection_reason),
            confirmLabel = stringResource(R.string.procurement_reject),
            singleLine = false,
            onDismiss = { showReject = false },
            onConfirm = { reason ->
                if (reason.isBlank()) {
                    messenger.show(reasonRequired)
                } else {
                    showReject = false
                    runner.execute(successMessage = decisionMessage, onSuccess = { _: Unit -> procurement.refresh() }) {
                        repository.reject(requestId, reason.trim())
                    }
                }
            },
        )
    }
}

@Composable
private fun RequestItemCard(
    requestItem: ProcurementRequestItem,
    currency: String,
    canQuote: Boolean,
    onAddQuote: () -> Unit,
) {
    InfoCard(title = requestItem.itemName) {
        KeyValueRow(label = stringResource(R.string.procurement_sku), value = requestItem.sku)
        KeyValueRow(label = stringResource(R.string.procurement_quantity), value = formatQuantity(requestItem.quantity, requestItem.unit))
        KeyValueRow(label = stringResource(R.string.procurement_target_unit_price), value = formatMoney(requestItem.targetUnitPrice, currency))
        KeyValueRow(
            label = stringResource(R.string.procurement_target_total),
            value = formatMoney(requestItem.targetUnitPrice * requestItem.quantity, currency),
        )
        KeyValueRow(label = stringResource(R.string.procurement_warehouse_place), value = requestItem.warehousePlace, icon = AppIcons.Place)
        KeyValueRow(label = stringResource(R.string.procurement_production_reference), value = requestItem.productionReference, icon = AppIcons.Production)
        KeyValueRow(label = stringResource(R.string.procurement_notes), value = requestItem.notes)

        Text(
            text = stringResource(R.string.procurement_quotations),
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.primary,
            modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 8.dp),
        )
        if (requestItem.quotations.isEmpty()) {
            ProcurementHint(text = stringResource(R.string.procurement_empty_quotes))
        }
        val bestLabel = stringResource(R.string.procurement_best_quote)
        requestItem.quotations.forEach { quotation ->
            val isBest = quotation.id == requestItem.bestQuotationId
            val validity = Formatters.date(quotation.validUntil).ifBlank { null }?.let { stringResource(R.string.procurement_valid_until_short, it) }
            ListRow(
                title = quotation.supplierName,
                overline = if (isBest) bestLabel else null,
                subtitle = listOfNotNull(
                    stringResource(
                        R.string.procurement_quote_prices,
                        formatMoney(quotation.unitPrice, quotation.currency),
                        Formatters.number(quotation.taxPercent, 2) + "%",
                        formatMoney(quotation.deliveryCost, quotation.currency),
                    ),
                    stringResource(
                        R.string.procurement_quote_terms,
                        formatMoney(quotation.landedUnitPrice, quotation.currency),
                        quotation.leadTimeDays,
                    ),
                    validity,
                ).joinToString("\n"),
                maxSubtitleLines = 3,
                trailing = {
                    StatusChip(
                        text = formatMoney(quotation.landedTotal, quotation.currency),
                        color = if (isBest) LocalExtendedColors.current.success else MaterialTheme.colorScheme.secondary,
                    )
                },
            )
        }
        if (canQuote) {
            TextButton(onClick = onAddQuote, modifier = Modifier.padding(horizontal = 8.dp)) {
                Icon(AppIcons.Add, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.size(8.dp))
                Text(stringResource(R.string.procurement_add_quote))
            }
        }
    }
}
