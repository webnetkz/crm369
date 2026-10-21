package com.appswebnetkz.crm369.feature.directories

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/** Routes of the reference directories module. */
object DirectoriesRoutes {
    const val LIST = "directories"
    const val DETAIL = "directories/{directoryId}"
    const val NEW = "directories/new"
    const val EDIT = "directories/{directoryId}/edit"
    const val NEW_RECORD = "directories/{directoryId}/records/new"
    const val EDIT_RECORD = "directories/{directoryId}/records/{recordId}"

    fun detail(directoryId: Long): String = "directories/$directoryId"
    fun edit(directoryId: Long): String = "directories/$directoryId/edit"
    fun newRecord(directoryId: Long): String = "directories/$directoryId/records/new"
    fun editRecord(directoryId: Long, recordId: Long): String = "directories/$directoryId/records/$recordId"
}

fun NavGraphBuilder.directoriesGraph() {
    composable(DirectoriesRoutes.LIST) { DirectoriesListScreen() }
    composable(DirectoriesRoutes.NEW) { DirectoryFormScreen(directoryId = null) }
    composable(
        route = DirectoriesRoutes.DETAIL,
        arguments = listOf(navArgument("directoryId") { type = NavType.LongType }),
    ) { entry ->
        DirectoryDetailScreen(directoryId = entry.longArg("directoryId"))
    }
    composable(
        route = DirectoriesRoutes.EDIT,
        arguments = listOf(navArgument("directoryId") { type = NavType.LongType }),
    ) { entry ->
        DirectoryFormScreen(directoryId = entry.longArg("directoryId"))
    }
    composable(
        route = DirectoriesRoutes.NEW_RECORD,
        arguments = listOf(navArgument("directoryId") { type = NavType.LongType }),
    ) { entry ->
        DirectoryRecordFormScreen(directoryId = entry.longArg("directoryId"), recordId = null)
    }
    composable(
        route = DirectoriesRoutes.EDIT_RECORD,
        arguments = listOf(
            navArgument("directoryId") { type = NavType.LongType },
            navArgument("recordId") { type = NavType.LongType },
        ),
    ) { entry ->
        DirectoryRecordFormScreen(directoryId = entry.longArg("directoryId"), recordId = entry.longArg("recordId"))
    }
}
