package com.appswebnetkz.crm369.feature.warehouses

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.InlineProgress
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadMoreEffect
import com.appswebnetkz.crm369.ui.components.Loadable
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.PagedContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SearchField
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.components.rememberPagedLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Warehouse card: address hierarchy (rows → columns → floors) and the paged list of items. */
@Composable
fun WarehouseDetailScreen(warehouseId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { WarehousesRepository(api) }
    val warehouse = rememberLoadable(api, warehouseId) { repository.warehouse(warehouseId) }
    var tab by rememberSaveable { mutableStateOf(0) }

    CrmScaffold(
        title = warehouse.data?.name ?: stringResource(R.string.warehouses_title),
        subtitle = warehouse.data?.let { stringResource(R.string.warehouses_area_line, Formatters.number(it.areaSqm)) },
        actions = {
            IconButton(onClick = { navigator.open(WarehousesRoutes.SCAN) }) {
                Icon(AppIcons.QrScan, contentDescription = stringResource(R.string.warehouses_scan))
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            TabRow(selectedTabIndex = tab) {
                Tab(
                    selected = tab == 0,
                    onClick = { tab = 0 },
                    text = { Text(stringResource(R.string.warehouses_tab_structure)) },
                )
                Tab(
                    selected = tab == 1,
                    onClick = { tab = 1 },
                    text = { Text(stringResource(R.string.warehouses_tab_items)) },
                )
            }
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f),
            ) {
                if (tab == 0) {
                    StructureTab(
                        warehouse = warehouse,
                        onOpenFloor = { floorId -> navigator.open(WarehousesRoutes.floor(warehouseId, floorId)) },
                    )
                } else {
                    ItemsTab(repository = repository, warehouseId = warehouseId)
                }
            }
        }
    }
}

@Composable
private fun StructureTab(warehouse: Loadable<Warehouse>, onOpenFloor: (Long) -> Unit) {
    val copy = rememberWarehouseCopy()
    var expandedRows by remember { mutableStateOf(setOf<Long>()) }
    var expandedColumns by remember { mutableStateOf(setOf<Long>()) }

    LoadableContent(loadable = warehouse) { data ->
        LazyColumn(modifier = Modifier.fillMaxSize()) {
            item(key = "header") { WarehouseHeader(warehouse = data, onCopy = copy) }
            item(key = "structure-title") {
                SectionHeader(title = stringResource(R.string.warehouses_map_rows_title))
            }
            if (data.rows.isEmpty()) {
                item(key = "structure-empty") {
                    Text(
                        text = stringResource(R.string.warehouses_structure_empty),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    )
                }
            }
            data.rows.forEach { row ->
                val rowExpanded = row.id in expandedRows
                item(key = "row-${row.id}") {
                    HierarchyRow(
                        level = 0,
                        icon = WarehousesIcons.RowLevel,
                        title = row.name,
                        qrCode = row.qrCode,
                        details = listOf(
                            stringResource(R.string.warehouses_count_columns, row.columnCount),
                            stringResource(R.string.warehouses_count_floors, row.floorCount),
                            stringResource(R.string.warehouses_count_places, row.placeCount),
                        ).joinToString(" · "),
                        expanded = rowExpanded,
                        onClick = { expandedRows = if (rowExpanded) expandedRows - row.id else expandedRows + row.id },
                    )
                }
                if (rowExpanded) {
                    row.columns.forEach { column ->
                        val columnExpanded = column.id in expandedColumns
                        item(key = "column-${column.id}") {
                            HierarchyRow(
                                level = 1,
                                icon = WarehousesIcons.ColumnLevel,
                                title = column.name,
                                qrCode = column.qrCode,
                                details = listOf(
                                    stringResource(R.string.warehouses_count_floors, column.floorCount),
                                    stringResource(R.string.warehouses_count_places, column.placeCount),
                                ).joinToString(" · "),
                                expanded = columnExpanded,
                                onClick = {
                                    expandedColumns = if (columnExpanded) expandedColumns - column.id else expandedColumns + column.id
                                },
                            )
                        }
                        if (columnExpanded) {
                            column.floors.forEach { floor ->
                                item(key = "floor-${floor.id}") {
                                    HierarchyRow(
                                        level = 2,
                                        icon = WarehousesIcons.FloorLevel,
                                        title = floor.name,
                                        qrCode = floor.qrCode,
                                        details = stringResource(R.string.warehouses_count_places, floor.placeCount),
                                        expanded = null,
                                        onClick = { onOpenFloor(floor.id) },
                                    )
                                }
                            }
                        }
                    }
                }
            }
            item(key = "bottom-space") { Spacer(Modifier.height(24.dp)) }
        }
    }
}

@Composable
private fun WarehouseHeader(warehouse: Warehouse, onCopy: (String) -> Unit) {
    InfoCard {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 12.dp),
        ) {
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_rows),
                value = warehouse.rowCount.toString(),
                modifier = Modifier.weight(1f),
            )
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_columns),
                value = warehouse.columnCount.toString(),
                modifier = Modifier.weight(1f),
            )
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_floors),
                value = warehouse.floorCount.toString(),
                modifier = Modifier.weight(1f),
            )
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_places),
                value = warehouse.placeCount.toString(),
                modifier = Modifier.weight(1f),
            )
        }
        KeyValueRow(
            label = stringResource(R.string.warehouses_area_label),
            value = stringResource(R.string.warehouses_area_value, Formatters.number(warehouse.areaSqm)),
            icon = AppIcons.Warehouses,
        )
        KeyValueRow(
            label = stringResource(R.string.warehouses_warehouse_qr),
            value = warehouse.qrCode,
            icon = AppIcons.Copy,
            onClick = warehouse.qrCode?.let { code -> { onCopy(code) } },
        )
        KeyValueRow(
            label = stringResource(R.string.warehouses_updated),
            value = listOfNotNull(Formatters.dateTime(warehouse.updatedAt).ifBlank { null }, warehouse.updatedBy)
                .joinToString(" · "),
            icon = AppIcons.History,
        )
    }
}

@Composable
private fun HierarchyRow(
    level: Int,
    icon: ImageVector,
    title: String,
    qrCode: String?,
    details: String,
    expanded: Boolean?,
    onClick: () -> Unit,
) {
    val trailingIcon = when (expanded) {
        true -> AppIcons.ExpandLess
        false -> AppIcons.ExpandMore
        null -> AppIcons.ChevronRight
    }
    ListRow(
        title = title,
        subtitle = listOfNotNull(
            details.ifBlank { null },
            qrCode?.let { stringResource(R.string.warehouses_qr_value, it) },
        ).joinToString("\n"),
        leading = { Icon(icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
        trailing = { Icon(trailingIcon, contentDescription = null) },
        maxSubtitleLines = 3,
        modifier = Modifier.padding(start = (level * 24).dp),
        onClick = onClick,
    )
    RowDivider()
}

@Composable
private fun ItemsTab(repository: WarehousesRepository, warehouseId: Long) {
    val copy = rememberWarehouseCopy()
    val listState = rememberLazyListState()
    var query by rememberSaveable { mutableStateOf("") }
    var selected by remember { mutableStateOf<WarehouseItem?>(null) }
    val paged = rememberPagedLoadable(repository, warehouseId, distinctBy = { entry: WarehouseItem -> entry.id }) { page ->
        repository.items(warehouseId, page)
    }
    LoadMoreEffect(listState = listState, enabled = paged.hasMore) { paged.loadMore() }

    Column(modifier = Modifier.fillMaxSize()) {
        SearchField(
            value = query,
            onValueChange = { query = it },
            placeholder = stringResource(R.string.warehouses_items_search),
        )
        PagedContent(
            paged = paged,
            empty = {
                EmptyState(
                    title = stringResource(R.string.warehouses_inventory_empty),
                    message = stringResource(R.string.warehouses_inventory_empty_description),
                    icon = AppIcons.Inbox,
                )
            },
        ) { stock ->
            val filtered = stock.filter { it.matches(query) }
            LazyColumn(state = listState, modifier = Modifier.fillMaxSize()) {
                items(filtered, key = { it.id }) { entry ->
                    ItemRow(entry = entry, onClick = { selected = entry })
                    RowDivider()
                }
                if (filtered.isEmpty() && !paged.isLoadingMore) {
                    item(key = "no-match") {
                        Text(
                            text = stringResource(R.string.warehouses_items_no_match),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(16.dp),
                        )
                    }
                }
                if (paged.isLoadingMore) {
                    item(key = "loading-more") { InlineProgress() }
                }
            }
        }
    }

    val current = selected
    if (current != null) {
        ItemDialog(entry = current, onCopy = copy, onDismiss = { selected = null })
    }
}

@Composable
private fun ItemRow(entry: WarehouseItem, onClick: () -> Unit) {
    ListRow(
        title = entry.title,
        overline = entry.sku?.let { stringResource(R.string.warehouses_sku_value, it) },
        subtitle = listOfNotNull(
            entry.location?.path,
            entry.qrCode?.let { stringResource(R.string.warehouses_qr_value, it) },
        ).joinToString("\n").ifBlank { null },
        maxSubtitleLines = 3,
        trailing = {
            StatusChip(
                text = stringResource(R.string.warehouses_quantity_value, Formatters.number(entry.quantity)),
                color = MaterialTheme.colorScheme.secondary,
            )
        },
        onClick = onClick,
    )
}

@Composable
private fun ItemDialog(entry: WarehouseItem, onCopy: (String) -> Unit, onDismiss: () -> Unit) {
    val qrCode = entry.qrCode
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(entry.title) },
        text = {
            Column {
                KeyValueRow(label = stringResource(R.string.warehouses_sku), value = entry.sku)
                KeyValueRow(label = stringResource(R.string.warehouses_quantity), value = Formatters.number(entry.quantity))
                KeyValueRow(label = stringResource(R.string.warehouses_location_path), value = entry.location?.path)
                KeyValueRow(label = stringResource(R.string.warehouses_notes), value = entry.notes)
                KeyValueRow(label = stringResource(R.string.warehouses_item_qr), value = qrCode)
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) { Text(stringResource(R.string.action_close)) }
        },
        dismissButton = {
            if (qrCode != null) {
                TextButton(onClick = { onCopy(qrCode) }) { Text(stringResource(R.string.warehouses_copy_qr)) }
            }
        },
    )
}
