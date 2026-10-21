package com.appswebnetkz.crm369.feature.edo

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException

/**
 * Create ([documentId] = null) or edit an EDO document. The update replaces the whole document,
 * so every field (and `document_source`) is always sent; an upload document without a new file
 * keeps its current file.
 */
@Composable
fun EdoFormScreen(documentId: Long?) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val repository = remember(api) { EdoRepository(api) }
    val state = remember(documentId) { FormState(mapOf("document_source" to EdoDocument.SOURCE_TEXT)) }
    var loaded by remember(documentId) { mutableStateOf(documentId == null) }
    var loadError by remember(documentId) { mutableStateOf<Throwable?>(null) }
    var attempt by remember(documentId) { mutableIntStateOf(0) }
    var existing by remember(documentId) { mutableStateOf<EdoDocument?>(null) }
    var upload by remember { mutableStateOf<Uri?>(null) }
    var uploadName by remember { mutableStateOf<String?>(null) }
    var selectedEntry by remember { mutableStateOf<EdoFileEntry?>(null) }
    var showFilePicker by remember { mutableStateOf(false) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            upload = uri
            uploadName = uriDisplayName(context, uri)
            state.errors.remove("document_upload")
        }
    }

    LaunchedEffect(documentId, attempt) {
        if (documentId == null) return@LaunchedEffect
        try {
            val document = repository.document(documentId)
            existing = document
            state.setAll(
                mapOf(
                    "title" to document.title,
                    "external_reference" to document.externalReference,
                    "counterparty_name" to document.counterpartyName,
                    "counterparty_identifier" to document.counterpartyIdentifier,
                    "counterparty_email" to document.counterpartyEmail,
                    "document_source" to document.documentSource,
                    "content" to document.content,
                ),
            )
            loaded = true
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            loadError = exception
        }
    }

    val title = stringResource(if (documentId == null) R.string.edo_new else R.string.edo_edit)
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

    val source = state.string("document_source").ifBlank { EdoDocument.SOURCE_TEXT }
    val current = existing
    val sourceOptions = EdoDocument.SOURCES.map { value -> Option(value, edoSourceLabel(value)) }
    val specs = buildList {
        add(FieldSpec.Text("title", stringResource(R.string.edo_title_label), required = true, maxLength = 255))
        add(FieldSpec.Text("external_reference", stringResource(R.string.edo_external_reference), maxLength = 255))
        add(FieldSpec.Text("counterparty_name", stringResource(R.string.edo_counterparty), required = true, maxLength = 255))
        add(
            FieldSpec.Text(
                "counterparty_identifier",
                stringResource(R.string.edo_counterparty_identifier),
                required = true,
                hint = stringResource(R.string.edo_identifier_hint),
            ),
        )
        add(FieldSpec.Email("counterparty_email", stringResource(R.string.edo_counterparty_email)))
        add(FieldSpec.Select("document_source", stringResource(R.string.edo_document_source), sourceOptions, required = true))
        if (source == EdoDocument.SOURCE_TEXT) {
            add(FieldSpec.Multiline("content", stringResource(R.string.edo_content), required = true, minLines = 6))
        }
    }

    val savedMessage = stringResource(if (documentId == null) R.string.edo_created_message else R.string.edo_saved_message)

    FormScreen(
        title = title,
        specs = specs,
        state = state,
        isLoading = !loaded,
        submitLabel = stringResource(if (documentId == null) R.string.edo_create else R.string.action_save),
        successMessage = savedMessage,
        onSubmit = {
            val extra = buildMap<String, Any?> {
                put("counterparty_identifier", state.string("counterparty_identifier").filter { it.isDigit() }.ifBlank { null })
                put("document_source", source)
                put("selected_file_entry_id", if (source == EdoDocument.SOURCE_FILE_ENTRY) selectedEntry?.id else null)
                if (source != EdoDocument.SOURCE_TEXT) put("content", null)
                val metadata = current?.metadata
                if (metadata != null) put("metadata", metadata)
            }
            val body = state.toJson(specs, extra = extra)
            val file = if (source == EdoDocument.SOURCE_UPLOAD) upload else null
            if (documentId == null) {
                val created = repository.create(context, body, file)
                navigator.replace(EdoRoutes.document(created.id))
            } else {
                repository.update(context, documentId, body, file)
                navigator.back()
            }
        },
        onSuccess = {},
        footer = {
            when (source) {
                EdoDocument.SOURCE_UPLOAD -> EdoSourceFileCard(
                    title = stringResource(R.string.edo_upload_label),
                    fileName = uploadName ?: current?.takeIf { it.documentSource == EdoDocument.SOURCE_UPLOAD }?.file?.name,
                    hint = stringResource(R.string.edo_upload_hint),
                    error = state.errors["document_upload"],
                    buttonLabel = stringResource(R.string.edo_choose_file),
                    buttonIcon = AppIcons.Upload,
                    onClick = { picker.launch("*/*") },
                )
                EdoDocument.SOURCE_FILE_ENTRY -> EdoSourceFileCard(
                    title = stringResource(R.string.edo_file_entry_label),
                    fileName = selectedEntry?.name ?: current?.takeIf { it.documentSource == EdoDocument.SOURCE_FILE_ENTRY }?.file?.name,
                    hint = stringResource(R.string.edo_file_entry_hint),
                    error = state.errors["selected_file_entry_id"],
                    buttonLabel = stringResource(R.string.edo_choose_from_files),
                    buttonIcon = AppIcons.FolderOpen,
                    onClick = { showFilePicker = true },
                )
                else -> Unit
            }
        },
    )

    if (showFilePicker) {
        EdoFilePickerDialog(
            repository = repository,
            onSelect = { entry ->
                selectedEntry = entry
                state.errors.remove("selected_file_entry_id")
                showFilePicker = false
            },
            onDismiss = { showFilePicker = false },
        )
    }
}
