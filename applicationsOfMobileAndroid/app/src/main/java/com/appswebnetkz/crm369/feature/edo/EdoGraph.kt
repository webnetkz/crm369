package com.appswebnetkz.crm369.feature.edo

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
 * the public API: [EdoRoutes], the route constants and `fun NavGraphBuilder.edoGraph()`.
 */

object EdoRoutes {
    const val LIST = "edo"
    const val NEW = "edo/new"
    const val DOCUMENT = "edo/{documentId}"

    fun document(documentId: Long): String = "edo/$documentId"
}

fun NavGraphBuilder.edoGraph() {
    composable(EdoRoutes.LIST) {
        WebPortalScreen(path = "/edo", title = stringResource(R.string.module_edo))
    }
    composable(EdoRoutes.NEW) {
        WebPortalScreen(path = "/edo", title = stringResource(R.string.module_edo))
    }
    composable(
        route = EdoRoutes.DOCUMENT,
        arguments = listOf(
            navArgument("documentId") { type = NavType.LongType },
        ),
    ) {
        WebPortalScreen(path = "/edo", title = stringResource(R.string.module_edo))
    }
}
