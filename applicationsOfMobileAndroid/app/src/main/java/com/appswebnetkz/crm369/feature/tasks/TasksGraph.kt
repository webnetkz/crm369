package com.appswebnetkz.crm369.feature.tasks

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg
import com.appswebnetkz.crm369.ui.navigation.optionalLongArg

/** Routes of the tasks module (tasks, projects, kanban stages, task discussions). */
object TasksRoutes {
    const val TASKS = "tasks"
    const val NEW_TASK = "tasks/new?projectId={projectId}"

    /**
     * [NEW_TASK] extended with the optional parent task (new subtask). This pattern is the one
     * registered in the graph; it matches every URL built for [NEW_TASK] as well.
     */
    const val NEW_TASK_WITH_PARENT = "tasks/new?projectId={projectId}&parentTaskId={parentTaskId}"
    const val TASK = "tasks/{taskId}"
    const val EDIT_TASK = "tasks/{taskId}/edit"
    const val DISCUSSION = "tasks/{taskId}/discussion"
    const val STAGES = "tasks/stages"
    const val PROJECTS = "projects"
    const val WORKSPACE = "projects/workspace"
    const val NEW_PROJECT = "projects/new"
    const val PROJECT = "projects/{projectId}"
    const val EDIT_PROJECT = "projects/{projectId}/edit"

    fun task(taskId: Long): String = "tasks/$taskId"

    fun newTask(projectId: Long? = null, parentTaskId: Long? = null): String {
        val query = listOfNotNull(
            projectId?.let { id -> "projectId=$id" },
            parentTaskId?.let { id -> "parentTaskId=$id" },
        )
        return if (query.isEmpty()) "tasks/new" else "tasks/new?" + query.joinToString("&")
    }

    fun editTask(taskId: Long): String = "tasks/$taskId/edit"
    fun discussion(taskId: Long): String = "tasks/$taskId/discussion"
    fun project(projectId: Long): String = "projects/$projectId"
    fun editProject(projectId: Long): String = "projects/$projectId/edit"
}

fun NavGraphBuilder.tasksGraph() {
    composable(TasksRoutes.TASKS) { TasksHomeScreen() }
    composable(TasksRoutes.WORKSPACE) { TasksHomeScreen() }
    composable(TasksRoutes.STAGES) { TaskStagesScreen() }
    composable(
        route = TasksRoutes.NEW_TASK_WITH_PARENT,
        arguments = listOf(
            navArgument("projectId") {
                type = NavType.StringType
                nullable = true
                defaultValue = null
            },
            navArgument("parentTaskId") {
                type = NavType.StringType
                nullable = true
                defaultValue = null
            },
        ),
    ) { entry ->
        TaskFormScreen(
            taskId = null,
            projectId = entry.optionalLongArg("projectId"),
            parentTaskId = entry.optionalLongArg("parentTaskId"),
        )
    }
    composable(
        route = TasksRoutes.TASK,
        arguments = listOf(navArgument("taskId") { type = NavType.LongType }),
    ) { entry ->
        TaskDetailScreen(taskId = entry.longArg("taskId"))
    }
    composable(
        route = TasksRoutes.EDIT_TASK,
        arguments = listOf(navArgument("taskId") { type = NavType.LongType }),
    ) { entry ->
        TaskFormScreen(taskId = entry.longArg("taskId"), projectId = null, parentTaskId = null)
    }
    composable(
        route = TasksRoutes.DISCUSSION,
        arguments = listOf(navArgument("taskId") { type = NavType.LongType }),
    ) { entry ->
        TaskDiscussionScreen(taskId = entry.longArg("taskId"))
    }
    composable(TasksRoutes.PROJECTS) { ProjectsListScreen() }
    composable(TasksRoutes.NEW_PROJECT) { ProjectFormScreen(projectId = null) }
    composable(
        route = TasksRoutes.PROJECT,
        arguments = listOf(navArgument("projectId") { type = NavType.LongType }),
    ) { entry ->
        ProjectDetailScreen(projectId = entry.longArg("projectId"))
    }
    composable(
        route = TasksRoutes.EDIT_PROJECT,
        arguments = listOf(navArgument("projectId") { type = NavType.LongType }),
    ) { entry ->
        ProjectFormScreen(projectId = entry.longArg("projectId"))
    }
}
