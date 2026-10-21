package com.appswebnetkz.crm369.feature.forms

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedCard
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshots.SnapshotStateList
import androidx.compose.runtime.snapshots.SnapshotStateMap
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import java.util.concurrent.atomic.AtomicLong
import kotlinx.coroutines.CancellationException
import kotlinx.serialization.json.JsonObject

private const val MAX_FIELDS = 20

/** Editable copy of a form field; [id] is kept for existing fields (fields left out are deleted). */
internal data class FieldDraft(
    val uid: Long,
    val id: Long? = null,
    val label: String = "",
    val type: String = FormFieldDef.TYPE_TEXT,
    val placeholder: String = "",
    val required: Boolean = true,
) {
    fun toPayload(index: Int): Map<String, Any?> = buildMap<String, Any?> {
        if (id != null) put("id", id)
        put("label", label.trim())
        put("type", type)
        put("placeholder", placeholder.trim().ifBlank { null })
        put("is_required", required)
        put("sort_order", (index + 1) * 10)
    }

    companion object {
        fun from(field: FormFieldDef, uid: Long): FieldDraft = FieldDraft(
            uid = uid,
            id = field.id.takeIf { it > 0L },
            label = field.label,
            type = field.type,
            placeholder = field.placeholder.orEmpty(),
            required = field.isRequired,
        )
    }
}

/**
 * Create ([formId] = null) or edit a form: settings, completion behaviour and the field editor.
 * The update replaces the whole form, so `style_settings` is sent back unchanged.
 */
@Composable
fun FormEditScreen(formId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { FormsRepository(api) }
    val state = remember(formId) {
        FormState(
            mapOf(
                "submission_mode" to FormSummary.MODE_TASK,
                "is_active" to true,
                "completion_settings.action" to FormCompletion.ACTION_MESSAGE,
            ),
        )
    }
    val fields = remember(formId) { mutableStateListOf<FieldDraft>() }
    val uids = remember(formId) { AtomicLong(1L) }
    var index by remember(formId) { mutableStateOf<FormsIndex?>(null) }
    var styleSettings by remember(formId) { mutableStateOf<JsonObject?>(null) }
    var loaded by remember(formId) { mutableStateOf(false) }

    LaunchedEffect(formId) {
        try {
            val loadedIndex = repository.index()
            index = loadedIndex
            if (formId == null) {
                styleSettings = loadedIndex.styleDefaults
                val defaults = loadedIndex.completionDefaults
                state.setAll(
                    mapOf(
                        "completion_settings.action" to defaults.action,
                        "completion_settings.success_message" to defaults.successMessage,
                        "completion_settings.redirect_url" to defaults.redirectUrl,
                    ),
                )
                if (fields.isEmpty()) {
                    fields.add(FieldDraft(uid = uids.getAndIncrement()))
                }
            } else {
                val detail = repository.form(formId)
                styleSettings = detail.styleSettings
                state.setAll(
                    mapOf(
                        "name" to detail.summary.name,
                        "description" to detail.summary.description,
                        "submission_mode" to detail.summary.submissionMode,
                        "target_user_id" to detail.targetUser?.id?.toString(),
                        "is_active" to detail.summary.isActive,
                        "completion_settings.action" to detail.completion.action,
                        "completion_settings.success_message" to detail.completion.successMessage,
                        "completion_settings.redirect_url" to detail.completion.redirectUrl,
                    ),
                )
                fields.clear()
                detail.fields.forEach { field -> fields.add(FieldDraft.from(field, uids.getAndIncrement())) }
            }
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            messenger.showError(exception)
        } finally {
            loaded = true
        }
    }

    val currentIndex = index
    val fieldTypes = currentIndex?.fieldTypes?.ifEmpty { null } ?: listOf(
        Option(FormFieldDef.TYPE_TEXT, stringResource(R.string.forms_field_type_text)),
        Option(FormFieldDef.TYPE_TEXTAREA, stringResource(R.string.forms_field_type_textarea)),
        Option(FormFieldDef.TYPE_EMAIL, stringResource(R.string.forms_field_type_email)),
        Option(FormFieldDef.TYPE_NUMBER, stringResource(R.string.forms_field_type_number)),
    )
    val actionOptions = currentIndex?.completionActions?.ifEmpty { null } ?: listOf(
        Option(FormCompletion.ACTION_MESSAGE, stringResource(R.string.forms_completion_message)),
        Option(FormCompletion.ACTION_REDIRECT, stringResource(R.string.forms_completion_redirect)),
        Option(FormCompletion.ACTION_CLOSE, stringResource(R.string.forms_completion_close)),
    )
    val modeOptions = listOf(
        Option(FormSummary.MODE_TASK, stringResource(R.string.forms_mode_task_full)),
        Option(FormSummary.MODE_CHAT, stringResource(R.string.forms_mode_chat_full)),
    )
    val userOptions = currentIndex?.users.orEmpty().map { user ->
        Option(value = user.id.toString(), label = user.name, subtitle = user.email)
    }
    val isChat = state.string("submission_mode") == FormSummary.MODE_CHAT
    val action = state.string("completion_settings.action").ifBlank { FormCompletion.ACTION_MESSAGE }
    val specs = buildList<FieldSpec> {
        add(FieldSpec.Text("name", stringResource(R.string.forms_name), required = true, maxLength = 255))
        add(FieldSpec.Multiline("description", stringResource(R.string.forms_description), minLines = 2, maxLength = 4000))
        add(FieldSpec.Select("submission_mode", stringResource(R.string.forms_submission_mode), options = modeOptions, required = true))
        add(
            FieldSpec.UserPicker(
                "target_user_id",
                stringResource(R.string.forms_target_user),
                users = userOptions,
                required = true,
                hint = if (isChat) stringResource(R.string.forms_target_chat_hint) else null,
            ),
        )
        add(FieldSpec.Switch("is_active", stringResource(R.string.forms_is_active)))
        add(FieldSpec.Section(stringResource(R.string.forms_completion_title)))
        add(FieldSpec.Select("completion_settings.action", stringResource(R.string.forms_completion_action), options = actionOptions, required = true))
        if (action == FormCompletion.ACTION_MESSAGE) {
            add(
                FieldSpec.Multiline(
                    "completion_settings.success_message",
                    stringResource(R.string.forms_success_message),
                    hint = stringResource(R.string.forms_success_message_hint),
                    minLines = 2,
                    maxLength = 1000,
                ),
            )
        }
        if (action == FormCompletion.ACTION_REDIRECT) {
            add(
                FieldSpec.Text(
                    "completion_settings.redirect_url",
                    stringResource(R.string.forms_redirect_url),
                    required = true,
                    hint = stringResource(R.string.forms_redirect_url_hint),
                    maxLength = 2048,
                ),
            )
        }
    }
    val savedMessage = stringResource(if (formId == null) R.string.forms_created else R.string.forms_updated)
    val labelRequired = stringResource(R.string.forms_field_label_required)
    val noFieldsMessage = stringResource(R.string.forms_validation_fields)
    val invalidFieldsMessage = stringResource(R.string.forms_validation_fields_invalid)

    FormScreen(
        title = stringResource(if (formId == null) R.string.forms_new else R.string.forms_edit),
        specs = specs,
        state = state,
        isLoading = !loaded,
        onSubmit = {
            clearFieldErrors(state.errors)
            val problems = fieldErrors(fields, labelRequired)
            if (fields.isEmpty() || problems.isNotEmpty()) {
                problems.forEach { (key, message) -> state.errors[key] = message }
                state.generalError = if (fields.isEmpty()) noFieldsMessage else invalidFieldsMessage
            } else {
                state.generalError = null
                val style = styleSettings
                val extra = buildMap<String, Any?> {
                    put("completion_settings.success_message", state.string("completion_settings.success_message").trim().ifBlank { null })
                    put("completion_settings.redirect_url", state.string("completion_settings.redirect_url").trim().ifBlank { null })
                    put("fields", fields.mapIndexed { position, field -> field.toPayload(position) })
                    if (style != null) put("style_settings", style)
                }
                val body = state.toJson(specs, extra = extra)
                if (formId == null) {
                    val created = repository.create(body)
                    messenger.show(savedMessage)
                    if (created.summary.id > 0L) {
                        navigator.replace(FormsRoutes.detail(created.summary.id))
                    } else {
                        navigator.back()
                    }
                } else {
                    repository.update(formId, body)
                    messenger.show(savedMessage)
                    navigator.back()
                }
            }
        },
        onSuccess = {},
        footer = {
            FieldsEditor(
                fields = fields,
                fieldTypes = fieldTypes,
                errors = state.errors,
                onAdd = {
                    if (fields.size < MAX_FIELDS) {
                        fields.add(FieldDraft(uid = uids.getAndIncrement()))
                    }
                },
            )
        },
    )
}

private fun clearFieldErrors(errors: SnapshotStateMap<String, String>) {
    errors.keys.filter { it == "fields" || it.startsWith("fields.") }.forEach { key -> errors.remove(key) }
}

private fun fieldErrors(fields: List<FieldDraft>, labelRequired: String): Map<String, String> {
    val result = LinkedHashMap<String, String>()
    fields.forEachIndexed { position, field ->
        if (field.label.isBlank()) {
            result["fields.$position.label"] = labelRequired
        }
    }
    return result
}

private fun moveField(fields: SnapshotStateList<FieldDraft>, uid: Long, offset: Int) {
    val from = fields.indexOfFirst { it.uid == uid }
    val to = from + offset
    if (from < 0 || to < 0 || to > fields.lastIndex) return
    val moved = fields.removeAt(from)
    fields.add(to, moved)
}

@Composable
private fun FieldsEditor(
    fields: SnapshotStateList<FieldDraft>,
    fieldTypes: List<Option>,
    errors: SnapshotStateMap<String, String>,
    onAdd: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(text = stringResource(R.string.forms_fields_title), style = MaterialTheme.typography.titleMedium)
        Text(
            text = stringResource(R.string.forms_fields_hint, MAX_FIELDS),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        val listError = errors["fields"]
        if (listError != null) {
            Text(text = listError, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
        }
        fields.forEachIndexed { position, field ->
            key(field.uid) {
                val prefix = "fields.$position."
                FieldCard(
                    field = field,
                    number = position + 1,
                    isFirst = position == 0,
                    isLast = position == fields.lastIndex,
                    canRemove = fields.size > 1,
                    fieldTypes = fieldTypes,
                    labelError = errors[prefix + "label"],
                    otherError = errors.entries.firstOrNull { it.key.startsWith(prefix) && it.key != prefix + "label" }?.value,
                    onChange = { updated ->
                        val current = fields.indexOfFirst { it.uid == updated.uid }
                        if (current >= 0) {
                            fields[current] = updated
                            errors.keys.filter { it.startsWith("fields.$current.") }.forEach { errorKey -> errors.remove(errorKey) }
                        }
                    },
                    onMove = { offset -> moveField(fields, field.uid, offset) },
                    onRemove = {
                        val current = fields.indexOfFirst { it.uid == field.uid }
                        if (current >= 0 && fields.size > 1) {
                            fields.removeAt(current)
                        }
                    },
                )
            }
        }
        if (fields.size < MAX_FIELDS) {
            OutlinedButton(onClick = onAdd) {
                Icon(AppIcons.Add, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.size(8.dp))
                Text(stringResource(R.string.forms_add_field))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FieldCard(
    field: FieldDraft,
    number: Int,
    isFirst: Boolean,
    isLast: Boolean,
    canRemove: Boolean,
    fieldTypes: List<Option>,
    labelError: String?,
    otherError: String?,
    onChange: (FieldDraft) -> Unit,
    onMove: (Int) -> Unit,
    onRemove: () -> Unit,
) {
    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = stringResource(R.string.forms_field_number, number),
                    style = MaterialTheme.typography.titleSmall,
                    modifier = Modifier.weight(1f),
                )
                IconButton(onClick = { onMove(-1) }, enabled = !isFirst) {
                    Icon(FormsIcons.MoveUp, contentDescription = stringResource(R.string.forms_move_up))
                }
                IconButton(onClick = { onMove(1) }, enabled = !isLast) {
                    Icon(FormsIcons.MoveDown, contentDescription = stringResource(R.string.forms_move_down))
                }
                IconButton(onClick = onRemove, enabled = canRemove) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.forms_remove_field))
                }
            }
            OutlinedTextField(
                value = field.label,
                onValueChange = { value -> onChange(field.copy(label = value)) },
                label = { Text(stringResource(R.string.forms_field_label)) },
                singleLine = true,
                isError = labelError != null,
                supportingText = if (labelError != null) {
                    { Text(labelError) }
                } else {
                    null
                },
                modifier = Modifier.fillMaxWidth(),
            )
            Text(
                text = stringResource(R.string.forms_field_type),
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Row(
                modifier = Modifier.horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                fieldTypes.forEach { option ->
                    FilterChip(
                        selected = field.type == option.value,
                        onClick = { onChange(field.copy(type = option.value)) },
                        label = { Text(option.label) },
                    )
                }
            }
            OutlinedTextField(
                value = field.placeholder,
                onValueChange = { value -> onChange(field.copy(placeholder = value)) },
                label = { Text(stringResource(R.string.forms_field_placeholder)) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = stringResource(R.string.forms_field_required),
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.weight(1f),
                )
                Switch(
                    checked = field.required,
                    onCheckedChange = { checked -> onChange(field.copy(required = checked)) },
                )
            }
            if (otherError != null) {
                Text(text = otherError, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }
        }
    }
}
