package com.appswebnetkz.crm369.feature.procurement

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ChipRow
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

private const val TAB_REQUESTS = "requests"
private const val TAB_ORDERS = "orders"
private const val TAB_RECEIPTS = "receipts"
private const val TAB_RETURNS = "returns"
private const val TAB_SUPPLIERS = "suppliers"

/** Procurement home: summary cards and the requests / orders / receipts / returns / suppliers tabs. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProcurementScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api) { repository.load() }
    var tab by rememberSaveable { mutableStateOf(TAB_REQUESTS) }
    val loaded = procurement.data

    CrmScaffold(
        title = stringResource(R.string.module_procurement),
        floatingActionButton = {
            if (loaded != null && tab == TAB_REQUESTS) {
                FloatingActionButton(onClick = { navigator.open(ProcurementRoutes.NEW_REQUEST) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.procurement_new_request))
                }
            } else if (loaded != null && tab == TAB_SUPPLIERS && loaded.can.manage) {
                FloatingActionButton(onClick = { navigator.open(ProcurementRoutes.NEW_SUPPLIER) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.procurement_new_supplier))
                }
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            val tabs = listOf(
                TAB_REQUESTS to stringResource(R.string.procurement_tab_requests),
                TAB_ORDERS to stringResource(R.string.procurement_tab_orders),
                TAB_RECEIPTS to stringResource(R.string.procurement_tab_receipts),
                TAB_RETURNS to stringResource(R.string.procurement_tab_returns),
                TAB_SUPPLIERS to stringResource(R.string.procurement_tab_suppliers),
            )
            ChipRow {
                tabs.forEach { (key, label) ->
                    val count = loaded?.let { data ->
                        when (key) {
                            TAB_REQUESTS -> data.requests.size
                            TAB_ORDERS -> data.orders.size
                            TAB_RECEIPTS -> data.receipts.size
                            TAB_RETURNS -> data.returns.size
                            else -> data.suppliers.size
                        }
                    }
                    FilterChip(
                        selected = tab == key,
                        onClick = { tab = key },
                        label = {
                            Text(if (count != null && count > 0) stringResource(R.string.procurement_tab_count, label, count) else label)
                        },
                    )
                }
            }
            LoadableContent(loadable = procurement) { data ->
                LazyColumn(modifier = Modifier.fillMaxSize()) {
                    item(key = "summary") { ProcurementSummaryGrid(summary = data.summary) }
                    when (tab) {
                        TAB_ORDERS -> {
                            if (data.orders.isEmpty()) {
                                item(key = "empty") { EmptyTabText(stringResource(R.string.procurement_empty_orders)) }
                            }
                            items(data.orders, key = { "order-${it.id}" }) { order ->
                                OrderRow(order = order, onClick = { navigator.open(ProcurementRoutes.order(order.id)) })
                                RowDivider()
                            }
                        }
                        TAB_RECEIPTS -> {
                            if (data.receipts.isEmpty()) {
                                item(key = "empty") { EmptyTabText(stringResource(R.string.procurement_empty_receipts)) }
                            }
                            items(data.receipts, key = { "receipt-${it.id}" }) { receipt ->
                                ReceiptRow(receipt = receipt, onClick = { navigator.open(ProcurementRoutes.receipt(receipt.id)) })
                                RowDivider()
                            }
                        }
                        TAB_RETURNS -> {
                            if (data.returns.isEmpty()) {
                                item(key = "empty") { EmptyTabText(stringResource(R.string.procurement_empty_returns)) }
                            }
                            items(data.returns, key = { "return-${it.id}" }) { purchaseReturn ->
                                ReturnRow(purchaseReturn = purchaseReturn, onClick = { navigator.open(ProcurementRoutes.purchaseReturn(purchaseReturn.id)) })
                                RowDivider()
                            }
                        }
                        TAB_SUPPLIERS -> {
                            if (data.suppliers.isEmpty()) {
                                item(key = "empty") { EmptyTabText(stringResource(R.string.procurement_empty_suppliers)) }
                            }
                            items(data.suppliers, key = { "supplier-${it.id}" }) { supplier ->
                                SupplierRow(supplier = supplier, onClick = { navigator.open(ProcurementRoutes.supplier(supplier.id)) })
                                RowDivider()
                            }
                        }
                        else -> {
                            if (data.requests.isEmpty()) {
                                item(key = "empty") { EmptyTabText(stringResource(R.string.procurement_empty_requests)) }
                            }
                            items(data.requests, key = { "request-${it.id}" }) { request ->
                                RequestRow(request = request, onClick = { navigator.open(ProcurementRoutes.purchaseRequest(request.id)) })
                                RowDivider()
                            }
                        }
                    }
                    item(key = "bottom") { Spacer(Modifier.height(88.dp)) }
                }
            }
        }
    }
}

@Composable
private fun ProcurementSummaryGrid(summary: ProcurementSummary) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            SummaryTile(
                label = stringResource(R.string.procurement_pending_approvals),
                value = Formatters.number(summary.pendingApprovals.toLong()),
                icon = AppIcons.Schedule,
                modifier = Modifier.weight(1f),
            )
            SummaryTile(
                label = stringResource(R.string.procurement_active_orders),
                value = Formatters.number(summary.activeOrders.toLong()),
                icon = AppIcons.Shipping,
                modifier = Modifier.weight(1f),
            )
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            SummaryTile(
                label = stringResource(R.string.procurement_month_receipts),
                value = formatCurrencyTotals(summary.monthReceipts),
                icon = AppIcons.Receipt,
                modifier = Modifier.weight(1f),
            )
            SummaryTile(
                label = stringResource(R.string.procurement_potential_savings),
                value = formatCurrencyTotals(summary.potentialSavings),
                icon = AppIcons.Money,
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun SummaryTile(label: String, value: String, icon: ImageVector, modifier: Modifier = Modifier) {
    Surface(
        color = MaterialTheme.colorScheme.surfaceContainerLow,
        shape = MaterialTheme.shapes.medium,
        modifier = modifier,
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Icon(icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.height(6.dp))
            Text(
                text = value,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = label,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 2,
            )
        }
    }
}

@Composable
private fun EmptyTabText(text: String) {
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier.padding(horizontal = 16.dp, vertical = 24.dp),
    )
}

@Composable
private fun RequestRow(request: ProcurementRequest, onClick: () -> Unit) {
    val details = listOfNotNull(
        stringResource(R.string.procurement_estimate_of_budget, formatMoney(request.estimatedTotal, request.currency), formatMoney(request.budgetAmount, request.currency)),
        Formatters.date(request.neededAt).ifBlank { null }?.let { stringResource(R.string.procurement_needed_short, it) },
        request.requestedBy,
    ).joinToString(" · ")
    ListRow(
        title = request.title,
        overline = request.number,
        subtitle = details,
        trailing = { StatusChip(text = procurementStatusLabel(request.status), color = procurementStatusColor(request.status)) },
        onClick = onClick,
    )
}

@Composable
private fun OrderRow(order: ProcurementOrder, onClick: () -> Unit) {
    val details = listOfNotNull(
        order.requestTitle,
        formatMoney(order.totalAmount, order.currency),
        Formatters.date(order.orderedAt).ifBlank { null },
    ).joinToString(" · ")
    ListRow(
        title = order.supplierName,
        overline = order.number,
        subtitle = details,
        trailing = { StatusChip(text = procurementStatusLabel(order.status), color = procurementStatusColor(order.status)) },
        onClick = onClick,
    )
}

@Composable
private fun ReceiptRow(receipt: ProcurementReceipt, onClick: () -> Unit) {
    val details = listOfNotNull(
        receipt.orderNumber,
        formatMoney(receipt.totalAmount, receipt.currency),
        Formatters.date(receipt.receivedAt).ifBlank { null },
    ).joinToString(" · ")
    ListRow(
        title = receipt.supplierName,
        overline = receipt.number,
        subtitle = details,
        trailing = { StatusChip(text = procurementStatusLabel(receipt.status), color = procurementStatusColor(receipt.status)) },
        onClick = onClick,
    )
}

@Composable
private fun ReturnRow(purchaseReturn: ProcurementReturn, onClick: () -> Unit) {
    val details = listOfNotNull(
        purchaseReturn.reason,
        formatMoney(purchaseReturn.totalAmount, purchaseReturn.currency),
        Formatters.date(purchaseReturn.returnedAt).ifBlank { null },
    ).joinToString(" · ")
    ListRow(
        title = purchaseReturn.supplierName,
        overline = purchaseReturn.number,
        subtitle = details,
        trailing = { StatusChip(text = procurementStatusLabel(purchaseReturn.status), color = procurementStatusColor(purchaseReturn.status)) },
        onClick = onClick,
    )
}

@Composable
private fun SupplierRow(supplier: ProcurementSupplier, onClick: () -> Unit) {
    val details = listOfNotNull(
        supplier.contactPerson,
        supplier.phone ?: supplier.email,
        supplier.rating?.let { stringResource(R.string.procurement_rating_short, Formatters.number(it, 2)) },
    ).joinToString(" · ")
    ListRow(
        title = supplier.name,
        overline = supplier.bin?.let { stringResource(R.string.procurement_bin_short, it) },
        subtitle = details.ifBlank { null },
        trailing = {
            if (supplier.isActive) {
                StatusChip(text = stringResource(R.string.procurement_supplier_active), color = LocalExtendedColors.current.success)
            } else {
                StatusChip(text = stringResource(R.string.procurement_supplier_inactive), color = MaterialTheme.colorScheme.outline)
            }
        },
        onClick = onClick,
    )
}
