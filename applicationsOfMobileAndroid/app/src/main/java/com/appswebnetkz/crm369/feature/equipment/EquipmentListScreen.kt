package com.appswebnetkz.crm369.feature.equipment

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.scan.scanBarcode
import com.appswebnetkz.crm369.ui.components.ChipRow
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SearchField
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

/** Equipment list (`GET equipment`): search, stage filter and QR scan → card. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EquipmentListScreen() {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { EquipmentRepository(api) }
    val scope = rememberCoroutineScope()
    val list = rememberLoadable(api) { repository.list() }
    var query by rememberSaveable { mutableStateOf("") }
    var status by rememberSaveable { mutableStateOf<String?>(null) }

    val scan: () -> Unit = {
        scope.launch {
            try {
                val code = scanBarcode(context)
                if (!code.isNullOrBlank()) {
                    val match = list.data?.items?.firstOrNull { it.matchesQr(code) } ?: repository.findByQr(code)
                    if (match != null) {
                        navigator.open(EquipmentRoutes.detail(match.id))
                    } else {
                        messenger.show(context.getString(R.string.equipment_scan_not_found, code.trim()))
                    }
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                messenger.showError(exception)
            }
        }
    }

    CrmScaffold(
        title = stringResource(R.string.equipment_title),
        subtitle = list.data?.let { stringResource(R.string.equipment_total, it.items.size) },
        actions = {
            IconButton(onClick = scan) {
                Icon(AppIcons.QrScan, contentDescription = stringResource(R.string.equipment_scan))
            }
        },
        floatingActionButton = {
            FloatingActionButton(onClick = { navigator.open(EquipmentRoutes.NEW) }) {
                Icon(AppIcons.Add, contentDescription = stringResource(R.string.equipment_new))
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            SearchField(
                value = query,
                onValueChange = { query = it },
                placeholder = stringResource(R.string.equipment_search),
            )
            val loaded = list.data
            if (loaded != null && loaded.statuses.isNotEmpty()) {
                ChipRow {
                    FilterChip(
                        selected = status == null,
                        onClick = { status = null },
                        label = { Text(stringResource(R.string.equipment_all_stages, loaded.items.size)) },
                    )
                    loaded.statuses.forEach { option ->
                        val count = loaded.items.count { it.status == option.value }
                        FilterChip(
                            selected = status == option.value,
                            onClick = { status = if (status == option.value) null else option.value },
                            label = { Text(stringResource(R.string.equipment_status_count, option.label, count)) },
                        )
                    }
                }
            }
            LoadableContent(
                loadable = list,
                isEmpty = { it.items.isEmpty() },
                empty = {
                    EmptyState(
                        title = stringResource(R.string.equipment_empty_title),
                        message = stringResource(R.string.equipment_empty_description),
                        icon = AppIcons.Equipment,
                        actionLabel = stringResource(R.string.equipment_new),
                        onAction = { navigator.open(EquipmentRoutes.NEW) },
                    )
                },
            ) { data ->
                val selectedStatus = status
                val filtered = data.items.filter { item ->
                    (selectedStatus == null || item.status == selectedStatus) && item.matches(query)
                }
                LazyColumn(modifier = Modifier.fillMaxSize()) {
                    items(filtered, key = { it.id }) { equipment ->
                        EquipmentRow(
                            item = equipment,
                            onClick = { navigator.open(EquipmentRoutes.detail(equipment.id)) },
                        )
                        RowDivider()
                    }
                    if (filtered.isEmpty()) {
                        item(key = "no-match") {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text(
                                    text = stringResource(R.string.equipment_search_empty_title),
                                    style = MaterialTheme.typography.titleSmall,
                                )
                                Text(
                                    text = stringResource(R.string.equipment_search_empty_description),
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    }
                    item(key = "fab-space") { Spacer(Modifier.height(88.dp)) }
                }
            }
        }
    }
}

@Composable
private fun EquipmentRow(item: EquipmentItem, onClick: () -> Unit) {
    val people = listOfNotNull(
        item.issuedTo?.let { stringResource(R.string.equipment_issued_to_value, it.name) },
        item.responsible?.let { stringResource(R.string.equipment_responsible_value, it.name) },
    ).joinToString("\n")
    ListRow(
        title = item.name,
        overline = item.qrCode.ifBlank { null },
        subtitle = people.ifBlank { null },
        leading = { Icon(AppIcons.Equipment, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
        trailing = { StatusChip(text = item.statusLabel, color = equipmentStatusColor(item.status)) },
        maxSubtitleLines = 3,
        onClick = onClick,
    )
}
