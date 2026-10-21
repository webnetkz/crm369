package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.material3.Card
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ScrollableTabRow
import androidx.compose.material3.Tab
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshots.SnapshotStateList
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
import kotlinx.coroutines.launch

/**
 * Content of the main screen tab (also `projects/workspace`): every visible task — standalone and
 * project tasks (`GET projects/workspace`) — as a grouped list or a kanban board, with quick
 * filters (assigned to me / by me / all), a stage filter and a title search.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TasksHomeScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val bootstrap = rememberBootstrap()
    val repository = remember(api) { TasksRepository(api) }
    val runner = rememberActionRunner()
    val workspace = rememberLoadable(api) { repository.workspace() }

    var scope by rememberSaveable { mutableStateOf(TaskScope.ALL) }
    var status by rememberSaveable { mutableStateOf<String?>(null) }
    var query by rememberSaveable { mutableStateOf("") }
    var kanban by rememberSaveable { mutableStateOf(false) }
    var menuOpen by remember { mutableStateOf(false) }
    var statusPickerOpen by remember { mutableStateOf(false) }
    var moveTarget by remember { mutableStateOf<TaskNode?>(null) }
    val expanded = remember { mutableStateListOf<Long>() }

    val page = workspace.data
    val canCreateTask = page?.can?.createTask == true
    val canManageStages = page?.can?.manageTaskStages == true
    val filter = TaskFilter(
        scope = scope,
        userId = bootstrap?.user?.id,
        status = if (kanban) null else status,
        query = query,
    )
    val movedMessage = stringResource(R.string.tasks_moved)
    val webTitle = stringResource(R.string.module_tasks)

    CrmScaffold(
        title = stringResource(R.string.tasks_title),
        actions = {
            IconButton(onClick = { kanban = !kanban }) {
                Icon(
                    imageVector = if (kanban) AppIcons.ListView else TasksIcons.Kanban,
                    contentDescription = stringResource(if (kanban) R.string.tasks_view_list else R.string.tasks_view_kanban),
                )
            }
            IconButton(onClick = { navigator.open(TasksRoutes.PROJECTS) }) {
                Icon(AppIcons.Projects, contentDescription = stringResource(R.string.tasks_projects))
            }
            Box {
                IconButton(onClick = { menuOpen = true }) {
                    Icon(AppIcons.More, contentDescription = stringResource(R.string.tasks_more_actions))
                }
                DropdownMenu(expanded = menuOpen, onDismissRequest = { menuOpen = false }) {
                    if (canManageStages) {
                        DropdownMenuItem(
                            text = { Text(stringResource(R.string.tasks_stages)) },
                            leadingIcon = { Icon(AppIcons.Palette, contentDescription = null) },
                            onClick = {
                                menuOpen = false
                                navigator.open(TasksRoutes.STAGES)
                            },
                        )
                    }
                    DropdownMenuItem(
                        text = { Text(stringResource(R.string.tasks_open_web)) },
                        leadingIcon = { Icon(AppIcons.Public, contentDescription = null) },
                        onClick = {
                            menuOpen = false
                            navigator.openWeb("/projects", webTitle)
                        },
                    )
                }
            }
        },
        floatingActionButton = {
            if (canCreateTask) {
                FloatingActionButton(onClick = { navigator.open(TasksRoutes.newTask()) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.tasks_new_task))
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
                placeholder = stringResource(R.string.tasks_search),
            )
            ChipRow {
                TaskScope.entries.forEach { option ->
                    FilterChip(
                        selected = scope == option,
                        onClick = { scope = option },
                        label = { Text(stringResource(scopeLabel(option))) },
                    )
                }
                if (!kanban) {
                    val currentStatus = status
                    val statusLabel = if (currentStatus != null) {
                        page?.stage(currentStatus)?.label ?: currentStatus
                    } else {
                        stringResource(R.string.tasks_status_any)
                    }
                    FilterChip(
                        selected = currentStatus != null,
                        onClick = { statusPickerOpen = true },
                        label = { Text(statusLabel) },
                        trailingIcon = { Icon(AppIcons.DropDown, contentDescription = null) },
                    )
                }
            }
            LoadableContent(loadable = workspace) { data ->
                if (kanban) {
                    KanbanBoard(page = data, filter = filter, onMove = { task -> moveTarget = task })
                } else {
                    TaskGroupsList(
                        page = data,
                        filter = filter,
                        expanded = expanded,
                        canCreate = data.can.createTask,
                        onResetFilters = {
                            scope = TaskScope.ALL
                            status = null
                        },
                    )
                }
            }
        }
    }

    val currentPage = workspace.data
    if (statusPickerOpen && currentPage != null) {
        OptionPickerDialog(
            title = stringResource(R.string.tasks_status),
            options = currentPage.statuses.map { Option(it.value, it.label) },
            selected = status,
            onSelect = { option ->
                status = option?.value
                statusPickerOpen = false
            },
            onDismiss = { statusPickerOpen = false },
            allowClear = true,
        )
    }
    val target = moveTarget
    if (target != null && currentPage != null) {
        OptionPickerDialog(
            title = stringResource(R.string.tasks_move_to),
            options = currentPage.statuses.map { Option(it.value, it.label) },
            selected = target.status,
            onSelect = { option ->
                moveTarget = null
                if (option != null && option.value != target.status) {
                    runner.execute(successMessage = movedMessage, onSuccess = { _: Unit -> workspace.load() }) {
                        repository.moveTask(target.id, option.value)
                    }
                }
            },
            onDismiss = { moveTarget = null },
            allowClear = false,
        )
    }
}

private fun scopeLabel(scope: TaskScope): Int = when (scope) {
    TaskScope.MINE -> R.string.tasks_scope_mine
    TaskScope.DELEGATED -> R.string.tasks_scope_delegated
    TaskScope.ALL -> R.string.tasks_scope_all
}

/** Grouped list: "without a project" first, then one section per project; trees expand inline. */
@Composable
private fun TaskGroupsList(
    page: ProjectsPage,
    filter: TaskFilter,
    expanded: SnapshotStateList<Long>,
    canCreate: Boolean,
    onResetFilters: () -> Unit,
) {
    val navigator = LocalNavigator.current
    val sections = buildSections(page.groups, filter, expanded.toSet())
    val hasRows = sections.any { it.rows.isNotEmpty() }
    if (!hasRows) {
        if (filter.isActive) {
            EmptyState(
                title = stringResource(R.string.tasks_empty_filtered),
                icon = AppIcons.Tasks,
                actionLabel = stringResource(R.string.tasks_reset_filters),
                onAction = onResetFilters,
            )
        } else {
            EmptyState(
                title = stringResource(R.string.tasks_empty),
                message = stringResource(R.string.tasks_empty_hint),
                icon = AppIcons.Tasks,
                actionLabel = if (canCreate) stringResource(R.string.tasks_new_task) else null,
                onAction = if (canCreate) {
                    { navigator.open(TasksRoutes.newTask()) }
                } else {
                    null
                },
            )
        }
    } else {
        LazyColumn(modifier = Modifier.fillMaxSize()) {
            sections.forEach { section ->
                val group = section.group
                item(key = "group-${group.key}") {
                    GroupHeader(group = group)
                }
                if (section.rows.isEmpty()) {
                    item(key = "group-empty-${group.key}") {
                        Text(
                            text = stringResource(R.string.tasks_group_empty),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                }
                items(section.rows, key = { row -> "task-${row.task.id}" }) { row ->
                    val task = row.task
                    val parentTitle = task.parentTaskTitle
                    TaskRow(
                        task = task,
                        stage = page.stage(task.status),
                        depth = row.depth,
                        overline = if (row.showParent && parentTitle != null) {
                            stringResource(R.string.tasks_subtask_of, parentTitle)
                        } else {
                            null
                        },
                        hasChildren = row.hasChildren,
                        expanded = row.expanded,
                        onToggle = {
                            if (task.id in expanded) expanded.remove(task.id) else expanded.add(task.id)
                        },
                        onClick = { navigator.open(TasksRoutes.task(task.id)) },
                    )
                    RowDivider()
                }
            }
            item(key = "bottom-space") { Spacer(Modifier.height(88.dp)) }
        }
    }
}

/** Section title of a task group (project name or "without a project") with counters. */
@Composable
private fun GroupHeader(group: TaskGroup) {
    val navigator = LocalNavigator.current
    val projectId = group.projectId
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(start = 16.dp, end = 4.dp, top = 12.dp, bottom = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = group.title.ifBlank { stringResource(R.string.tasks_group_standalone) },
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.primary,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = stringResource(R.string.tasks_open_done_counts, group.openTasksCount, group.completedTasksCount),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        if (group.isArchived) {
            StatusChip(text = stringResource(R.string.tasks_archived), color = MaterialTheme.colorScheme.outline)
        }
        if (projectId != null) {
            IconButton(onClick = { navigator.open(TasksRoutes.project(projectId)) }) {
                Icon(AppIcons.ChevronRight, contentDescription = stringResource(R.string.tasks_open_project))
            }
        }
    }
}

/** Kanban: one tab and one page per stage (`taskOptions.statuses`), cards can be moved between stages. */
@OptIn(ExperimentalFoundationApi::class, ExperimentalMaterial3Api::class)
@Composable
private fun KanbanBoard(page: ProjectsPage, filter: TaskFilter, onMove: (TaskNode) -> Unit) {
    val navigator = LocalNavigator.current
    val columns = buildKanban(page.statuses, page.groups, filter)
    if (columns.isEmpty()) {
        EmptyState(title = stringResource(R.string.tasks_no_stages), icon = AppIcons.Tasks)
    } else {
        val pagerState = rememberPagerState(pageCount = { columns.size })
        val coroutineScope = rememberCoroutineScope()
        val selectedIndex = pagerState.currentPage.coerceIn(0, columns.size - 1)
        Column(modifier = Modifier.fillMaxSize()) {
            ScrollableTabRow(selectedTabIndex = selectedIndex, edgePadding = 8.dp) {
                columns.forEachIndexed { index, column ->
                    Tab(
                        selected = index == selectedIndex,
                        onClick = { coroutineScope.launch { pagerState.animateScrollToPage(index) } },
                        text = {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                ColorDot(color = stageColor(column.stage))
                                Spacer(Modifier.width(6.dp))
                                Text(
                                    text = stringResource(R.string.tasks_column_title, column.stage.label, column.tasks.size),
                                    maxLines = 1,
                                )
                            }
                        },
                    )
                }
            }
            HorizontalPager(state = pagerState, modifier = Modifier.fillMaxSize()) { pageIndex ->
                val column = columns.getOrNull(pageIndex)
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(12.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    if (column == null || column.tasks.isEmpty()) {
                        item(key = "column-empty") {
                            Text(
                                text = stringResource(R.string.tasks_column_empty),
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(vertical = 24.dp, horizontal = 4.dp),
                            )
                        }
                    } else {
                        items(column.tasks, key = { task -> task.id }) { task ->
                            KanbanCard(
                                task = task,
                                page = page,
                                onClick = { navigator.open(TasksRoutes.task(task.id)) },
                                onMove = { onMove(task) },
                            )
                        }
                    }
                }
            }
        }
    }
}

/** Kanban card: project, title, importance, due date, assignee; menu "Move" / "Open". */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun KanbanCard(task: TaskNode, page: ProjectsPage, onClick: () -> Unit, onMove: () -> Unit) {
    var menuOpen by remember { mutableStateOf(false) }
    val stage = page.stage(task.status)
    val completed = task.completedAt != null || stage?.isCompleted == true
    val overdue = isOverdue(task.dueAt, completed)
    val assignee = task.assignee
    val parentTitle = task.parentTaskTitle
    Card(onClick = onClick, modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 12.dp, top = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = task.projectName ?: stringResource(R.string.tasks_group_standalone),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            Box {
                IconButton(onClick = { menuOpen = true }) {
                    Icon(AppIcons.More, contentDescription = stringResource(R.string.tasks_more_actions))
                }
                DropdownMenu(expanded = menuOpen, onDismissRequest = { menuOpen = false }) {
                    DropdownMenuItem(
                        text = { Text(stringResource(R.string.tasks_move)) },
                        leadingIcon = { Icon(TasksIcons.Move, contentDescription = null) },
                        onClick = {
                            menuOpen = false
                            onMove()
                        },
                    )
                    DropdownMenuItem(
                        text = { Text(stringResource(R.string.tasks_open)) },
                        leadingIcon = { Icon(AppIcons.ChevronRight, contentDescription = null) },
                        onClick = {
                            menuOpen = false
                            onClick()
                        },
                    )
                }
            }
        }
        Column(modifier = Modifier.padding(start = 12.dp, end = 12.dp, bottom = 12.dp)) {
            if (parentTitle != null) {
                Text(
                    text = stringResource(R.string.tasks_subtask_of, parentTitle),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            Text(
                text = task.title,
                style = MaterialTheme.typography.titleSmall,
                maxLines = 3,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    AppIcons.Flag,
                    contentDescription = stringResource(R.string.tasks_importance),
                    tint = importanceColor(task.importance),
                    modifier = Modifier.size(16.dp),
                )
                Spacer(Modifier.width(4.dp))
                Text(
                    text = page.importanceLabel(task.importance),
                    style = MaterialTheme.typography.labelMedium,
                    color = importanceColor(task.importance),
                )
                Spacer(Modifier.width(12.dp))
                TaskMetaRow(task = task, stage = stage, overdue = overdue, showStage = false)
            }
            if (assignee != null) {
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Avatar(name = assignee.name, imageUrl = assignee.avatarUrl, size = 24.dp)
                    Spacer(Modifier.width(8.dp))
                    Text(
                        text = assignee.name,
                        style = MaterialTheme.typography.bodySmall,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}
