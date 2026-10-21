package com.appswebnetkz.crm369.feature.conferences

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/**
 * Routes of the conferences module. The list, details and creation are native; the meeting room
 * itself (WebRTC) opens in the authenticated web view via `navigator.openWeb(web_path)`.
 */
object ConferencesRoutes {
    const val LIST = "conferences"
    const val NEW = "conferences/new"
    const val DETAIL = "conferences/{conferenceId}"

    fun detail(conferenceId: Long): String = "conferences/$conferenceId"
}

fun NavGraphBuilder.conferencesGraph() {
    composable(ConferencesRoutes.LIST) { ConferencesListScreen() }
    composable(ConferencesRoutes.NEW) { ConferenceFormScreen() }
    composable(
        route = ConferencesRoutes.DETAIL,
        arguments = listOf(navArgument("conferenceId") { type = NavType.LongType }),
    ) { entry ->
        ConferenceDetailScreen(conferenceId = entry.longArg("conferenceId"))
    }
}
