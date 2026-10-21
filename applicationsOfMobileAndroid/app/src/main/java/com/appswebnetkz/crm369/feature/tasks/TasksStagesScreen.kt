package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormFields
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/**
 * Kanban stages (`taskOptions.statuses`) for users with `can.manageTaskStages`: add
 * (`POST task-stages`), rename / recolor (`PATCH task-stages/{id}`), reorder (`PATCH task-stages/move`).
 */
@Composable
fun TaskStagesScreen() {
    val api = rememberApi()
    val repository = remember(api) { TasksRepository(api) }
    val runner = rememberActionRunner()
    val loadable = rememberLoadable(api) { repository.workspace() }
    var editing by remember { mutableStateOf<TaskStage?>(null) }
    var creating by remember { mutableStateOf(false) }
    val canManage = loadable.data?.can?.manageTaskStages == true
    val orderSavedMessage = stringResource(R.string.tasks_stage_order_saved)

    CrmScaffold(
        title = stringResource(R.string.tasks_stages),
        floatingActionButton = {
            if (canManage) {
                FloatingActionButton(onClick = { creating = true }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.tasks_stage_new))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = loadable,
            modifier = Modifier.padding(padding),
            isEmpty = { page -> page.statuses.isEmpty() },
            empty = { EmptyState(title = stringResource(R.string.tasks_no_stages), icon = AppIcons.Palette) },
        ) { page ->
            val stages = page.statuses
            LazyColumn(modifier = Modifier.fillMaxSize()) {
                item(key = "hint") {
                    Text(
                        text = stringResource(if (page.can.manageTaskStages) R.string.tasks_stages_hint else R.string.tasks_stages_read_only),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp),
                    )
                }
                itemsIndexed(stages, key = { _, stage -> "stage-" + (stage.id?.toString() ?: stage.value) }) { index, stage ->
                    StageRow(
                        stage = stage,
                        canMoveUp = page.can.manageTaskStages && index > 0 && !runner.isBusy,
                        canMoveDown = page.can.manageTaskStages && index < stages.size - 1 && !runner.isBusy,
                        canEdit = page.can.manageTaskStages && stage.id != null,
                        onMoveUp = {
                            val reordered = stages.toMutableList().apply { add(index - 1, removeAt(index)) }
                            loadable.update { current -> current.copy(statuses = reordered) }
                            runner.execute(
                                successMessage = orderSavedMessage,
                                onError = { _ ->
                                    loadable.load()
                                    false
                                },
                                onSuccess = { updated: List<TaskStage> ->
                                    if (updated.isNotEmpty()) loadable.update { current -> current.copy(statuses = updated) }
                                },
                            ) { repository.moveStages(reordered.mapNotNull { it.id }) }
                        },
                        onMoveDown = {
                            val reordered = stages.toMutableList().apply { add(index + 1, removeAt(index)) }
                            loadable.update { current -> current.copy(statuses = reordered) }
                            runner.execute(
                                successMessage = orderSavedMessage,
                                onError = { _ ->
                                    loadable.load()
                                    false
                                },
                                onSuccess = { updated: List<TaskStage> ->
                                    if (updated.isNotEmpty()) loadable.update { current -> current.copy(statuses = updated) }
                                },
                            ) { repository.moveStages(reordered.mapNotNull { it.id }) }
                        },
                        onEdit = { editing = stage },
                    )
                    RowDivider()
                }
                item(key = "bottom-space") { Spacer(Modifier.height(88.dp)) }
            }
        }
    }

    val editedStage = editing
    if (creating || editedStage != null) {
        StageDialog(
            stage = editedStage,
            onDismiss = {
                creating = false
                editing = null
            },
            onSave = { name, color ->
                val stageId = editedStage?.id
                if (stageId != null) {
                    repository.updateStage(stageId, name, color)
                } else {
                    repository.createStage(name, color)
                }
            },
            onSaved = {
                creating = false
                editing = null
                loadable.load()
            },
        )
    }
}

@Composable
private fun StageRow(
    stage: TaskStage,
    canMoveUp: Boolean,
    canMoveDown: Boolean,
    canEdit: Boolean,
    onMoveUp: () -> Unit,
    onMoveDown: () -> Unit,
    onEdit: () -> Unit,
) {
    val completedLabel = stringResource(R.string.tasks_stage_final)
    ListRow(
        title = stage.label,
        subtitle = listOfNotNull(stage.value, if (stage.isCompleted) completedLabel else null).joinToString(" · "),
        leading = { ColorDot(color = stageColor(stage), diameter = 20) },
        trailing = {
            Row {
                IconButton(onClick = onMoveUp, enabled = canMoveUp) {
                    Icon(TasksIcons.MoveUp, contentDescription = stringResource(R.string.tasks_stage_move_up))
                }
                IconButton(onClick = onMoveDown, enabled = canMoveDown) {
                    Icon(TasksIcons.MoveDown, contentDescription = stringResource(R.string.tasks_stage_move_down))
                }
                if (canEdit) {
                    IconButton(onClick = onEdit) {
                        Icon(AppIcons.Edit, contentDescription = stringResource(R.string.tasks_stage_edit))
                    }
                }
            }
        },
        onClick = if (canEdit) onEdit else null,
    )
}

/** Add / edit dialog: name and column color (form kit fields, server 422 errors shown under them). */
@Composable
private fun StageDialog(
    stage: TaskStage?,
    onDismiss: () -> Unit,
    onSave: suspend (name: String, color: String) -> Unit,
    onSaved: () -> Unit,
) {
    val runner = rememberActionRunner()
    val state = remember(stage) {
        FormState(
            mapOf(
                "name" to stage?.label.orEmpty(),
                "color" to (stage?.color ?: FieldSpec.DEFAULT_COLOR_PRESETS.first()),
            ),
        )
    }
    val specs = listOf(
        FieldSpec.Text("name", stringResource(R.string.tasks_stage_name), required = true, maxLength = 255),
        FieldSpec.Color("color", stringResource(R.string.tasks_stage_color), required = true),
    )
    val requiredMessage = stringResource(R.string.form_required)
    val savedMessage = stringResource(if (stage == null) R.string.tasks_stage_created else R.string.tasks_stage_updated)

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(if (stage == null) R.string.tasks_stage_new else R.string.tasks_stage_edit)) },
        text = { FormFields(specs = specs, state = state, enabled = !runner.isBusy) },
        confirmButton = {
            TextButton(
                enabled = !runner.isBusy,
                onClick = {
                    if (state.validate(specs, requiredMessage)) {
                        val name = state.string("name").trim()
                        val color = state.string("color").trim()
                        runner.execute(
                            successMessage = savedMessage,
                            onError = { error -> state.applyErrors(error) },
                            onSuccess = { _: Unit -> onSaved() },
                        ) { onSave(name, color) }
                    }
                },
            ) {
                Text(stringResource(R.string.action_save))
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text(stringResource(R.string.action_cancel))
            }
        },
    )
}
