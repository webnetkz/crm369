package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Project page (`GET projects/{id}`): description, owner, members and the task tree. */
@Composable
fun ProjectDetailScreen(projectId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { TasksRepository(api) }
    val runner = rememberActionRunner()
    val loadable = rememberLoadable(api, projectId) { repository.project(projectId) }
    val expanded = remember(projectId) { mutableStateListOf<Long>() }
    var confirmDelete by remember { mutableStateOf(false) }
    val deletedMessage = stringResource(R.string.tasks_project_deleted)

    val page = loadable.data
    val project = page?.activeProject
    val can = page?.can
    val canManage = can?.manageProject == true
    val canAddTask = project != null && can != null && can.createTask && (can.workOnActiveProject || can.manageProject)

    CrmScaffold(
        title = project?.name ?: stringResource(R.string.tasks_project),
        subtitle = if (project?.isArchived == true) stringResource(R.string.tasks_archived) else null,
        actions = {
            if (canManage) {
                IconButton(onClick = { navigator.open(TasksRoutes.editProject(projectId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        },
        floatingActionButton = {
            if (canAddTask) {
                FloatingActionButton(onClick = { navigator.open(TasksRoutes.newTask(projectId)) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.tasks_new_task))
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = loadable, modifier = Modifier.padding(padding)) { data ->
            val active = data.activeProject
            if (active == null) {
                EmptyState(title = stringResource(R.string.tasks_project_not_found), icon = AppIcons.Projects)
            } else {
                val rows = buildTaskRows(active.tasks, TaskFilter.NONE, expanded.toSet())
                LazyColumn(modifier = Modifier.fillMaxSize()) {
                    item(key = "info") { ProjectInfo(project = active) }
                    item(key = "tasks-header") {
                        SectionHeader(title = stringResource(R.string.tasks_project_tasks, active.tasks.size))
                    }
                    if (rows.isEmpty()) {
                        item(key = "no-tasks") {
                            Text(
                                text = stringResource(R.string.tasks_project_no_tasks),
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                            )
                        }
                    }
                    items(rows, key = { row -> "task-${row.task.id}" }) { row ->
                        val task = row.task
                        TaskRow(
                            task = task,
                            stage = data.stage(task.status),
                            depth = row.depth,
                            hasChildren = row.hasChildren,
                            expanded = row.expanded,
                            onToggle = {
                                if (task.id in expanded) expanded.remove(task.id) else expanded.add(task.id)
                            },
                            onClick = { navigator.open(TasksRoutes.task(task.id)) },
                        )
                        RowDivider()
                    }
                    item(key = "bottom-space") { Spacer(Modifier.height(88.dp)) }
                }
            }
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.tasks_project_delete_title),
            text = stringResource(R.string.tasks_project_delete_text),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                confirmDelete = false
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.deleteProject(projectId)
                }
            },
        )
    }
}

@Composable
private fun ProjectInfo(project: ActiveProject) {
    val description = project.description
    Column(modifier = Modifier.fillMaxWidth()) {
        if (project.isArchived) {
            Row(modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) {
                StatusChip(text = stringResource(R.string.tasks_archived), color = MaterialTheme.colorScheme.outline)
            }
        }
        if (description != null) {
            InfoCard(title = stringResource(R.string.tasks_description)) {
                Text(
                    text = description,
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            }
        }
        InfoCard(title = stringResource(R.string.tasks_owner)) {
            PersonRow(label = stringResource(R.string.tasks_owner), user = project.owner, emptyText = stringResource(R.string.tasks_not_set))
        }
        InfoCard(title = stringResource(R.string.tasks_members_count, project.members.size)) {
            if (project.members.isEmpty()) {
                Text(
                    text = stringResource(R.string.tasks_no_members),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            } else {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    project.members.forEach { member -> MemberChip(user = member) }
                }
            }
        }
    }
}
