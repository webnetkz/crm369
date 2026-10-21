package com.appswebnetkz.crm369.feature.warehouses

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
import androidx.compose.material3.Text
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
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.scan.scanBarcode
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.InlineProgress
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

/**
 * Warehouse QR search: camera scan (opened automatically once) or a typed / keyboard-wedge code →
 * `POST warehouses/scan` → exact location and contents of the address.
 */
@Composable
fun WarehouseScanScreen() {
    val api = rememberApi()
    val context = LocalContext.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { WarehousesRepository(api) }
    val scope = rememberCoroutineScope()
    val focusRequester = remember { FocusRequester() }
    var code by rememberSaveable { mutableStateOf("") }
    var cameraStarted by rememberSaveable { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var outcome by remember { mutableStateOf<WarehouseScanOutcome?>(null) }

    val submit: (String) -> Unit = submit@{ raw ->
        val value = raw.trim()
        if (value.isEmpty() || busy) return@submit
        busy = true
        scope.launch {
            try {
                outcome = repository.scan(value, Build.MODEL)
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
    LaunchedEffect(Unit) {
        if (!cameraStarted) {
            cameraStarted = true
            scanWithCamera()
        }
    }

    CrmScaffold(title = stringResource(R.string.warehouses_scan_title)) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(vertical = 8.dp),
        ) {
            Text(
                text = stringResource(R.string.warehouses_scan_description),
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
                Text(stringResource(R.string.warehouses_scan_camera))
            }
            ScanCodeField(
                value = code,
                onValueChange = { code = it },
                onSubmit = { submit(code) },
                label = stringResource(R.string.warehouses_scan_code),
                focusRequester = focusRequester,
            )
            if (busy) {
                InlineProgress()
            }
            val current = outcome
            if (current != null) {
                val resolved = current.resolved
                if (resolved != null) {
                    ResolvedQrCard(resolved = resolved)
                } else {
                    InfoCard(title = stringResource(R.string.warehouses_scan_result_title)) {
                        KeyValueRow(label = stringResource(R.string.warehouses_scan_code), value = current.code, icon = AppIcons.QrScan)
                        Text(
                            text = stringResource(R.string.warehouses_scan_not_found),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.error,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                }
            } else if (!busy) {
                Text(
                    text = stringResource(R.string.warehouses_scan_result_empty_description),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            }
            Spacer(Modifier.height(24.dp))
        }
    }
}
