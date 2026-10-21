package com.appswebnetkz.crm369.feature.equipment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Equipment card: stage, employees, QR code and history stamps (`GET equipment/{id}`). */
@Composable
fun EquipmentDetailScreen(itemId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { EquipmentRepository(api) }
    val copy = rememberEquipmentCopy()
    val item = rememberLoadable(api, itemId) { repository.item(itemId) }

    CrmScaffold(
        title = item.data?.name ?: stringResource(R.string.equipment_title),
        subtitle = item.data?.statusLabel,
        actions = {
            if (item.data != null) {
                IconButton(onClick = { navigator.open(EquipmentRoutes.edit(itemId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = item, modifier = Modifier.padding(padding)) { data ->
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState()),
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Icon(
                        AppIcons.Equipment,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(56.dp),
                    )
                    Spacer(Modifier.height(12.dp))
                    Text(data.name, style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
                    Spacer(Modifier.height(8.dp))
                    StatusChip(text = data.statusLabel, color = equipmentStatusColor(data.status))
                }

                InfoCard(title = stringResource(R.string.equipment_details_title)) {
                    KeyValueRow(
                        label = stringResource(R.string.equipment_status),
                        value = data.statusLabel,
                        icon = AppIcons.Info,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.equipment_issued_to),
                        value = data.issuedTo?.name ?: stringResource(R.string.equipment_not_issued),
                        icon = AppIcons.Person,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.equipment_responsible),
                        value = data.responsible?.name ?: stringResource(R.string.equipment_not_assigned),
                        icon = AppIcons.Account,
                    )
                }

                InfoCard(title = stringResource(R.string.equipment_qr_code)) {
                    Text(
                        text = data.qrCode,
                        style = MaterialTheme.typography.titleMedium,
                        fontFamily = FontFamily.Monospace,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    )
                    Row(
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        OutlinedButton(onClick = { copy(data.qrCode) }, enabled = data.qrCode.isNotBlank()) {
                            Icon(AppIcons.Copy, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.size(8.dp))
                            Text(stringResource(R.string.equipment_copy_qr))
                        }
                    }
                }

                InfoCard {
                    KeyValueRow(
                        label = stringResource(R.string.equipment_created),
                        value = listOfNotNull(Formatters.dateTime(data.createdAt).ifBlank { null }, data.createdBy?.name)
                            .joinToString(" · "),
                        icon = AppIcons.Time,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.equipment_last_updated),
                        value = listOfNotNull(Formatters.dateTime(data.updatedAt).ifBlank { null }, data.updatedBy?.name)
                            .joinToString(" · "),
                        icon = AppIcons.History,
                    )
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }
}
