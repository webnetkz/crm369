package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import kotlinx.coroutines.CancellationException

private const val NO_PROJECT = "none"
private const val NO_PARENT = "none"

/** Keys sent through `extra` (converted by hand) instead of the generic form serialization. */
private val MANUAL_KEYS = setOf("project_id", "parent_task_id", "due_at", "sort_order")

/**
 * Create ([taskId] = null) or edit a task. New tasks can be preset to a project ([projectId]) or
 * created as a subtask of [parentTaskId] (the project then follows the parent). The update replaces
 * the whole task, so every field (including `sort_order` and the parent) is always sent.
 */
@Composable
fun TaskFormScreen(taskId: Long?, projectId: Long?, parentTaskId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { TasksRepository(api) }
    val state = remember(taskId, projectId, parentTaskId) {
        FormState(
            mapOf(
                "project_id" to (projectId?.toString() ?: NO_PROJECT),
                "parent_task_id" to (parentTaskId?.toString() ?: NO_PARENT),
                "complexity" to DEFAULT_COMPLEXITY.toString(),
                "sort_order" to "0",
                "co_assignee_user_ids" to emptyList<String>(),
            ),
        )
    }
    var loaded by remember(taskId, projectId, parentTaskId) { mutableStateOf(false) }
    var page by remember(taskId, projectId, parentTaskId) { mutableStateOf<ProjectsPage?>(null) }
    var parentTitle by remember(taskId, projectId, parentTaskId) { mutableStateOf<String?>(null) }

    LaunchedEffect(taskId, projectId, parentTaskId) {
        try {
            if (taskId != null) {
                val loadedPage = repository.task(taskId)
                page = loadedPage
                val task = loadedPage.activeTask
                if (task != null) {
                    parentTitle = task.parentTaskTitle
                    state.setAll(
                        mapOf(
                            "project_id" to (task.projectId?.toString() ?: NO_PROJECT),
                            "parent_task_id" to (task.parentTaskId?.toString() ?: NO_PARENT),
                            "title" to task.title,
                            "description" to task.description,
                            "status" to task.status,
                            "importance" to task.importance,
                            "complexity" to task.complexity.toString(),
                            "due_at" to isoToFormDateTime(task.dueAt),
                            "sort_order" to task.sortOrder.toString(),
                            "assignee_user_id" to task.assignee?.id?.toString(),
                            "co_assignee_user_ids" to task.coAssignees.map { it.id.toString() },
                        ),
                    )
                }
            } else {
                val loadedPage = when {
                    parentTaskId != null -> repository.task(parentTaskId)
                    projectId != null -> repository.project(projectId)
                    else -> repository.workspace()
                }
                page = loadedPage
                val parent = if (parentTaskId != null) loadedPage.activeTask else null
                parentTitle = parent?.title
                val presetProject = parent?.projectId ?: projectId
                val defaultImportance = loadedPage.importances.firstOrNull { it.value == IMPORTANCE_NORMAL }?.value
                    ?: loadedPage.importances.firstOrNull()?.value
                    ?: IMPORTANCE_NORMAL
                state.setAll(
                    mapOf(
                        "project_id" to (presetProject?.toString() ?: NO_PROJECT),
                        "status" to loadedPage.statuses.firstOrNull { !it.isCompleted }?.value,
                        "importance" to defaultImportance,
                    ),
                )
            }
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            messenger.showError(exception)
        } finally {
            loaded = true
        }
    }

    val currentPage = page
    val selectedProjectId = state.string("project_id").toLongOrNull()
    val selectedProject = currentPage?.availableProjects?.firstOrNull { it.id == selectedProjectId }
    val userPool = selectedProject?.members ?: currentPage?.availableUsers.orEmpty()
    val allowedUserIds = userPool.map { it.id.toString() }.toSet()
    val assigneeValue = state.string("assignee_user_id")
    val hasParent = state.string("parent_task_id").toLongOrNull() != null

    // Like the web form: an assignee / co-assignees outside the selected project's members are dropped,
    // and the assignee is never also a co-assignee.
    LaunchedEffect(loaded, selectedProjectId, assigneeValue, allowedUserIds) {
        if (!loaded || currentPage == null) return@LaunchedEffect
        if (assigneeValue.isNotEmpty() && assigneeValue !in allowedUserIds) {
            state.set("assignee_user_id", null)
        }
        val coAssignees = state.list("co_assignee_user_ids")
        val cleaned = coAssignees.filter { it in allowedUserIds && it != state.string("assignee_user_id") }
        if (cleaned.size != coAssignees.size) {
            state.set("co_assignee_user_ids", cleaned)
        }
    }

    val archivedSuffix = stringResource(R.string.tasks_archived_suffix)
    val knownProjectOptions = listOf(Option(NO_PROJECT, stringResource(R.string.tasks_group_standalone))) +
        currentPage?.availableProjects.orEmpty().map { project ->
            Option(project.id.toString(), if (project.isArchived) project.name + archivedSuffix else project.name)
        }
    val projectOptions = if (selectedProjectId != null && knownProjectOptions.none { it.value == selectedProjectId.toString() }) {
        knownProjectOptions + Option(selectedProjectId.toString(), stringResource(R.string.tasks_project_number, selectedProjectId))
    } else {
        knownProjectOptions
    }
    val parentId = state.string("parent_task_id").toLongOrNull() ?: parentTaskId
    val parentOptions = if (parentId != null) {
        listOf(
            Option(NO_PARENT, stringResource(R.string.tasks_no_parent)),
            Option(parentId.toString(), parentTitle ?: stringResource(R.string.tasks_task_number, parentId)),
        )
    } else {
        null
    }
    val userOptions = userPool.map { user -> Option(user.id.toString(), user.name, user.email) }
    val coAssigneeOptions = userOptions.filter { it.value != assigneeValue }
    val statusOptions = currentPage?.statuses.orEmpty().map { Option(it.value, it.label) }
    val importanceOptions = currentPage?.importances.orEmpty().map { Option(it.value, it.label) }
    val complexityOptions = (currentPage?.complexities ?: (MIN_COMPLEXITY..MAX_COMPLEXITY).toList())
        .map { value -> Option(value.toString(), value.toString()) }

    val specs = buildList<FieldSpec> {
        add(
            FieldSpec.Select(
                "project_id",
                stringResource(R.string.tasks_project),
                projectOptions,
                required = true,
                enabled = !hasParent,
                hint = if (hasParent) stringResource(R.string.tasks_project_follows_parent) else null,
            ),
        )
        if (parentOptions != null) {
            add(FieldSpec.Select("parent_task_id", stringResource(R.string.tasks_parent), parentOptions, required = true))
        }
        add(FieldSpec.Text("title", stringResource(R.string.tasks_task_title), required = true, maxLength = 255))
        add(FieldSpec.Multiline("description", stringResource(R.string.tasks_description), maxLength = 10000))
        add(FieldSpec.Select("status", stringResource(R.string.tasks_status), statusOptions, required = true))
        add(FieldSpec.Select("importance", stringResource(R.string.tasks_importance), importanceOptions, required = true))
        add(FieldSpec.Select("complexity", stringResource(R.string.tasks_complexity), complexityOptions, required = true, numeric = true))
        add(FieldSpec.DateTime("due_at", stringResource(R.string.tasks_due)))
        add(
            FieldSpec.UserPicker(
                "assignee_user_id",
                stringResource(R.string.tasks_assignee),
                users = userOptions,
                hint = if (selectedProject != null) stringResource(R.string.tasks_assignee_hint) else null,
            ),
        )
        add(
            FieldSpec.UserPicker(
                "co_assignee_user_ids",
                stringResource(R.string.tasks_co_assignees),
                users = coAssigneeOptions,
                multiple = true,
                hint = stringResource(R.string.tasks_co_assignees_hint),
            ),
        )
        add(FieldSpec.Number("sort_order", stringResource(R.string.tasks_sort_order)))
    }
    val savedMessage = stringResource(if (taskId == null) R.string.tasks_task_created else R.string.tasks_task_updated)

    FormScreen(
        title = stringResource(
            when {
                taskId != null -> R.string.tasks_edit_task
                parentTaskId != null -> R.string.tasks_new_subtask
                else -> R.string.tasks_new_task
            },
        ),
        specs = specs,
        state = state,
        isLoading = !loaded,
        successMessage = savedMessage,
        onSubmit = {
            val body = state.toJson(
                specs.filter { it.key !in MANUAL_KEYS },
                extra = mapOf(
                    "project_id" to state.string("project_id").toLongOrNull(),
                    "parent_task_id" to state.string("parent_task_id").toLongOrNull(),
                    "due_at" to formDateTimeToIso(state.string("due_at")),
                    "sort_order" to (state.string("sort_order").trim().toLongOrNull()?.coerceAtLeast(0L) ?: 0L),
                ),
            )
            if (taskId == null) {
                val createdId = repository.createTask(body)
                navigator.replace(TasksRoutes.task(createdId))
            } else {
                repository.updateTask(taskId, body)
                navigator.back()
            }
        },
        onSuccess = {},
    )
}
