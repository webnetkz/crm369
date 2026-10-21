package com.appswebnetkz.crm369.feature.directories

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.SearchField
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Records of one directory (`GET directories/{id}`), record management and CSV exchange. */
@Composable
fun DirectoryDetailScreen(directoryId: Long) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val container = LocalAppContainer.current
    val bootstrap = rememberBootstrap()
    val canManage = bootstrap?.can("canManageDirectories") == true
    val repository = remember(api) { DirectoriesRepository(api) }
    val runner = rememberActionRunner()
    val directory = rememberLoadable(api, directoryId) { repository.directory(directoryId) }
    var search by rememberSaveable { mutableStateOf("") }
    var delimiter by rememberSaveable { mutableStateOf(";") }
    var menuOpen by remember { mutableStateOf(false) }
    var showCsv by remember { mutableStateOf(false) }
    var confirmDelete by remember { mutableStateOf(false) }
    var recordToDelete by remember { mutableStateOf<DirectoryRecord?>(null) }
    var importMessage by remember { mutableStateOf<String?>(null) }
    var importErrors by remember { mutableStateOf<List<String>>(emptyList()) }
    val yes = stringResource(R.string.directories_boolean_true)
    val no = stringResource(R.string.directories_boolean_false)
    val deletedMessage = stringResource(R.string.directories_deleted)
    val recordDeletedMessage = stringResource(R.string.directories_record_deleted)

    val importPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            runner.execute(
                onError = { error ->
                    if (error is ApiException && error.isValidation) {
                        importErrors = error.errors.values.flatten().ifEmpty { listOf(error.firstMessage()) }
                        true
                    } else {
                        false
                    }
                },
                onSuccess = { count: Int ->
                    importMessage = context.getString(R.string.directories_import_success, count)
                    directory.refresh()
                },
            ) { repository.importCsv(context, directoryId, uri, delimiter.trim().ifBlank { null }) }
        }
    }

    val loaded = directory.data
    CrmScaffold(
        title = loaded?.name ?: stringResource(R.string.module_directories),
        subtitle = loaded?.let { stringResource(R.string.directories_records_count, it.records.size) },
        actions = {
            if (loaded != null && (canManage || loaded.csvExchangeEnabled)) {
                Box {
                    IconButton(onClick = { menuOpen = true }) {
                        Icon(AppIcons.More, contentDescription = stringResource(R.string.directories_actions))
                    }
                    DropdownMenu(expanded = menuOpen, onDismissRequest = { menuOpen = false }) {
                        if (loaded.csvExchangeEnabled) {
                            MenuEntry(text = stringResource(R.string.directories_csv_title), icon = AppIcons.Sync) {
                                menuOpen = false
                                showCsv = true
                            }
                        }
                        if (canManage) {
                            MenuEntry(text = stringResource(R.string.directories_edit), icon = AppIcons.Edit) {
                                menuOpen = false
                                navigator.open(DirectoriesRoutes.edit(directoryId))
                            }
                            MenuEntry(text = stringResource(R.string.directories_delete), icon = AppIcons.Delete) {
                                menuOpen = false
                                confirmDelete = true
                            }
                        }
                    }
                }
            }
        },
        floatingActionButton = {
            if (canManage && loaded != null) {
                FloatingActionButton(onClick = { navigator.open(DirectoriesRoutes.newRecord(directoryId)) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.directories_add_record))
                }
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            SearchField(
                value = search,
                onValueChange = { search = it },
                placeholder = stringResource(R.string.directories_search_records),
            )
            LoadableContent(loadable = directory) { data ->
                val query = search.trim()
                val filtered = if (query.isEmpty()) {
                    data.records
                } else {
                    data.records.filter { record ->
                        data.columns.any { column ->
                            formatDirectoryValue(column, record.values, yes, no)?.contains(query, ignoreCase = true) == true
                        }
                    }
                }
                LazyColumn(modifier = Modifier.fillMaxSize()) {
                    item(key = "header") {
                        DirectoryHeader(directory = data)
                    }
                    if (filtered.isEmpty()) {
                        item(key = "empty") {
                            Text(
                                text = stringResource(
                                    if (data.records.isEmpty()) R.string.directories_records_empty else R.string.directories_records_not_found,
                                ),
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 24.dp),
                            )
                        }
                    }
                    items(filtered, key = { "record-${it.id}" }) { record ->
                        DirectoryRecordCard(
                            record = record,
                            columns = data.columns,
                            canManage = canManage,
                            yes = yes,
                            no = no,
                            onEdit = { navigator.open(DirectoriesRoutes.editRecord(directoryId, record.id)) },
                            onDelete = { recordToDelete = record },
                        )
                    }
                    item(key = "bottom") { Spacer(Modifier.height(88.dp)) }
                }
            }
        }
    }

    val csvDirectory = directory.data
    if (showCsv && csvDirectory != null) {
        AlertDialog(
            onDismissRequest = { showCsv = false },
            title = { Text(stringResource(R.string.directories_csv_title)) },
            text = {
                Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                    Text(
                        text = stringResource(R.string.directories_csv_description),
                        style = MaterialTheme.typography.bodyMedium,
                    )
                    Spacer(Modifier.height(12.dp))
                    OutlinedTextField(
                        value = delimiter,
                        onValueChange = { delimiter = it.take(2) },
                        label = { Text(stringResource(R.string.directories_csv_delimiter)) },
                        supportingText = { Text(stringResource(R.string.directories_csv_delimiter_hint)) },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                    )
                    Spacer(Modifier.height(8.dp))
                    CsvButton(text = stringResource(R.string.directories_csv_export), icon = AppIcons.Share, enabled = !runner.isBusy) {
                        showCsv = false
                        runner.execute {
                            container.fileTransfer.downloadAndShare(
                                api,
                                repository.exportUrl(directoryId, delimiter.trim().ifBlank { null }),
                                csvDirectory.slug.ifBlank { "directory" } + ".csv",
                            )
                        }
                    }
                    CsvButton(text = stringResource(R.string.directories_csv_template), icon = AppIcons.Download, enabled = !runner.isBusy) {
                        showCsv = false
                        runner.execute {
                            container.fileTransfer.downloadAndShare(
                                api,
                                repository.templateUrl(directoryId, delimiter.trim().ifBlank { null }),
                                csvDirectory.slug.ifBlank { "directory" } + "-template.csv",
                            )
                        }
                    }
                    if (canManage) {
                        CsvButton(text = stringResource(R.string.directories_csv_import), icon = AppIcons.Upload, enabled = !runner.isBusy) {
                            showCsv = false
                            importPicker.launch("*/*")
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { showCsv = false }) { Text(stringResource(R.string.action_close)) }
            },
        )
    }

    val message = importMessage
    if (message != null) {
        AlertDialog(
            onDismissRequest = { importMessage = null },
            title = { Text(stringResource(R.string.directories_csv_import)) },
            text = { Text(message) },
            confirmButton = {
                TextButton(onClick = { importMessage = null }) { Text(stringResource(R.string.action_ok)) }
            },
        )
    }

    if (importErrors.isNotEmpty()) {
        AlertDialog(
            onDismissRequest = { importErrors = emptyList() },
            title = { Text(stringResource(R.string.directories_import_failed)) },
            text = {
                Column(
                    modifier = Modifier
                        .heightIn(max = 360.dp)
                        .verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    importErrors.forEach { line ->
                        Text(line, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.error)
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { importErrors = emptyList() }) { Text(stringResource(R.string.action_ok)) }
            },
        )
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.directories_delete_title),
            text = stringResource(R.string.directories_delete_text),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                confirmDelete = false
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.delete(directoryId)
                }
            },
        )
    }

    val pendingRecord = recordToDelete
    if (pendingRecord != null) {
        ConfirmDialog(
            title = stringResource(R.string.directories_record_delete_title),
            text = stringResource(R.string.directories_record_number, pendingRecord.id),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { recordToDelete = null },
            onConfirm = {
                recordToDelete = null
                runner.execute(
                    successMessage = recordDeletedMessage,
                    onSuccess = { _: Unit ->
                        directory.update { current ->
                            current.copy(
                                records = current.records.filterNot { it.id == pendingRecord.id },
                                recordsCount = (current.recordsCount - 1).coerceAtLeast(0),
                            )
                        }
                    },
                ) { repository.deleteRecord(directoryId, pendingRecord.id) }
            },
        )
    }
}

@Composable
private fun MenuEntry(text: String, icon: ImageVector, onClick: () -> Unit) {
    DropdownMenuItem(
        text = { Text(text) },
        onClick = onClick,
        leadingIcon = { Icon(icon, contentDescription = null) },
    )
}

@Composable
private fun CsvButton(text: String, icon: ImageVector, enabled: Boolean, onClick: () -> Unit) {
    OutlinedButton(
        onClick = onClick,
        enabled = enabled,
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
    ) {
        Icon(icon, contentDescription = null, modifier = Modifier.size(18.dp))
        Spacer(Modifier.size(8.dp))
        Text(text)
    }
}

@Composable
private fun DirectoryHeader(directory: ReferenceDirectory) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
    ) {
        val description = directory.description
        if (description != null) {
            Text(description, style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(4.dp))
        }
        val parts = listOfNotNull(
            stringResource(R.string.directories_columns_count, directory.columns.size),
            stringResource(R.string.directories_records_count, directory.records.size),
            if (directory.csvExchangeEnabled) stringResource(R.string.directories_csv_badge) else null,
        )
        Text(
            text = parts.joinToString(" · "),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun DirectoryRecordCard(
    record: DirectoryRecord,
    columns: List<DirectoryColumn>,
    canManage: Boolean,
    yes: String,
    no: String,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
) {
    InfoCard(onClick = if (canManage) onEdit else null) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 16.dp, end = 4.dp, top = if (canManage) 4.dp else 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = stringResource(R.string.directories_record_number, record.id),
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.weight(1f),
            )
            if (canManage) {
                IconButton(onClick = onDelete) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        }
        columns.forEach { column ->
            KeyValueRow(label = column.label, value = formatDirectoryValue(column, record.values, yes, no))
        }
        val updated = Formatters.dateTime(record.updatedAt)
        if (updated.isNotBlank()) {
            Text(
                text = listOfNotNull(stringResource(R.string.directories_updated_at, updated), record.updaterName).joinToString(" · "),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
            )
        }
    }
}
