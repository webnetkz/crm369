package com.appswebnetkz.crm369.feature.warehouses

import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Places of one floor with the items stored in them (`GET warehouses/{id}/floors/{floorId}`). */
@Composable
fun WarehouseFloorScreen(warehouseId: Long, floorId: Long) {
    val api = rememberApi()
    val repository = remember(api) { WarehousesRepository(api) }
    val copy = rememberWarehouseCopy()
    val floor = rememberLoadable(api, warehouseId, floorId) { repository.floor(warehouseId, floorId) }
    val loaded = floor.data

    CrmScaffold(
        title = loaded?.location?.floor?.name ?: stringResource(R.string.warehouses_entity_floor),
        subtitle = loaded?.location?.label ?: loaded?.warehouseName,
    ) { padding ->
        LoadableContent(
            loadable = floor,
            modifier = Modifier.padding(padding),
            isEmpty = { it.places.isEmpty() },
            empty = {
                EmptyState(
                    title = stringResource(R.string.warehouses_map_floor_empty),
                    icon = AppIcons.Place,
                )
            },
        ) { data ->
            LazyColumn(modifier = Modifier.fillMaxSize()) {
                val location = data.location
                if (location != null) {
                    item(key = "floor-header") {
                        InfoCard {
                            KeyValueRow(
                                label = stringResource(R.string.warehouses_floor_qr),
                                value = location.floor.qrCode,
                                icon = AppIcons.Copy,
                                onClick = location.floor.qrCode?.let { code -> { copy(code) } },
                            )
                            KeyValueRow(
                                label = stringResource(R.string.warehouses_metric_places),
                                value = data.places.size.toString(),
                                icon = AppIcons.Place,
                            )
                        }
                    }
                }
                items(data.places, key = { it.id }) { place ->
                    PlaceCard(place = place, onCopy = copy)
                }
                item(key = "bottom-space") { Spacer(Modifier.height(24.dp)) }
            }
        }
    }
}

@Composable
private fun PlaceCard(place: FloorPlace, onCopy: (String) -> Unit) {
    InfoCard(title = place.name) {
        KeyValueRow(
            label = stringResource(R.string.warehouses_place_qr),
            value = place.qrCode,
            icon = AppIcons.Copy,
            onClick = place.qrCode?.let { code -> { onCopy(code) } },
        )
        KeyValueRow(
            label = stringResource(R.string.warehouses_metric_items),
            value = place.itemCount.toString(),
        )
        if (place.items.isEmpty()) {
            Text(
                text = stringResource(R.string.warehouses_map_place_empty),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            )
        } else {
            place.items.forEach { stock ->
                ListRow(
                    title = stock.name,
                    overline = stock.sku?.let { stringResource(R.string.warehouses_sku_value, it) },
                    subtitle = stock.qrCode?.let { stringResource(R.string.warehouses_qr_value, it) },
                    trailing = {
                        StatusChip(
                            text = stringResource(R.string.warehouses_quantity_value, Formatters.number(stock.quantity)),
                            color = MaterialTheme.colorScheme.secondary,
                        )
                    },
                    onClick = stock.qrCode?.let { code -> { onCopy(code) } },
                )
            }
        }
    }
}
