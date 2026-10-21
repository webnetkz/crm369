package com.appswebnetkz.crm369.feature.warehouses

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.theme.AppIcons

/**
 * Result of a warehouse QR lookup (`Resolved`): type, title, exact address, the details of the
 * entity type, what is stored there (≤ 100 positions) and shortcuts to the warehouse / floor.
 */
@Composable
fun ResolvedQrCard(resolved: ResolvedQr, modifier: Modifier = Modifier) {
    val navigator = LocalNavigator.current
    val copy = rememberWarehouseCopy()
    val typeLabel = resolved.entityTypeLabel ?: warehouseEntityLabel(resolved.entityType)
    Column(modifier = modifier.fillMaxWidth()) {
        InfoCard(title = stringResource(R.string.warehouses_scan_result_title)) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 6.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                Text(
                    text = resolved.title,
                    style = MaterialTheme.typography.titleMedium,
                    modifier = Modifier.weight(1f),
                )
                StatusChip(text = typeLabel)
            }
            KeyValueRow(
                label = stringResource(R.string.warehouses_scan_matched_qr),
                value = resolved.qrCode,
                icon = AppIcons.Copy,
                onClick = resolved.qrCode?.let { code -> { copy(code) } },
            )
            KeyValueRow(
                label = stringResource(R.string.warehouses_scan_location),
                value = resolved.location?.path,
                icon = AppIcons.Place,
            )
            ResolvedDetails(resolved = resolved)
            val warehouseId = resolved.warehouseId
            val floorId = resolved.floorId
            if (warehouseId != null) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    if (floorId != null) {
                        Button(onClick = { navigator.open(WarehousesRoutes.floor(warehouseId, floorId)) }) {
                            Text(stringResource(R.string.warehouses_open_floor))
                        }
                    }
                    OutlinedButton(onClick = { navigator.open(WarehousesRoutes.detail(warehouseId)) }) {
                        Text(stringResource(R.string.warehouses_open_warehouse))
                    }
                }
            }
        }
        if (!resolved.isItem) {
            InfoCard(title = stringResource(R.string.warehouses_qr_contents_title)) {
                if (resolved.contents.isEmpty()) {
                    Text(
                        text = stringResource(R.string.warehouses_qr_contents_empty),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    )
                } else {
                    resolved.contents.forEach { content ->
                        ListRow(
                            title = content.name,
                            overline = content.sku?.let { stringResource(R.string.warehouses_sku_value, it) },
                            subtitle = listOfNotNull(
                                content.location?.path,
                                content.qrCode?.let { stringResource(R.string.warehouses_qr_value, it) },
                            ).joinToString("\n").ifBlank { null },
                            maxSubtitleLines = 3,
                            trailing = {
                                StatusChip(
                                    text = stringResource(R.string.warehouses_quantity_value, Formatters.number(content.quantity)),
                                    color = MaterialTheme.colorScheme.secondary,
                                )
                            },
                            onClick = content.qrCode?.let { code -> { copy(code) } },
                        )
                    }
                }
                if (resolved.contentsTruncated) {
                    Text(
                        text = stringResource(R.string.warehouses_qr_contents_truncated),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun ResolvedDetails(resolved: ResolvedQr) {
    val area = resolved.areaSqm
    if (area != null) {
        KeyValueRow(
            label = stringResource(R.string.warehouses_area_label),
            value = stringResource(R.string.warehouses_area_value, Formatters.number(area)),
        )
    }
    CountRow(label = R.string.warehouses_metric_rows, value = resolved.rowCount)
    CountRow(label = R.string.warehouses_metric_columns, value = resolved.columnCount)
    CountRow(label = R.string.warehouses_metric_floors, value = resolved.floorCount)
    CountRow(label = R.string.warehouses_metric_places, value = resolved.placeCount)
    CountRow(label = R.string.warehouses_metric_items, value = resolved.itemCount)
    val quantity = resolved.quantity
    if (quantity != null) {
        KeyValueRow(label = stringResource(R.string.warehouses_quantity), value = Formatters.number(quantity))
    }
    KeyValueRow(label = stringResource(R.string.warehouses_sku), value = resolved.sku)
    KeyValueRow(label = stringResource(R.string.warehouses_notes), value = resolved.notes)
    val place = resolved.place
    if (resolved.isItem && place != null) {
        KeyValueRow(label = stringResource(R.string.warehouses_entity_place), value = place.name)
    }
}

@Composable
private fun CountRow(@StringRes label: Int, value: Int?) {
    if (value != null) {
        KeyValueRow(label = stringResource(label), value = value.toString())
    }
}

/**
 * Single-line code input that also works with hardware "keyboard wedge" scanners of handheld
 * terminals: the Enter key (or the IME Done action) submits the value.
 */
@Composable
fun ScanCodeField(
    value: String,
    onValueChange: (String) -> Unit,
    onSubmit: () -> Unit,
    label: String,
    focusRequester: FocusRequester,
    modifier: Modifier = Modifier,
) {
    OutlinedTextField(
        value = value,
        onValueChange = { text ->
            // Some wedge scanners commit the terminator as a "\n" character instead of an Enter key event.
            val clean = text.replace("\n", "").replace("\r", "")
            onValueChange(clean)
            if (clean.length != text.length && clean.isNotBlank()) onSubmit()
        },
        label = { Text(label) },
        singleLine = true,
        textStyle = MaterialTheme.typography.bodyLarge.copy(fontFamily = FontFamily.Monospace),
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
        keyboardActions = KeyboardActions(onDone = { onSubmit() }),
        trailingIcon = {
            IconButton(onClick = onSubmit, enabled = value.isNotBlank()) {
                Icon(AppIcons.Send, contentDescription = stringResource(R.string.warehouses_scan_submit))
            }
        },
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp)
            .focusRequester(focusRequester)
            .onPreviewKeyEvent { event ->
                if (event.key == Key.Enter || event.key == Key.NumPadEnter) {
                    if (event.type == KeyEventType.KeyUp) onSubmit()
                    true
                } else {
                    false
                }
            },
    )
}

/** Value over label, used in the summary grids. */
@Composable
internal fun WarehouseMetric(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier = modifier) {
        Text(
            text = value,
            style = MaterialTheme.typography.titleMedium,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

/** Copies a code to the clipboard and confirms with a snackbar. */
@Composable
internal fun rememberWarehouseCopy(): (String) -> Unit {
    val clipboard = LocalClipboardManager.current
    val messenger = LocalMessenger.current
    val copied = stringResource(R.string.warehouses_copied)
    return remember(clipboard, messenger, copied) {
        { text: String ->
            clipboard.setText(AnnotatedString(text))
            messenger.show(copied)
        }
    }
}

/** Local fallback for `entity_type_label`. */
@Composable
internal fun warehouseEntityLabel(type: String): String = when (type) {
    ResolvedQr.TYPE_WAREHOUSE -> stringResource(R.string.warehouses_entity_warehouse)
    ResolvedQr.TYPE_ROW -> stringResource(R.string.warehouses_entity_row)
    ResolvedQr.TYPE_COLUMN -> stringResource(R.string.warehouses_entity_column)
    ResolvedQr.TYPE_FLOOR -> stringResource(R.string.warehouses_entity_floor)
    ResolvedQr.TYPE_PLACE -> stringResource(R.string.warehouses_entity_place)
    ResolvedQr.TYPE_ITEM -> stringResource(R.string.warehouses_entity_item)
    else -> stringResource(R.string.warehouses_entity_unknown)
}
