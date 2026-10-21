package com.appswebnetkz.crm369.feature.conferences

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
 * the public API: [ConferencesRoutes], the route constants and `fun NavGraphBuilder.conferencesGraph()`.
 */

object ConferencesRoutes {
    const val LIST = "conferences"
    const val DETAIL = "conferences/{conferenceId}"

    fun detail(conferenceId: Long): String = "conferences/$conferenceId"
}

fun NavGraphBuilder.conferencesGraph() {
    composable(ConferencesRoutes.LIST) {
        WebPortalScreen(path = "/conferences", title = stringResource(R.string.module_conferences))
    }
    composable(
        route = ConferencesRoutes.DETAIL,
        arguments = listOf(
            navArgument("conferenceId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/conferences/" + entry.longArg("conferenceId"), title = stringResource(R.string.module_conferences))
    }
}
