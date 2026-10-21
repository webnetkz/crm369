package com.appswebnetkz.crm369.feature.tsd

import android.os.Build
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
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
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.scan.scanBarcode
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.core.util.describeError
import com.appswebnetkz.crm369.feature.warehouses.ResolvedQrCard
import com.appswebnetkz.crm369.feature.warehouses.ScanCodeField
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.InlineProgress
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

/**
 * Handheld-terminal (TSD) scanning: camera scan or a focused field for hardware "keyboard wedge"
 * scanners (Enter submits and the field keeps the focus), optional device / location / operation
 * context, the result of the last scan and the common log (`GET tsd/scans?per_page=50`).
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TsdScreen() {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { TsdRepository(api) }
    val scope = rememberCoroutineScope()
    val focusRequester = remember { FocusRequester() }
    var code by rememberSaveable { mutableStateOf("") }
    var deviceName by rememberSaveable { mutableStateOf(Build.MODEL.orEmpty()) }
    var location by rememberSaveable { mutableStateOf("") }
    var operationContext by rememberSaveable { mutableStateOf("") }
    var settingsOpen by rememberSaveable { mutableStateOf(false) }
    var pending by remember { mutableStateOf(0) }
    var lastResult by remember { mutableStateOf<TsdScanResult?>(null) }
    val history = rememberLoadable(api) { repository.history() }

    val submit: (String) -> Unit = submit@{ raw ->
        val value = raw.trim()
        if (value.isEmpty()) return@submit
        code = ""
        pending += 1
        scope.launch {
            try {
                val result = repository.submit(value, deviceName, location, operationContext)
                lastResult = result
                val current = history.data.orEmpty().filterNot { it.id == result.scan.id }
                history.set((listOf(result.scan) + current).take(TsdRepository.HISTORY_SIZE))
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                messenger.showError(exception)
            } finally {
                pending -= 1
            }
        }
        runCatching { focusRequester.requestFocus() }
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
    LaunchedEffect(Unit) {
        runCatching { focusRequester.requestFocus() }
    }

    CrmScaffold(
        title = stringResource(R.string.tsd_title),
        actions = {
            IconButton(onClick = { navigator.open(TsdRoutes.QR) }) {
                Icon(AppIcons.Search, contentDescription = stringResource(R.string.tsd_open_qr_lookup))
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            Button(
                onClick = scanWithCamera,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(start = 16.dp, end = 16.dp, top = 8.dp),
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
            if (pending > 0) {
                InlineProgress()
            }
            PullToRefreshBox(
                isRefreshing = history.isRefreshing,
                onRefresh = { history.refresh() },
                modifier = Modifier.fillMaxSize(),
            ) {
                LazyColumn(modifier = Modifier.fillMaxSize()) {
                    item(key = "settings") {
                        ScanSettingsCard(
                            expanded = settingsOpen,
                            onToggle = { settingsOpen = !settingsOpen },
                            deviceName = deviceName,
                            onDeviceNameChange = { deviceName = it },
                            location = location,
                            onLocationChange = { location = it },
                            operationContext = operationContext,
                            onOperationContextChange = { operationContext = it },
                        )
                    }
                    val result = lastResult
                    if (result != null) {
                        item(key = "result") { ScanResultCard(result = result) }
                    }
                    item(key = "history-title") {
                        SectionHeader(title = stringResource(R.string.tsd_recent_title))
                    }
                    val scans = history.data
                    val error = history.error
                    when {
                        scans == null && error != null -> item(key = "history-error") {
                            Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) {
                                Text(
                                    text = describeError(context, error),
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.error,
                                )
                                TextButton(onClick = { history.load() }) {
                                    Text(stringResource(R.string.action_retry))
                                }
                            }
                        }
                        scans == null -> item(key = "history-loading") { InlineProgress() }
                        scans.isEmpty() -> item(key = "history-empty") {
                            Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) {
                                Text(
                                    text = stringResource(R.string.tsd_empty_title),
                                    style = MaterialTheme.typography.titleSmall,
                                )
                                Text(
                                    text = stringResource(R.string.tsd_empty_description),
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                        else -> items(scans, key = { it.id }) { scan ->
                            ScanRow(scan = scan)
                            RowDivider()
                        }
                    }
                    item(key = "bottom-space") { Spacer(Modifier.height(24.dp)) }
                }
            }
        }
    }
}

@Composable
private fun ScanSettingsCard(
    expanded: Boolean,
    onToggle: () -> Unit,
    deviceName: String,
    onDeviceNameChange: (String) -> Unit,
    location: String,
    onLocationChange: (String) -> Unit,
    operationContext: String,
    onOperationContextChange: (String) -> Unit,
) {
    InfoCard {
        ListRow(
            title = stringResource(R.string.tsd_settings_title),
            subtitle = listOf(deviceName, location, operationContext).filter { it.isNotBlank() }.joinToString(" · ").ifBlank { null },
            leading = { Icon(AppIcons.Tune, contentDescription = null) },
            trailing = { Icon(if (expanded) AppIcons.ExpandLess else AppIcons.ExpandMore, contentDescription = null) },
            onClick = onToggle,
        )
        if (expanded) {
            OutlinedTextField(
                value = deviceName,
                onValueChange = onDeviceNameChange,
                label = { Text(stringResource(R.string.tsd_device_name)) },
                placeholder = { Text(stringResource(R.string.tsd_device_name_placeholder)) },
                singleLine = true,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 4.dp),
            )
            OutlinedTextField(
                value = location,
                onValueChange = onLocationChange,
                label = { Text(stringResource(R.string.tsd_location)) },
                placeholder = { Text(stringResource(R.string.tsd_location_placeholder)) },
                singleLine = true,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 4.dp),
            )
            OutlinedTextField(
                value = operationContext,
                onValueChange = onOperationContextChange,
                label = { Text(stringResource(R.string.tsd_context)) },
                placeholder = { Text(stringResource(R.string.tsd_context_placeholder)) },
                singleLine = true,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 4.dp),
            )
        }
    }
}

@Composable
private fun ScanResultCard(result: TsdScanResult) {
    val scan = result.scan
    Column {
        InfoCard(title = stringResource(R.string.tsd_result_title)) {
            KeyValueRow(
                label = stringResource(R.string.tsd_scanned_at),
                value = Formatters.dateTime(scan.scannedAt),
                icon = AppIcons.Time,
            )
            KeyValueRow(label = stringResource(R.string.tsd_qr_code), value = scan.qrCode, icon = AppIcons.QrScan)
            KeyValueRow(label = stringResource(R.string.tsd_normalized_qr_code), value = scan.normalizedQrCode)
            if (result.resolved == null) {
                Text(
                    text = stringResource(R.string.tsd_not_resolved),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            }
        }
        val resolved = result.resolved
        if (resolved != null) {
            ResolvedQrCard(resolved = resolved)
        }
    }
}

@Composable
private fun ScanRow(scan: TsdScan) {
    val details = listOfNotNull(
        scan.actorName?.let { stringResource(R.string.tsd_actor_value, it) },
        scan.deviceName?.let { stringResource(R.string.tsd_device_value, it) },
        scan.location?.let { stringResource(R.string.tsd_location_value, it) },
        scan.context?.let { stringResource(R.string.tsd_context_value, it) },
        scan.webhookName?.let { stringResource(R.string.tsd_webhook_value, it) },
    ).joinToString("\n")
    val normalized = scan.normalizedQrCode
    ListRow(
        title = scan.qrCode,
        overline = listOfNotNull(Formatters.dateTime(scan.scannedAt).ifBlank { null }, scan.sourceLabel).joinToString(" · ").ifBlank { null },
        subtitle = details.ifBlank { null },
        maxSubtitleLines = 5,
        trailing = if (normalized != null && normalized != scan.qrCode) {
            {
                Text(
                    text = normalized,
                    style = MaterialTheme.typography.labelSmall,
                    fontFamily = FontFamily.Monospace,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                )
            }
        } else {
            null
        },
    )
}
