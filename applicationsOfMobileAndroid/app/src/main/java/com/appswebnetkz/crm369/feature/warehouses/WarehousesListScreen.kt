package com.appswebnetkz.crm369.feature.warehouses

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Warehouses: summary (`meta`) and the list of warehouses (`GET warehouses`). */
@Composable
fun WarehousesListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { WarehousesRepository(api) }
    val overview = rememberLoadable(api) { repository.overview() }

    CrmScaffold(
        title = stringResource(R.string.warehouses_title),
        subtitle = overview.data?.summary?.let { stringResource(R.string.warehouses_total, it.total) },
        actions = {
            IconButton(onClick = { navigator.open(WarehousesRoutes.SCAN) }) {
                Icon(AppIcons.QrScan, contentDescription = stringResource(R.string.warehouses_scan))
            }
        },
        floatingActionButton = {
            ExtendedFloatingActionButton(
                text = { Text(stringResource(R.string.warehouses_new)) },
                icon = { Icon(AppIcons.Add, contentDescription = null) },
                onClick = { navigator.open(WarehousesRoutes.NEW) },
            )
        },
    ) { padding ->
        LoadableContent(
            loadable = overview,
            modifier = Modifier.padding(padding),
            isEmpty = { it.warehouses.isEmpty() },
            empty = {
                EmptyState(
                    title = stringResource(R.string.warehouses_empty_title),
                    message = stringResource(R.string.warehouses_empty_description),
                    icon = AppIcons.Warehouses,
                    actionLabel = stringResource(R.string.warehouses_new),
                    onAction = { navigator.open(WarehousesRoutes.NEW) },
                )
            },
        ) { data ->
            LazyColumn(modifier = Modifier.fillMaxSize()) {
                item(key = "summary") { WarehousesSummaryCard(summary = data.summary) }
                items(data.warehouses, key = { it.id }) { warehouse ->
                    WarehouseCard(
                        warehouse = warehouse,
                        onClick = { navigator.open(WarehousesRoutes.detail(warehouse.id)) },
                    )
                }
                item(key = "fab-space") { Spacer(Modifier.height(88.dp)) }
            }
        }
    }
}

@Composable
private fun WarehousesSummaryCard(summary: WarehousesSummary) {
    InfoCard(title = stringResource(R.string.warehouses_summary_title)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 6.dp),
        ) {
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_warehouses),
                value = summary.total.toString(),
                modifier = Modifier.weight(1f),
            )
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_area),
                value = stringResource(R.string.warehouses_area_value, Formatters.number(summary.totalAreaSqm)),
                modifier = Modifier.weight(1f),
            )
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_rows),
                value = summary.rowCount.toString(),
                modifier = Modifier.weight(1f),
            )
        }
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 6.dp),
        ) {
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_columns),
                value = summary.columnCount.toString(),
                modifier = Modifier.weight(1f),
            )
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_floors),
                value = summary.floorCount.toString(),
                modifier = Modifier.weight(1f),
            )
            WarehouseMetric(
                label = stringResource(R.string.warehouses_metric_places),
                value = summary.placeCount.toString(),
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun WarehouseCard(warehouse: Warehouse, onClick: () -> Unit) {
    InfoCard(onClick = onClick) {
        ListRow(
            title = warehouse.name,
            subtitle = stringResource(R.string.warehouses_area_line, Formatters.number(warehouse.areaSqm)),
            leading = { Icon(AppIcons.Warehouses, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
            trailing = { Icon(AppIcons.ChevronRight, contentDescription = null) },
        )
        Text(
            text = listOf(
                stringResource(R.string.warehouses_count_rows, warehouse.rowCount),
                stringResource(R.string.warehouses_count_columns, warehouse.columnCount),
                stringResource(R.string.warehouses_count_floors, warehouse.floorCount),
                stringResource(R.string.warehouses_count_places, warehouse.placeCount),
            ).joinToString(" · "),
            style = MaterialTheme.typography.bodyMedium,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 2.dp),
        )
        val qrCode = warehouse.qrCode
        if (qrCode != null) {
            Text(
                text = stringResource(R.string.warehouses_qr_value, qrCode),
                style = MaterialTheme.typography.bodySmall,
                fontFamily = FontFamily.Monospace,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
            )
        }
    }
}
