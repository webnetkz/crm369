package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.OptionPickerDialog
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Task card (`GET tasks/{id}` → `activeTask`) with quick status change, subtasks and the discussion. */
@Composable
fun TaskDetailScreen(taskId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val bootstrap = rememberBootstrap()
    val repository = remember(api) { TasksRepository(api) }
    val runner = rememberActionRunner()
    val loadable = rememberLoadable(api, taskId) { repository.task(taskId) }
    var confirmDelete by remember { mutableStateOf(false) }
    var statusPickerOpen by remember { mutableStateOf(false) }
    val deletedMessage = stringResource(R.string.tasks_task_deleted)
    val movedMessage = stringResource(R.string.tasks_moved)

    val page = loadable.data
    val task = page?.activeTask
    val canManage = page?.can?.manageTask == true
    val canChangeStatus = task != null && (canManage || task.involves(bootstrap?.user?.id))
    val canAddSubtask = page?.can?.createTask == true && (canManage || task?.involves(bootstrap?.user?.id) == true)

    CrmScaffold(
        title = task?.title ?: stringResource(R.string.tasks_task),
        subtitle = if (task != null) task.projectName ?: stringResource(R.string.tasks_group_standalone) else null,
        actions = {
            if (task != null) {
                IconButton(onClick = { navigator.open(TasksRoutes.discussion(taskId)) }) {
                    Icon(AppIcons.Chat, contentDescription = stringResource(R.string.tasks_discussion))
                }
            }
            if (canManage) {
                IconButton(onClick = { navigator.open(TasksRoutes.editTask(taskId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = loadable, modifier = Modifier.padding(padding)) { data ->
            val active = data.activeTask
            if (active == null) {
                EmptyState(title = stringResource(R.string.tasks_task_not_found), icon = AppIcons.Tasks)
            } else {
                TaskDetailContent(
                    page = data,
                    task = active,
                    canChangeStatus = canChangeStatus,
                    canAddSubtask = canAddSubtask,
                    onChangeStatus = { statusPickerOpen = true },
                )
            }
        }
    }

    val currentTask = task
    if (statusPickerOpen && page != null && currentTask != null) {
        OptionPickerDialog(
            title = stringResource(R.string.tasks_change_status),
            options = page.statuses.map { Option(it.value, it.label) },
            selected = currentTask.status,
            onSelect = { option ->
                statusPickerOpen = false
                if (option != null && option.value != currentTask.status) {
                    runner.execute(successMessage = movedMessage, onSuccess = { _: Unit -> loadable.load() }) {
                        repository.moveTask(taskId, option.value)
                    }
                }
            },
            onDismiss = { statusPickerOpen = false },
            allowClear = false,
        )
    }
    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.tasks_task_delete_title),
            text = stringResource(R.string.tasks_task_delete_text),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                confirmDelete = false
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.deleteTask(taskId)
                }
            },
        )
    }
}

@Composable
private fun TaskDetailContent(
    page: ProjectsPage,
    task: ActiveTask,
    canChangeStatus: Boolean,
    canAddSubtask: Boolean,
    onChangeStatus: () -> Unit,
) {
    val navigator = LocalNavigator.current
    val stage = page.stage(task.status)
    val completed = task.completedAt != null || stage?.isCompleted == true
    val overdue = isOverdue(task.dueAt, completed)
    val projectId = task.projectId
    val parentId = task.parentTaskId
    val description = task.description
    val notSet = stringResource(R.string.tasks_not_set)

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
    ) {
        Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp)) {
            Text(text = task.title, style = MaterialTheme.typography.titleLarge)
            Spacer(Modifier.height(8.dp))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                TaskStatusChip(stage = stage, fallback = task.status, onClick = if (canChangeStatus) onChangeStatus else null)
                StatusChip(text = page.importanceLabel(task.importance), color = importanceColor(task.importance))
                if (overdue) {
                    StatusChip(text = stringResource(R.string.tasks_overdue), color = MaterialTheme.colorScheme.error)
                }
            }
            if (canChangeStatus) {
                TextButton(onClick = onChangeStatus) {
                    Text(stringResource(R.string.tasks_change_status))
                }
            }
        }
        OutlinedButton(
            onClick = { navigator.open(TasksRoutes.discussion(task.id)) },
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp),
        ) {
            Icon(AppIcons.Chat, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(stringResource(R.string.tasks_discussion))
        }

        InfoCard(title = stringResource(R.string.tasks_section_main)) {
            KeyValueRow(
                label = stringResource(R.string.tasks_due),
                value = Formatters.dateTime(task.dueAt).ifBlank { notSet },
                icon = AppIcons.Schedule,
            )
            KeyValueRow(
                label = stringResource(R.string.tasks_importance),
                value = page.importanceLabel(task.importance),
                icon = AppIcons.Flag,
            )
            KeyValueRow(
                label = stringResource(R.string.tasks_complexity),
                value = stringResource(R.string.tasks_complexity_value, task.complexity, MAX_COMPLEXITY),
                icon = AppIcons.Tune,
            )
            KeyValueRow(
                label = stringResource(R.string.tasks_project),
                value = task.projectName ?: stringResource(R.string.tasks_group_standalone),
                icon = AppIcons.Projects,
                onClick = if (projectId != null) {
                    { navigator.open(TasksRoutes.project(projectId)) }
                } else {
                    null
                },
            )
            KeyValueRow(
                label = stringResource(R.string.tasks_parent),
                value = task.parentTaskTitle ?: parentId?.let { id -> stringResource(R.string.tasks_task_number, id) },
                icon = AppIcons.Tasks,
                onClick = if (parentId != null) {
                    { navigator.open(TasksRoutes.task(parentId)) }
                } else {
                    null
                },
            )
        }

        InfoCard(title = stringResource(R.string.tasks_section_people)) {
            PersonRow(label = stringResource(R.string.tasks_creator), user = task.creator, emptyText = notSet)
            PersonRow(label = stringResource(R.string.tasks_assignee), user = task.assignee, emptyText = stringResource(R.string.tasks_unassigned))
            if (task.coAssignees.isEmpty()) {
                KeyValueRow(label = stringResource(R.string.tasks_co_assignees), value = stringResource(R.string.tasks_no_co_assignees))
            } else {
                task.coAssignees.forEach { user ->
                    PersonRow(label = stringResource(R.string.tasks_co_assignee), user = user)
                }
            }
        }

        InfoCard(title = stringResource(R.string.tasks_description)) {
            Text(
                text = description ?: stringResource(R.string.tasks_no_description),
                style = MaterialTheme.typography.bodyMedium,
                color = if (description != null) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            )
        }

        InfoCard(title = stringResource(R.string.tasks_subtasks_count, task.subtasks.size)) {
            if (task.subtasks.isEmpty()) {
                Text(
                    text = stringResource(R.string.tasks_no_subtasks),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            }
            task.subtasks.forEach { subtask ->
                TaskRow(
                    task = subtask,
                    stage = page.stage(subtask.status),
                    onClick = { navigator.open(TasksRoutes.task(subtask.id)) },
                )
                RowDivider()
            }
            if (canAddSubtask) {
                TextButton(
                    onClick = { navigator.open(TasksRoutes.newTask(projectId = task.projectId, parentTaskId = task.id)) },
                    modifier = Modifier.padding(horizontal = 8.dp),
                ) {
                    Icon(AppIcons.Add, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(stringResource(R.string.tasks_add_subtask))
                }
            }
        }

        InfoCard {
            KeyValueRow(label = stringResource(R.string.tasks_created_at), value = Formatters.dateTime(task.createdAt), icon = AppIcons.Time)
            KeyValueRow(label = stringResource(R.string.tasks_updated_at), value = Formatters.dateTime(task.updatedAt), icon = AppIcons.History)
            KeyValueRow(label = stringResource(R.string.tasks_completed_at), value = Formatters.dateTime(task.completedAt), icon = AppIcons.Done)
        }
        Spacer(Modifier.height(24.dp))
    }
}
