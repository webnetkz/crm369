package com.appswebnetkz.crm369.feature.files

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.web.WebPortalScreen

/*
 * STUB: temporary web fallback. The native implementation replaces this file and keeps
 * the public API: [FilesRoutes], the route constants and `fun NavGraphBuilder.filesGraph()`.
 */

object FilesRoutes {
    const val ROOT = "files?directoryId={directoryId}"

    fun directory(directoryId: Long? = null): String = if (directoryId == null) "files" else "files?directoryId=$directoryId"
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
    ) {
        WebPortalScreen(path = "/files", title = stringResource(R.string.module_files))
    }
}
