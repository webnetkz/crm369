package com.appswebnetkz.crm369.feature.directories

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
 * the public API: [DirectoriesRoutes], the route constants and `fun NavGraphBuilder.directoriesGraph()`.
 */

object DirectoriesRoutes {
    const val LIST = "directories"
    const val DETAIL = "directories/{directoryId}"

    fun detail(directoryId: Long): String = "directories/$directoryId"
}

fun NavGraphBuilder.directoriesGraph() {
    composable(DirectoriesRoutes.LIST) {
        WebPortalScreen(path = "/directories", title = stringResource(R.string.module_directories))
    }
    composable(
        route = DirectoriesRoutes.DETAIL,
        arguments = listOf(
            navArgument("directoryId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/directories/" + entry.longArg("directoryId"), title = stringResource(R.string.module_directories))
    }
}
