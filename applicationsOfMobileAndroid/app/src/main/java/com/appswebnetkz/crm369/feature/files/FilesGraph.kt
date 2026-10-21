package com.appswebnetkz.crm369.feature.files

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg
import com.appswebnetkz.crm369.ui.navigation.optionalLongArg

/**
 * Routes of the file storage. `directoryId` is an optional String query argument: without it the
 * screen lists the root folders.
 */
object FilesRoutes {
    const val ROOT = "files?directoryId={directoryId}"
    const val ACCESS = "files/{directoryId}/access"

    fun directory(directoryId: Long? = null): String = if (directoryId == null) "files" else "files?directoryId=$directoryId"
    fun access(directoryId: Long): String = "files/$directoryId/access"
}

fun NavGraphBuilder.filesGraph() {
    composable(
        route = FilesRoutes.ROOT,
        arguments = listOf(
            navArgument("directoryId") {
                type = NavType.StringType
                nullable = true
                defaultValue = null
            },
        ),
    ) { entry ->
        FilesScreen(directoryId = entry.optionalLongArg("directoryId"))
    }
    composable(
        route = FilesRoutes.ACCESS,
        arguments = listOf(navArgument("directoryId") { type = NavType.LongType }),
    ) { entry ->
        FilesAccessScreen(directoryId = entry.longArg("directoryId"))
    }
}
