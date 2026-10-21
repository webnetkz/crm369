package com.appswebnetkz.crm369.feature.tsd

import android.os.Build
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.scan.scanBarcode
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.feature.equipment.EquipmentItem
import com.appswebnetkz.crm369.feature.equipment.EquipmentRoutes
import com.appswebnetkz.crm369.feature.warehouses.ResolvedQrCard
import com.appswebnetkz.crm369.feature.warehouses.ScanCodeField
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.InlineProgress
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

/**
 * Quick QR lookup: scan or type a code → warehouse entity (`GET warehouses/qr/{code}`) and/or the
 * equipment with this `qr_code`; without both abilities the code is sent to `POST tsd/scans`.
 */
@Composable
fun QrLookupScreen() {
    val api = rememberApi()
    val context = LocalContext.current
    val messenger = LocalMessenger.current
    val bootstrap = rememberBootstrap()
    val lookup = remember(api) { QrLookup(api) }
    val scope = rememberCoroutineScope()
    val focusRequester = remember { FocusRequester() }
    var code by rememberSaveable { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var result by remember { mutableStateOf<QrLookupResult?>(null) }

    val canWarehouses = bootstrap == null || bootstrap.can("canAccessWarehouses")
    val canEquipment = bootstrap == null || bootstrap.can("canAccessEquipment")
    val canTsd = bootstrap == null || bootstrap.can("canAccessTsd")

    val submit: (String) -> Unit = submit@{ raw ->
        val value = raw.trim()
        if (value.isEmpty() || busy) return@submit
        busy = true
        scope.launch {
            try {
                result = lookup.lookup(value, canWarehouses, canEquipment, canTsd, Build.MODEL)
                code = ""
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                messenger.showError(exception)
            } finally {
                busy = false
            }
        }
    }
    val scanWithCamera: () -> Unit = {
        scope.launch {
            try {
                val scanned = scanBarcode(context)
                if (!scanned.isNullOrBlank()) submit(scanned)
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                messenger.showError(exception)
            }
        }
    }

    CrmScaffold(title = stringResource(R.string.tsd_qr_title)) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(vertical = 8.dp),
        ) {
            Text(
                text = stringResource(R.string.tsd_qr_description),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            )
            Button(
                onClick = scanWithCamera,
                enabled = !busy,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 4.dp),
            ) {
                Icon(AppIcons.CameraAlt, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.size(8.dp))
                Text(stringResource(R.string.tsd_scan_camera))
            }
            ScanCodeField(
                value = code,
                onValueChange = { code = it },
                onSubmit = { submit(code) },
                label = stringResource(R.string.tsd_qr_code),
                focusRequester = focusRequester,
            )
            if (busy) {
                InlineProgress()
            }
            val current = result
            if (current != null) {
                QrLookupResultView(result = current)
            }
            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun QrLookupResultView(result: QrLookupResult) {
    val navigator = LocalNavigator.current
    val resolved = result.resolved
    val equipment = result.equipment
    val scan = result.scan

    if (!result.found) {
        InfoCard(title = stringResource(R.string.tsd_qr_result_title)) {
            KeyValueRow(label = stringResource(R.string.tsd_qr_code), value = result.code, icon = AppIcons.QrScan)
            Text(
                text = stringResource(R.string.tsd_qr_not_found),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.error,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            )
            if (scan != null) {
                Text(
                    text = stringResource(R.string.tsd_qr_saved_to_log),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
                )
            }
        }
    }
    if (equipment != null) {
        EquipmentMatchCard(
            item = equipment,
            onOpen = { navigator.open(EquipmentRoutes.detail(equipment.id)) },
        )
    }
    if (resolved != null) {
        ResolvedQrCard(resolved = resolved)
    }
    if (result.found && scan != null) {
        Text(
            text = stringResource(R.string.tsd_qr_saved_at, Formatters.dateTime(scan.scannedAt)),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
        )
    }
}

@Composable
private fun EquipmentMatchCard(item: EquipmentItem, onOpen: () -> Unit) {
    InfoCard(title = stringResource(R.string.tsd_qr_equipment_found)) {
        ListRow(
            title = item.name,
            overline = item.qrCode.ifBlank { null },
            subtitle = listOfNotNull(
                item.issuedTo?.let { stringResource(R.string.tsd_qr_equipment_issued_to, it.name) },
                item.responsible?.let { stringResource(R.string.tsd_qr_equipment_responsible, it.name) },
            ).joinToString("\n").ifBlank { null },
            leading = { Icon(AppIcons.Equipment, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
            trailing = { StatusChip(text = item.statusLabel) },
            onClick = onOpen,
        )
        OutlinedButton(
            onClick = onOpen,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
        ) {
            Text(stringResource(R.string.tsd_qr_open_equipment))
        }
    }
}
