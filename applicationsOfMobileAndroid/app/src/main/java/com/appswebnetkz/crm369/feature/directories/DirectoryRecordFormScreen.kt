package com.appswebnetkz.crm369.feature.directories

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.json.asBool
import com.appswebnetkz.crm369.core.json.asDouble
import com.appswebnetkz.crm369.core.json.asStr
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject

private const val VALUES_PREFIX = "values."

/**
 * Create ([recordId] = null) or edit a directory record. The form is built from the directory
 * columns; the update replaces all values, so every column is sent.
 */
@Composable
fun DirectoryRecordFormScreen(directoryId: Long, recordId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { DirectoriesRepository(api) }
    val runner = rememberActionRunner()
    val directory = rememberLoadable(api, directoryId) { repository.directory(directoryId) }
    val state = remember(directoryId, recordId) { FormState() }
    var initialized by remember(directoryId, recordId) { mutableStateOf(false) }
    var confirmDelete by remember { mutableStateOf(false) }
    val data = directory.data
    val error = directory.error
    val deletedMessage = stringResource(R.string.directories_record_deleted)

    LaunchedEffect(data) {
        if (data != null && !initialized) {
            val record = if (recordId != null) data.records.firstOrNull { it.id == recordId } else null
            state.setAll(data.columns.associate { column -> VALUES_PREFIX + column.key to initialValue(column, record?.values) })
            initialized = true
        }
    }

    val title = stringResource(if (recordId == null) R.string.directories_add_record else R.string.directories_edit_record)
    if (data == null && error != null) {
        CrmScaffold(title = title) { padding ->
            ErrorState(error = error, onRetry = { directory.load() }, modifier = Modifier.padding(padding))
        }
        return
    }
    if (data != null && recordId != null && data.records.none { it.id == recordId }) {
        CrmScaffold(title = title) { padding ->
            EmptyState(
                title = stringResource(R.string.directories_record_missing),
                modifier = Modifier.padding(padding),
                icon = AppIcons.Warning,
            )
        }
        return
    }

    val columns = data?.columns.orEmpty()
    val specs = columns.map { column -> columnSpec(column) }

    FormScreen(
        title = title,
        specs = specs,
        state = state,
        isLoading = !initialized,
        submitLabel = stringResource(if (recordId == null) R.string.directories_add_record else R.string.action_save),
        successMessage = stringResource(
            if (recordId == null) R.string.directories_record_created else R.string.directories_record_saved,
        ),
        actions = {
            if (recordId != null) {
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        },
        header = {
            if (data != null) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 4.dp),
                ) {
                    Text(data.name, style = MaterialTheme.typography.titleMedium)
                    Text(
                        text = if (recordId != null) {
                            stringResource(R.string.directories_record_number, recordId)
                        } else {
                            stringResource(R.string.directories_record_form_hint)
                        },
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        },
        onSubmit = {
            val values = recordValues(columns, state)
            if (recordId == null) {
                repository.createRecord(directoryId, values)
            } else {
                repository.updateRecord(directoryId, recordId, values)
            }
        },
        onSuccess = { navigator.back() },
    )

    if (confirmDelete && recordId != null) {
        ConfirmDialog(
            title = stringResource(R.string.directories_record_delete_title),
            text = stringResource(R.string.directories_record_number, recordId),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                confirmDelete = false
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.deleteRecord(directoryId, recordId)
                }
            },
        )
    }
}

private fun columnSpec(column: DirectoryColumn): FieldSpec {
    val key = VALUES_PREFIX + column.key
    return when (column.type) {
        DirectoryColumn.TYPE_TEXTAREA -> FieldSpec.Multiline(key, column.label, required = column.isRequired)
        DirectoryColumn.TYPE_NUMBER -> FieldSpec.Decimal(key, column.label, required = column.isRequired)
        DirectoryColumn.TYPE_DATE -> FieldSpec.Date(key, column.label, required = column.isRequired)
        DirectoryColumn.TYPE_BOOLEAN -> FieldSpec.Switch(key, column.label, required = column.isRequired)
        else -> FieldSpec.Text(key, column.label, required = column.isRequired)
    }
}

/** Initial form value of [column]: Boolean for switches, text for everything else. */
private fun initialValue(column: DirectoryColumn, values: JsonObject?): Any? {
    val element = values?.get(column.key)
    if (element == null || element is JsonNull) {
        return if (column.type == DirectoryColumn.TYPE_BOOLEAN) false else null
    }
    return when (column.type) {
        DirectoryColumn.TYPE_BOOLEAN -> element.asBool() ?: false
        DirectoryColumn.TYPE_NUMBER -> element.asDouble()?.let { number ->
            if (number % 1.0 == 0.0) number.toLong().toString() else number.toString()
        } ?: element.asStr()
        else -> element.asStr()
    }
}

/** `values` object with every column (typed: booleans, numbers, `YYYY-MM-DD` dates, text). */
private fun recordValues(columns: List<DirectoryColumn>, state: FormState): JsonObject {
    val pairs = columns.map { column ->
        val key = VALUES_PREFIX + column.key
        val value: Any? = when (column.type) {
            DirectoryColumn.TYPE_BOOLEAN -> state.bool(key)
            DirectoryColumn.TYPE_NUMBER -> state.string(key)
                .filter { !it.isWhitespace() }
                .replace(',', '.')
                .takeIf { it.isNotEmpty() }
                ?.let { text -> text.toLongOrNull() ?: text.toDoubleOrNull() ?: text }
            else -> state.string(key).trim().ifBlank { null }
        }
        column.key to value
    }
    return jsonObjectOf(*pairs.toTypedArray())
}
