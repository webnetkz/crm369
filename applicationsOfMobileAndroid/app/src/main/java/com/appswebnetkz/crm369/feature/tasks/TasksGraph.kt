package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.navigation.longArg
import com.appswebnetkz.crm369.ui.web.WebPortalScreen

/*
 * STUB: temporary web fallback. The native implementation replaces this file and keeps
 * the public API: [TasksRoutes], [TasksHomeScreen] and `fun NavGraphBuilder.tasksGraph()`.
 */

object TasksRoutes {
    const val TASKS = "tasks"
    const val NEW_TASK = "tasks/new?projectId={projectId}"
    const val TASK = "tasks/{taskId}"
    const val EDIT_TASK = "tasks/{taskId}/edit"
    const val PROJECTS = "projects"
    const val WORKSPACE = "projects/workspace"
    const val NEW_PROJECT = "projects/new"
    const val PROJECT = "projects/{projectId}"
    const val EDIT_PROJECT = "projects/{projectId}/edit"

    fun task(taskId: Long): String = "tasks/$taskId"
    fun newTask(projectId: Long? = null): String = if (projectId == null) "tasks/new" else "tasks/new?projectId=$projectId"
    fun editTask(taskId: Long): String = "tasks/$taskId/edit"
    fun project(projectId: Long): String = "projects/$projectId"
    fun editProject(projectId: Long): String = "projects/$projectId/edit"
}

/** Content of the main screen tab. */
@Composable
fun TasksHomeScreen() {
    WebPortalScreen(path = "/tasks", title = stringResource(R.string.module_tasks))
}

fun NavGraphBuilder.tasksGraph() {
    composable(TasksRoutes.TASKS) { TasksHomeScreen() }
    composable(
        route = TasksRoutes.NEW_TASK,
        arguments = listOf(
            navArgument("projectId") {
                type = NavType.StringType
                nullable = true
                defaultValue = null
            },
        ),
    ) {
        WebPortalScreen(path = "/tasks", title = stringResource(R.string.module_tasks))
    }
    composable(
        route = TasksRoutes.TASK,
        arguments = listOf(
            navArgument("taskId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/tasks/" + entry.longArg("taskId"), title = stringResource(R.string.module_tasks))
    }
    composable(
        route = TasksRoutes.EDIT_TASK,
        arguments = listOf(
            navArgument("taskId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/tasks/" + entry.longArg("taskId"), title = stringResource(R.string.module_tasks))
    }
    composable(TasksRoutes.PROJECTS) {
        WebPortalScreen(path = "/projects", title = stringResource(R.string.module_tasks))
    }
    composable(TasksRoutes.WORKSPACE) {
        WebPortalScreen(path = "/projects", title = stringResource(R.string.module_tasks))
    }
    composable(TasksRoutes.NEW_PROJECT) {
        WebPortalScreen(path = "/projects", title = stringResource(R.string.module_tasks))
    }
    composable(
        route = TasksRoutes.PROJECT,
        arguments = listOf(
            navArgument("projectId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/projects/" + entry.longArg("projectId"), title = stringResource(R.string.module_tasks))
    }
    composable(
        route = TasksRoutes.EDIT_PROJECT,
        arguments = listOf(
            navArgument("projectId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/projects/" + entry.longArg("projectId"), title = stringResource(R.string.module_tasks))
    }
}
