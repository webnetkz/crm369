package com.appswebnetkz.crm369.feature.directories

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.LoadingState
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormFields
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException
import kotlinx.serialization.json.JsonObject

private val COLUMN_FIELDS = listOf("label", "key", "type", "is_required")
private const val MAX_COLUMNS = 50

@Composable
internal fun directoryColumnTypeLabel(type: String): String = when (type) {
    DirectoryColumn.TYPE_TEXTAREA -> stringResource(R.string.directories_type_textarea)
    DirectoryColumn.TYPE_NUMBER -> stringResource(R.string.directories_type_number)
    DirectoryColumn.TYPE_DATE -> stringResource(R.string.directories_type_date)
    DirectoryColumn.TYPE_BOOLEAN -> stringResource(R.string.directories_type_boolean)
    else -> stringResource(R.string.directories_type_text)
}

/** Create ([directoryId] = null) or edit a directory with its columns (the update replaces everything). */
@Composable
fun DirectoryFormScreen(directoryId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { DirectoriesRepository(api) }
    val runner = rememberActionRunner()
    val state = remember(directoryId) {
        FormState(
            mapOf(
                "csv_exchange_enabled" to true,
                "columns.0.type" to DirectoryColumn.TYPE_TEXT,
                "columns.0.is_required" to false,
            ),
        )
    }
    var columnCount by remember(directoryId) { mutableIntStateOf(1) }
    var loaded by remember(directoryId) { mutableStateOf(directoryId == null) }
    var loadError by remember(directoryId) { mutableStateOf<Throwable?>(null) }
    var attempt by remember(directoryId) { mutableIntStateOf(0) }

    LaunchedEffect(directoryId, attempt) {
        if (directoryId == null) return@LaunchedEffect
        try {
            val directory = repository.directory(directoryId)
            val values = LinkedHashMap<String, Any?>()
            values["name"] = directory.name
            values["slug"] = directory.slug
            values["description"] = directory.description
            values["csv_exchange_enabled"] = directory.csvExchangeEnabled
            directory.columns.forEachIndexed { index, column ->
                values["columns.$index.label"] = column.label
                values["columns.$index.key"] = column.key
                values["columns.$index.type"] = column.type
                values["columns.$index.is_required"] = column.isRequired
            }
            state.setAll(values)
            columnCount = directory.columns.size.coerceAtLeast(1)
            loaded = true
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            loadError = exception
        }
    }

    val title = stringResource(if (directoryId == null) R.string.directories_new else R.string.directories_edit)
    val failure = loadError
    if (failure != null) {
        CrmScaffold(title = title) { padding ->
            ErrorState(
                error = failure,
                onRetry = {
                    loadError = null
                    attempt += 1
                },
                modifier = Modifier.padding(padding),
            )
        }
        return
    }

    val requiredMessage = stringResource(R.string.form_required)
    val savedMessage = stringResource(if (directoryId == null) R.string.directories_created_message else R.string.directories_saved_message)
    val typeOptions = DirectoryColumn.TYPES.map { type -> Option(type, directoryColumnTypeLabel(type)) }
    val columnLabel = stringResource(R.string.directories_column_label)
    val columnKeyLabel = stringResource(R.string.directories_column_key)
    val columnKeyHint = stringResource(R.string.directories_column_key_hint)
    val columnTypeLabel = stringResource(R.string.directories_column_type)
    val columnRequiredLabel = stringResource(R.string.directories_column_required)
    val mainSpecs = listOf(
        FieldSpec.Text("name", stringResource(R.string.directories_name), required = true, maxLength = 255),
        FieldSpec.Text("slug", stringResource(R.string.directories_slug), hint = stringResource(R.string.directories_slug_hint), maxLength = 255),
        FieldSpec.Multiline("description", stringResource(R.string.directories_description)),
        FieldSpec.Switch(
            "csv_exchange_enabled",
            stringResource(R.string.directories_csv_enabled),
            hint = stringResource(R.string.directories_csv_enabled_hint),
        ),
    )

    fun columnSpecs(index: Int): List<FieldSpec> = listOf(
        FieldSpec.Text("columns.$index.label", columnLabel, required = true, maxLength = 255),
        FieldSpec.Text("columns.$index.key", columnKeyLabel, hint = columnKeyHint, maxLength = 255),
        FieldSpec.Select("columns.$index.type", columnTypeLabel, typeOptions, required = true),
        FieldSpec.Switch("columns.$index.is_required", columnRequiredLabel),
    )

    fun addColumn() {
        state.set("columns.$columnCount.type", DirectoryColumn.TYPE_TEXT)
        state.set("columns.$columnCount.is_required", false)
        columnCount += 1
    }

    fun removeColumn(index: Int) {
        for (row in index until columnCount - 1) {
            COLUMN_FIELDS.forEach { field -> state.set("columns.$row.$field", state.value("columns.${row + 1}.$field")) }
        }
        COLUMN_FIELDS.forEach { field -> state.set("columns.${columnCount - 1}.$field", null) }
        columnCount -= 1
    }

    DirectoryEditorScaffold(
        title = title,
        submitLabel = stringResource(if (directoryId == null) R.string.directories_create else R.string.action_save),
        isLoading = !loaded,
        isBusy = runner.isBusy,
        generalError = state.generalError,
        onSubmit = {
            val allSpecs = mainSpecs + (0 until columnCount).flatMap { columnSpecs(it) }
            if (state.validate(allSpecs, requiredMessage)) {
                val body = directoryBody(state, columnCount)
                runner.execute(
                    successMessage = savedMessage,
                    onError = { error -> state.applyErrors(error) },
                    onSuccess = { saved: ReferenceDirectory ->
                        if (directoryId == null) {
                            navigator.replace(DirectoriesRoutes.detail(saved.id))
                        } else {
                            navigator.back()
                        }
                    },
                ) {
                    if (directoryId == null) repository.create(body) else repository.update(directoryId, body)
                }
            }
        },
    ) {
        FormFields(specs = mainSpecs, state = state, enabled = !runner.isBusy)
        SectionHeader(title = stringResource(R.string.directories_columns_title))
        Text(
            text = stringResource(R.string.directories_columns_hint),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(horizontal = 16.dp),
        )
        val columnsError = state.errors["columns"]
        if (columnsError != null) {
            Text(
                text = columnsError,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.error,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
            )
        }
        for (index in 0 until columnCount) {
            InfoCard {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(start = 16.dp, end = 4.dp, top = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        text = stringResource(R.string.directories_column_number, index + 1),
                        style = MaterialTheme.typography.titleSmall,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.weight(1f),
                    )
                    IconButton(onClick = { removeColumn(index) }, enabled = columnCount > 1 && !runner.isBusy) {
                        Icon(AppIcons.Delete, contentDescription = stringResource(R.string.directories_remove_column))
                    }
                }
                FormFields(
                    specs = columnSpecs(index),
                    state = state,
                    modifier = Modifier.padding(bottom = 8.dp),
                    enabled = !runner.isBusy,
                )
            }
        }
        OutlinedButton(
            onClick = { addColumn() },
            enabled = columnCount < MAX_COLUMNS && !runner.isBusy,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
        ) {
            Icon(AppIcons.Add, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.size(8.dp))
            Text(stringResource(R.string.directories_add_column))
        }
    }
}

/** Request body of `POST/PATCH directories` (columns keep their keys so existing values stay bound). */
private fun directoryBody(state: FormState, columnCount: Int): JsonObject = jsonObjectOf(
    "name" to state.string("name").trim(),
    "slug" to state.string("slug").trim().ifBlank { null },
    "description" to state.string("description").trim().ifBlank { null },
    "csv_exchange_enabled" to state.bool("csv_exchange_enabled"),
    "columns" to (0 until columnCount).map { index ->
        mapOf(
            "label" to state.string("columns.$index.label").trim(),
            "key" to state.string("columns.$index.key").trim().ifBlank { null },
            "type" to state.string("columns.$index.type").ifBlank { DirectoryColumn.TYPE_TEXT },
            "is_required" to state.bool("columns.$index.is_required"),
        )
    },
)

/** Form layout with a bottom submit button (like `FormScreen`, but with custom content). */
@Composable
private fun DirectoryEditorScaffold(
    title: String,
    submitLabel: String,
    isLoading: Boolean,
    isBusy: Boolean,
    generalError: String?,
    onSubmit: () -> Unit,
    content: @Composable ColumnScope.() -> Unit,
) {
    CrmScaffold(
        title = title,
        bottomBar = {
            if (!isLoading) {
                Surface(tonalElevation = 3.dp) {
                    Button(
                        onClick = onSubmit,
                        enabled = !isBusy,
                        modifier = Modifier
                            .fillMaxWidth()
                            .navigationBarsPadding()
                            .imePadding()
                            .padding(horizontal = 16.dp, vertical = 12.dp),
                    ) {
                        if (isBusy) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(18.dp),
                                strokeWidth = 2.dp,
                                color = MaterialTheme.colorScheme.onPrimary,
                            )
                            Spacer(Modifier.size(8.dp))
                        }
                        Text(submitLabel)
                    }
                }
            }
        },
    ) { padding ->
        if (isLoading) {
            LoadingState(modifier = Modifier.padding(padding))
        } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .verticalScroll(rememberScrollState())
                    .padding(vertical = 12.dp),
            ) {
                if (!generalError.isNullOrBlank()) {
                    Surface(
                        color = MaterialTheme.colorScheme.errorContainer,
                        contentColor = MaterialTheme.colorScheme.onErrorContainer,
                        shape = MaterialTheme.shapes.medium,
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 8.dp),
                    ) {
                        Text(generalError, modifier = Modifier.padding(12.dp))
                    }
                }
                content()
                Spacer(Modifier.height(24.dp))
            }
        }
    }
}
