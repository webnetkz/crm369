package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.FilterChip
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.ChipRow
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.DebouncedSearchField
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Projects the user can see (`GET projects`): owner, members/tasks counters, progress, archive badge. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProjectsListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { TasksRepository(api) }
    val loadable = rememberLoadable(api) { repository.projects() }
    var query by rememberSaveable { mutableStateOf("") }
    var showArchived by rememberSaveable { mutableStateOf(true) }
    val canCreate = loadable.data?.can?.createProject == true

    CrmScaffold(
        title = stringResource(R.string.tasks_projects),
        subtitle = loadable.data?.projects?.size?.let { count -> stringResource(R.string.tasks_projects_count, count) },
        floatingActionButton = {
            if (canCreate) {
                FloatingActionButton(onClick = { navigator.open(TasksRoutes.NEW_PROJECT) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.tasks_new_project))
                }
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            DebouncedSearchField(
                onQuery = { value -> query = value },
                initialValue = query,
                placeholder = stringResource(R.string.tasks_projects_search),
            )
            ChipRow {
                FilterChip(
                    selected = showArchived,
                    onClick = { showArchived = !showArchived },
                    label = { Text(stringResource(R.string.tasks_show_archived)) },
                    leadingIcon = if (showArchived) {
                        { Icon(AppIcons.Check, contentDescription = null) }
                    } else {
                        null
                    },
                )
            }
            LoadableContent(
                loadable = loadable,
                isEmpty = { page -> page.projects.isEmpty() },
                empty = {
                    EmptyState(
                        title = stringResource(R.string.tasks_projects_empty),
                        message = stringResource(R.string.tasks_projects_empty_hint),
                        icon = AppIcons.Projects,
                        actionLabel = if (canCreate) stringResource(R.string.tasks_new_project) else null,
                        onAction = if (canCreate) {
                            { navigator.open(TasksRoutes.NEW_PROJECT) }
                        } else {
                            null
                        },
                    )
                },
            ) { page ->
                val text = query.trim()
                val visible = page.projects
                    .filter { project -> showArchived || !project.isArchived }
                    .filter { project -> text.isEmpty() || project.name.contains(text, ignoreCase = true) }
                    .sortedBy { project -> project.isArchived }
                if (visible.isEmpty()) {
                    EmptyState(title = stringResource(R.string.tasks_projects_not_found), icon = AppIcons.Search)
                } else {
                    LazyColumn(modifier = Modifier.fillMaxSize()) {
                        items(visible, key = { project -> project.id }) { project ->
                            ProjectCard(project = project, onClick = { navigator.open(TasksRoutes.project(project.id)) })
                        }
                        item(key = "bottom-space") { Spacer(Modifier.height(88.dp)) }
                    }
                }
            }
        }
    }
}

@Composable
private fun ProjectCard(project: ProjectSummary, onClick: () -> Unit) {
    val owner = project.owner
    val description = project.description
    InfoCard(onClick = onClick) {
        Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = project.name,
                    style = MaterialTheme.typography.titleMedium,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f),
                )
                if (project.isArchived) {
                    StatusChip(text = stringResource(R.string.tasks_archived), color = MaterialTheme.colorScheme.outline)
                }
            }
            if (description != null) {
                Spacer(Modifier.height(4.dp))
                Text(
                    text = description,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            if (owner != null) {
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Avatar(name = owner.name, imageUrl = owner.avatarUrl, size = 24.dp)
                    Spacer(Modifier.width(8.dp))
                    Text(
                        text = stringResource(R.string.tasks_owner_value, owner.name),
                        style = MaterialTheme.typography.bodySmall,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
            Spacer(Modifier.height(8.dp))
            Text(
                text = stringResource(R.string.tasks_project_counts, project.membersCount, project.tasksCount),
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(6.dp))
            LinearProgressIndicator(
                progress = { project.progress },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(4.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(
                    text = stringResource(R.string.tasks_open_done_counts, project.openTasksCount, project.completedTasksCount),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}
