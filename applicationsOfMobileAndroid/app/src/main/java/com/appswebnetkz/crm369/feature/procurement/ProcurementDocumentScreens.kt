package com.appswebnetkz.crm369.feature.procurement

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
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
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Purchase order: amounts, items with received/remaining quantities, receipts and returns. */
@Composable
fun ProcurementOrderScreen(orderId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { ProcurementRepository(api) }
    val runner = rememberActionRunner()
    val procurement = rememberLoadable(api, orderId) { repository.load() }
    var confirmSend by remember { mutableStateOf(false) }
    val sentMessage = stringResource(R.string.procurement_order_sent)
    val order = procurement.data?.orders?.firstOrNull { it.id == orderId }

    CrmScaffold(
        title = order?.number ?: stringResource(R.string.procurement_order),
        subtitle = order?.supplierName,
    ) { padding ->
        LoadableContent(loadable = procurement, modifier = Modifier.padding(padding)) { data ->
            val item = data.orders.firstOrNull { it.id == orderId }
            if (item == null) {
                ProcurementNotFound()
            } else {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState()),
                ) {
                    ProcurementHeader(status = item.status, number = item.number, title = item.supplierName)
                    InfoCard(title = stringResource(R.string.procurement_section_order)) {
                        val requestId = item.purchaseRequestId
                        KeyValueRow(
                            label = stringResource(R.string.procurement_request),
                            value = listOfNotNull(item.requestNumber, item.requestTitle).joinToString(" · "),
                            icon = AppIcons.Document,
                            onClick = if (requestId != null) {
                                { navigator.open(ProcurementRoutes.purchaseRequest(requestId)) }
                            } else {
                                null
                            },
                        )
                        val supplierId = item.supplierId
                        KeyValueRow(
                            label = stringResource(R.string.procurement_supplier),
                            value = item.supplierName,
                            icon = AppIcons.Store,
                            onClick = if (supplierId != null) {
                                { navigator.open(ProcurementRoutes.supplier(supplierId)) }
                            } else {
                                null
                            },
                        )
                        KeyValueRow(label = stringResource(R.string.procurement_ordered_at), value = Formatters.date(item.orderedAt), icon = AppIcons.Calendar)
                        KeyValueRow(label = stringResource(R.string.procurement_expected_at), value = Formatters.date(item.expectedAt), icon = AppIcons.Schedule)
                        KeyValueRow(label = stringResource(R.string.procurement_sent_at), value = Formatters.dateTime(item.sentAt), icon = AppIcons.Send)
                        KeyValueRow(label = stringResource(R.string.procurement_notes), value = item.notes)
                    }
                    InfoCard(title = stringResource(R.string.procurement_section_amounts)) {
                        KeyValueRow(label = stringResource(R.string.procurement_subtotal), value = formatMoney(item.subtotal, item.currency))
                        KeyValueRow(label = stringResource(R.string.procurement_tax_amount), value = formatMoney(item.taxAmount, item.currency))
                        KeyValueRow(label = stringResource(R.string.procurement_delivery_cost), value = formatMoney(item.deliveryAmount, item.currency))
                        KeyValueRow(label = stringResource(R.string.procurement_total), value = formatMoney(item.totalAmount, item.currency), icon = AppIcons.Money)
                    }

                    if (data.can.manageOrders && item.canBeSent) {
                        ProcurementActionButton(
                            text = stringResource(R.string.procurement_send_order),
                            icon = AppIcons.Send,
                            enabled = !runner.isBusy,
                        ) { confirmSend = true }
                    }
                    if (data.can.receiveOrders && item.isReceivable) {
                        ProcurementActionButton(
                            text = stringResource(R.string.procurement_record_receipt),
                            icon = AppIcons.Download,
                            enabled = !runner.isBusy && data.places.isNotEmpty(),
                            outlined = item.canBeSent,
                        ) { navigator.open(ProcurementRoutes.newReceipt(orderId)) }
                        if (data.places.isEmpty()) {
                            ProcurementHint(text = stringResource(R.string.procurement_no_places), color = MaterialTheme.colorScheme.error)
                        }
                    }

                    SectionHeader(title = stringResource(R.string.procurement_items_count, item.items.size))
                    item.items.forEach { orderItem ->
                        InfoCard(title = orderItem.itemName) {
                            KeyValueRow(label = stringResource(R.string.procurement_sku), value = orderItem.sku)
                            KeyValueRow(label = stringResource(R.string.procurement_quantity), value = formatQuantity(orderItem.quantity, orderItem.unit))
                            KeyValueRow(label = stringResource(R.string.procurement_received_quantity), value = formatQuantity(orderItem.receivedQuantity, orderItem.unit))
                            KeyValueRow(label = stringResource(R.string.procurement_remaining_quantity), value = formatQuantity(orderItem.remainingQuantity, orderItem.unit))
                            if (orderItem.returnedQuantity > 0) {
                                KeyValueRow(label = stringResource(R.string.procurement_returned_quantity), value = formatQuantity(orderItem.returnedQuantity, orderItem.unit))
                            }
                            KeyValueRow(label = stringResource(R.string.procurement_unit_price), value = formatMoney(orderItem.unitPrice, item.currency))
                            KeyValueRow(label = stringResource(R.string.procurement_tax_percent), value = Formatters.number(orderItem.taxPercent, 2) + "%")
                            KeyValueRow(label = stringResource(R.string.procurement_line_total), value = formatMoney(orderItem.lineTotal, item.currency))
                            KeyValueRow(label = stringResource(R.string.procurement_warehouse_place), value = orderItem.warehousePlace, icon = AppIcons.Place)
                            KeyValueRow(
                                label = stringResource(R.string.procurement_warehouse_stock),
                                value = orderItem.warehouseStock?.let { formatQuantity(it, orderItem.unit) },
                                icon = AppIcons.Warehouses,
                            )
                        }
                    }

                    val receipts = data.receipts.filter { it.purchaseOrderId == orderId }
                    if (receipts.isNotEmpty()) {
                        SectionHeader(title = stringResource(R.string.procurement_tab_receipts))
                        InfoCard {
                            receipts.forEach { receipt ->
                                ListRow(
                                    title = receipt.number,
                                    subtitle = listOfNotNull(
                                        Formatters.date(receipt.receivedAt).ifBlank { null },
                                        formatMoney(receipt.totalAmount, receipt.currency),
                                    ).joinToString(" · "),
                                    leading = { Icon(AppIcons.Receipt, contentDescription = null) },
                                    onClick = { navigator.open(ProcurementRoutes.receipt(receipt.id)) },
                                )
                            }
                        }
                    }
                    val returns = data.returns.filter { it.purchaseOrderId == orderId }
                    if (returns.isNotEmpty()) {
                        SectionHeader(title = stringResource(R.string.procurement_tab_returns))
                        InfoCard {
                            returns.forEach { purchaseReturn ->
                                ListRow(
                                    title = purchaseReturn.number,
                                    subtitle = listOfNotNull(
                                        Formatters.date(purchaseReturn.returnedAt).ifBlank { null },
                                        formatMoney(purchaseReturn.totalAmount, purchaseReturn.currency),
                                    ).joinToString(" · "),
                                    leading = { Icon(AppIcons.Archive, contentDescription = null) },
                                    onClick = { navigator.open(ProcurementRoutes.purchaseReturn(purchaseReturn.id)) },
                                )
                            }
                        }
                    }
                    Spacer(Modifier.height(24.dp))
                }
            }
        }
    }

    if (confirmSend) {
        ConfirmDialog(
            title = stringResource(R.string.procurement_send_title),
            text = stringResource(R.string.procurement_send_text),
            confirmLabel = stringResource(R.string.procurement_send_order),
            onDismiss = { confirmSend = false },
            onConfirm = {
                confirmSend = false
                runner.execute(successMessage = sentMessage, onSuccess = { _: Unit -> procurement.refresh() }) {
                    repository.sendOrder(orderId)
                }
            },
        )
    }
}

/** Goods receipt: posted quantities and the return action. */
@Composable
fun ProcurementReceiptScreen(receiptId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api, receiptId) { repository.load() }
    val receipt = procurement.data?.receipts?.firstOrNull { it.id == receiptId }

    CrmScaffold(
        title = receipt?.number ?: stringResource(R.string.procurement_receipt),
        subtitle = receipt?.supplierName,
    ) { padding ->
        LoadableContent(loadable = procurement, modifier = Modifier.padding(padding)) { data ->
            val item = data.receipts.firstOrNull { it.id == receiptId }
            if (item == null) {
                ProcurementNotFound()
            } else {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState()),
                ) {
                    ProcurementHeader(status = item.status, number = item.number, title = item.supplierName)
                    InfoCard(title = stringResource(R.string.procurement_section_receipt)) {
                        KeyValueRow(
                            label = stringResource(R.string.procurement_order),
                            value = item.orderNumber,
                            icon = AppIcons.Shipping,
                            onClick = { navigator.open(ProcurementRoutes.order(item.purchaseOrderId)) },
                        )
                        KeyValueRow(label = stringResource(R.string.procurement_received_at), value = Formatters.date(item.receivedAt), icon = AppIcons.Calendar)
                        KeyValueRow(label = stringResource(R.string.procurement_received_by), value = item.receivedBy, icon = AppIcons.Person)
                        KeyValueRow(label = stringResource(R.string.procurement_external_reference), value = item.externalReference, icon = AppIcons.Tag)
                        KeyValueRow(label = stringResource(R.string.procurement_notes), value = item.notes)
                        KeyValueRow(label = stringResource(R.string.procurement_total), value = formatMoney(item.totalAmount, item.currency), icon = AppIcons.Money)
                    }
                    if (data.can.returnGoods && item.isReturnable) {
                        ProcurementActionButton(
                            text = stringResource(R.string.procurement_record_return),
                            icon = AppIcons.Archive,
                            enabled = true,
                            outlined = true,
                        ) { navigator.open(ProcurementRoutes.newReturn(receiptId)) }
                    }
                    SectionHeader(title = stringResource(R.string.procurement_items_count, item.items.size))
                    item.items.forEach { receiptItem ->
                        InfoCard(title = receiptItem.itemName) {
                            KeyValueRow(label = stringResource(R.string.procurement_sku), value = receiptItem.sku)
                            KeyValueRow(label = stringResource(R.string.procurement_quantity), value = formatQuantity(receiptItem.quantity, receiptItem.unit))
                            if (receiptItem.returnedQuantity > 0) {
                                KeyValueRow(label = stringResource(R.string.procurement_returned_quantity), value = formatQuantity(receiptItem.returnedQuantity, receiptItem.unit))
                            }
                            KeyValueRow(label = stringResource(R.string.procurement_returnable_quantity), value = formatQuantity(receiptItem.returnableQuantity, receiptItem.unit))
                            KeyValueRow(label = stringResource(R.string.procurement_unit_price), value = formatMoney(receiptItem.unitPrice, item.currency))
                            KeyValueRow(label = stringResource(R.string.procurement_line_total), value = formatMoney(receiptItem.lineTotal, item.currency))
                            KeyValueRow(label = stringResource(R.string.procurement_warehouse_item), value = receiptItem.warehouseItemName, icon = AppIcons.Warehouses)
                        }
                    }
                    Spacer(Modifier.height(24.dp))
                }
            }
        }
    }
}

/** Return to the supplier. */
@Composable
fun ProcurementReturnScreen(returnId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api, returnId) { repository.load() }
    val purchaseReturn = procurement.data?.returns?.firstOrNull { it.id == returnId }

    CrmScaffold(
        title = purchaseReturn?.number ?: stringResource(R.string.procurement_return),
        subtitle = purchaseReturn?.supplierName,
    ) { padding ->
        LoadableContent(loadable = procurement, modifier = Modifier.padding(padding)) { data ->
            val item = data.returns.firstOrNull { it.id == returnId }
            if (item == null) {
                ProcurementNotFound()
            } else {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState()),
                ) {
                    ProcurementHeader(status = item.status, number = item.number, title = item.supplierName)
                    InfoCard(title = stringResource(R.string.procurement_section_return)) {
                        KeyValueRow(
                            label = stringResource(R.string.procurement_order),
                            value = item.orderNumber,
                            icon = AppIcons.Shipping,
                            onClick = { navigator.open(ProcurementRoutes.order(item.purchaseOrderId)) },
                        )
                        KeyValueRow(label = stringResource(R.string.procurement_returned_at), value = Formatters.date(item.returnedAt), icon = AppIcons.Calendar)
                        KeyValueRow(label = stringResource(R.string.procurement_created_by), value = item.createdBy, icon = AppIcons.Person)
                        KeyValueRow(label = stringResource(R.string.procurement_reason), value = item.reason, icon = AppIcons.Info)
                        KeyValueRow(label = stringResource(R.string.procurement_total), value = formatMoney(item.totalAmount, item.currency), icon = AppIcons.Money)
                    }
                    SectionHeader(title = stringResource(R.string.procurement_items_count, item.items.size))
                    item.items.forEach { returnItem ->
                        InfoCard(title = returnItem.itemName) {
                            KeyValueRow(label = stringResource(R.string.procurement_sku), value = returnItem.sku)
                            KeyValueRow(label = stringResource(R.string.procurement_quantity), value = formatQuantity(returnItem.quantity, returnItem.unit))
                            KeyValueRow(label = stringResource(R.string.procurement_unit_price), value = formatMoney(returnItem.unitPrice, item.currency))
                            KeyValueRow(label = stringResource(R.string.procurement_line_total), value = formatMoney(returnItem.lineTotal, item.currency))
                            KeyValueRow(
                                label = stringResource(R.string.procurement_warehouse_stock),
                                value = returnItem.warehouseStock?.let { formatQuantity(it, returnItem.unit) },
                                icon = AppIcons.Warehouses,
                            )
                        }
                    }
                    Spacer(Modifier.height(24.dp))
                }
            }
        }
    }
}

/** Supplier card with contacts, terms and its orders. */
@Composable
fun ProcurementSupplierScreen(supplierId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api, supplierId) { repository.load() }
    val supplier = procurement.data?.suppliers?.firstOrNull { it.id == supplierId }
    val canManage = procurement.data?.can?.manage == true

    CrmScaffold(
        title = supplier?.name ?: stringResource(R.string.procurement_supplier),
        actions = {
            if (supplier != null && canManage) {
                IconButton(onClick = { navigator.open(ProcurementRoutes.editSupplier(supplierId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = procurement, modifier = Modifier.padding(padding)) { data ->
            val item = data.suppliers.firstOrNull { it.id == supplierId }
            if (item == null) {
                ProcurementNotFound()
            } else {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState()),
                ) {
                    Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp)) {
                        if (item.isActive) {
                            StatusChip(text = stringResource(R.string.procurement_supplier_active), color = LocalExtendedColors.current.success)
                        } else {
                            StatusChip(text = stringResource(R.string.procurement_supplier_inactive), color = MaterialTheme.colorScheme.outline)
                        }
                        Spacer(Modifier.height(8.dp))
                        Text(item.name, style = MaterialTheme.typography.titleLarge)
                    }
                    InfoCard(title = stringResource(R.string.procurement_section_contacts)) {
                        KeyValueRow(label = stringResource(R.string.procurement_bin), value = item.bin, icon = AppIcons.Key)
                        KeyValueRow(label = stringResource(R.string.procurement_contact_person), value = item.contactPerson, icon = AppIcons.Person)
                        KeyValueRow(
                            label = stringResource(R.string.procurement_phone),
                            value = item.phone,
                            icon = AppIcons.Phone,
                            onClick = item.phone?.let { phone -> { navigator.dial(phone) } },
                        )
                        KeyValueRow(
                            label = stringResource(R.string.procurement_email),
                            value = item.email,
                            icon = AppIcons.Email,
                            onClick = item.email?.let { email -> { navigator.email(email) } },
                        )
                        val contact = data.contacts.firstOrNull { it.id == item.contactId }
                        KeyValueRow(label = stringResource(R.string.procurement_contact), value = contact?.name, icon = AppIcons.Contacts)
                    }
                    InfoCard(title = stringResource(R.string.procurement_section_terms)) {
                        KeyValueRow(label = stringResource(R.string.procurement_currency), value = item.currency, icon = AppIcons.Money)
                        KeyValueRow(
                            label = stringResource(R.string.procurement_payment_terms_days),
                            value = stringResource(R.string.procurement_days, item.paymentTermsDays),
                            icon = AppIcons.Payment,
                        )
                        KeyValueRow(
                            label = stringResource(R.string.procurement_lead_time_days),
                            value = stringResource(R.string.procurement_days, item.leadTimeDays),
                            icon = AppIcons.Shipping,
                        )
                        KeyValueRow(label = stringResource(R.string.procurement_rating), value = item.rating?.let { Formatters.number(it, 2) }, icon = AppIcons.Star)
                        KeyValueRow(label = stringResource(R.string.procurement_quotation_count), value = item.quotationCount.toString())
                        KeyValueRow(label = stringResource(R.string.procurement_order_count), value = item.orderCount.toString())
                        KeyValueRow(label = stringResource(R.string.procurement_notes), value = item.notes)
                    }
                    val orders = data.orders.filter { it.supplierId == supplierId }
                    if (orders.isNotEmpty()) {
                        SectionHeader(title = stringResource(R.string.procurement_tab_orders))
                        InfoCard {
                            orders.forEach { order ->
                                ListRow(
                                    title = order.number,
                                    subtitle = listOfNotNull(
                                        order.requestTitle,
                                        formatMoney(order.totalAmount, order.currency),
                                        Formatters.date(order.orderedAt).ifBlank { null },
                                    ).joinToString(" · "),
                                    trailing = {
                                        StatusChip(text = procurementStatusLabel(order.status), color = procurementStatusColor(order.status))
                                    },
                                    onClick = { navigator.open(ProcurementRoutes.order(order.id)) },
                                )
                            }
                        }
                    }
                    Spacer(Modifier.height(24.dp))
                }
            }
        }
    }
}
