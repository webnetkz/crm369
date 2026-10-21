package com.appswebnetkz.crm369.feature.funnels

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.OptionPickerDialog
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.form.PickerInput
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException

/** Editable custom deal field of the funnel form ([uid] keeps the row identity while editing). */
private data class DealFieldDraft(
    val uid: Int,
    val key: String,
    val label: String,
    val type: String,
    val isRequired: Boolean,
)

private const val DEFAULT_FUNNEL_COLOR = "#2563EB"
private const val DEAL_FIELDS_ERROR_PREFIX = "deal_fields."

/**
 * Create ([funnelId] = null) or edit a funnel: name, description, color, activity, access groups
 * and custom deal fields. The update endpoint replaces everything (omitted groups / fields are
 * removed, an omitted slug is regenerated), so the current slug and all fields are always sent.
 */
@Composable
fun FunnelFormScreen(funnelId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { FunnelsRepository(api) }
    val state = remember(funnelId) { FormState(mapOf("is_active" to true, "color" to DEFAULT_FUNNEL_COLOR)) }
    val drafts = remember(funnelId) { mutableStateListOf<DealFieldDraft>() }
    var page by remember(funnelId) { mutableStateOf<FunnelPage?>(null) }
    var slug by remember(funnelId) { mutableStateOf<String?>(null) }
    var loadError by remember(funnelId) { mutableStateOf<Throwable?>(null) }
    var attempt by remember { mutableStateOf(0) }
    var nextUid by remember { mutableStateOf(0) }

    LaunchedEffect(funnelId, attempt) {
        loadError = null
        try {
            val loaded = repository.page(funnelId)
            val board = loaded.activeFunnel
            if (funnelId != null && board != null) {
                state.setAll(
                    mapOf(
                        "name" to board.name,
                        "description" to board.description,
                        "color" to board.color,
                        "is_active" to board.isActive,
                        "group_ids" to board.groupIds.map { it.toString() },
                    ),
                )
                slug = board.slug
                drafts.clear()
                board.dealFields.forEachIndexed { index, field ->
                    drafts.add(
                        DealFieldDraft(
                            uid = index,
                            key = field.key,
                            label = field.label,
                            type = field.type,
                            isRequired = field.isRequired,
                        ),
                    )
                }
                nextUid = board.dealFields.size
            }
            page = loaded
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            loadError = exception
        }
    }

    val title = stringResource(if (funnelId == null) R.string.funnels_create_funnel else R.string.funnels_edit_funnel)
    val loaded = page
    val error = loadError
    if (loaded == null && error != null) {
        CrmScaffold(title = title) { padding ->
            ErrorState(error = error, onRetry = { attempt += 1 }, modifier = Modifier.padding(padding))
        }
    } else {
        val currentGroups: List<FunnelGroup> = if (funnelId != null) loaded?.activeFunnel?.groups.orEmpty() else emptyList()
        val groupOptions = (loaded?.availableGroups.orEmpty() + currentGroups)
            .distinctBy { it.id }
            .map { group -> Option(value = group.id.toString(), label = group.name, subtitle = group.description) }
        val specs: List<FieldSpec> = listOf(
            FieldSpec.Text("name", stringResource(R.string.funnels_name), required = true, maxLength = 255),
            FieldSpec.Multiline("description", stringResource(R.string.funnels_description), maxLength = 10000),
            FieldSpec.Color("color", stringResource(R.string.funnels_color)),
            FieldSpec.Switch("is_active", stringResource(R.string.funnels_active)),
            FieldSpec.MultiSelect(
                "group_ids",
                stringResource(R.string.funnels_access_groups),
                groupOptions,
                hint = stringResource(R.string.funnels_access_groups_hint),
                numeric = true,
            ),
        )
        FormScreen(
            title = title,
            specs = specs,
            state = state,
            isLoading = loaded == null,
            successMessage = stringResource(if (funnelId == null) R.string.funnels_funnel_created else R.string.funnels_funnel_updated),
            onSubmit = {
                val extra = LinkedHashMap<String, Any?>()
                if (funnelId != null) extra["slug"] = slug
                extra["deal_fields"] = drafts.map { draft ->
                    mapOf(
                        "key" to draft.key.trim().lowercase(),
                        "label" to draft.label.trim(),
                        "type" to draft.type,
                        "is_required" to draft.isRequired,
                    )
                }
                val body = state.toJson(specs, extra = extra)
                if (funnelId == null) {
                    val created = repository.createFunnel(body)
                    navigator.replace(FunnelsRoutes.board(created.id))
                } else {
                    repository.updateFunnel(funnelId, body)
                    navigator.back()
                }
            },
            onSuccess = {},
            footer = {
                DealFieldsEditor(
                    drafts = drafts,
                    state = state,
                    fieldTypes = loaded?.fieldTypes.orEmpty(),
                    onAdd = {
                        drafts.add(DealFieldDraft(uid = nextUid, key = "", label = "", type = DealField.TYPE_TEXT, isRequired = false))
                        nextUid += 1
                    },
                )
            },
        )
    }
}

/** Small editor of the funnel's custom deal fields (label, key, type, required). */
@Composable
private fun DealFieldsEditor(
    drafts: SnapshotStateList<DealFieldDraft>,
    state: FormState,
    fieldTypes: List<ValueLabel>,
    onAdd: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Text(
            text = stringResource(R.string.funnels_deal_fields),
            style = MaterialTheme.typography.titleSmall,
            color = MaterialTheme.colorScheme.primary,
            modifier = Modifier.padding(top = 12.dp),
        )
        Text(
            text = stringResource(R.string.funnels_deal_fields_hint),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        if (drafts.isEmpty()) {
            Text(
                text = stringResource(R.string.funnels_no_custom_fields),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        drafts.forEachIndexed { index, draft ->
            key(draft.uid) {
                DealFieldEditor(
                    index = index,
                    draft = draft,
                    state = state,
                    fieldTypes = fieldTypes,
                    onChange = { updated ->
                        val position = drafts.indexOfFirst { it.uid == updated.uid }
                        if (position >= 0) drafts[position] = updated
                    },
                    onRemove = {
                        drafts.removeAll { it.uid == draft.uid }
                        state.errors.keys
                            .filter { it.startsWith(DEAL_FIELDS_ERROR_PREFIX) }
                            .forEach { errorKey -> state.errors.remove(errorKey) }
                    },
                )
            }
        }
        OutlinedButton(onClick = onAdd) {
            Icon(AppIcons.Add, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.size(8.dp))
            Text(stringResource(R.string.funnels_add_field))
        }
    }
}

@Composable
private fun DealFieldEditor(
    index: Int,
    draft: DealFieldDraft,
    state: FormState,
    fieldTypes: List<ValueLabel>,
    onChange: (DealFieldDraft) -> Unit,
    onRemove: () -> Unit,
) {
    // 422 errors arrive as "deal_fields.<index>.<attribute>".
    val prefix = "$DEAL_FIELDS_ERROR_PREFIX$index."
    val labelError = state.errors[prefix + "label"]
    val keyError = state.errors[prefix + "key"]
    val typeError = state.errors[prefix + "type"]
    val typeOptions = DealField.TYPES.map { type -> Option(value = type, label = fieldTypeLabel(type, fieldTypes)) }

    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 12.dp, end = 12.dp, bottom = 12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = stringResource(R.string.funnels_field_number, index + 1),
                    style = MaterialTheme.typography.labelLarge,
                    modifier = Modifier.weight(1f),
                )
                IconButton(onClick = onRemove) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.funnels_remove_field))
                }
            }
            OutlinedTextField(
                value = draft.label,
                onValueChange = { value ->
                    state.errors.remove(prefix + "label")
                    onChange(draft.copy(label = value.take(255)))
                },
                label = { Text(stringResource(R.string.funnels_field_label) + " *") },
                isError = labelError != null,
                supportingText = if (labelError != null) {
                    { Text(labelError) }
                } else {
                    null
                },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value = draft.key,
                onValueChange = { value ->
                    state.errors.remove(prefix + "key")
                    onChange(draft.copy(key = value.filter { it.isFieldKeyChar() }.take(50)))
                },
                label = { Text(stringResource(R.string.funnels_field_key) + " *") },
                isError = keyError != null,
                supportingText = { Text(keyError ?: stringResource(R.string.funnels_field_key_hint)) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            PickerInput(
                label = stringResource(R.string.funnels_field_type),
                display = fieldTypeLabel(draft.type, fieldTypes),
                enabled = true,
                error = typeError,
                hint = null,
            ) { close ->
                OptionPickerDialog(
                    title = stringResource(R.string.funnels_field_type),
                    options = typeOptions,
                    selected = draft.type,
                    onSelect = { option -> if (option != null) onChange(draft.copy(type = option.value)) },
                    onDismiss = close,
                    allowClear = false,
                )
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = stringResource(R.string.funnels_field_required),
                    style = MaterialTheme.typography.bodyLarge,
                    modifier = Modifier.weight(1f),
                )
                Switch(checked = draft.isRequired, onCheckedChange = { checked -> onChange(draft.copy(isRequired = checked)) })
            }
        }
    }
}

/** Allowed characters of a custom field key: `[a-zA-Z0-9_]`. */
private fun Char.isFieldKeyChar(): Boolean =
    this in 'a'..'z' || this in 'A'..'Z' || this in '0'..'9' || this == '_'
