package com.appswebnetkz.crm369.feature.funnels

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
 * the public API: [FunnelsRoutes], [FunnelsHomeScreen] and `fun NavGraphBuilder.funnelsGraph()`.
 */

object FunnelsRoutes {
    const val HOME = "funnels"
    const val BOARD = "funnels/{funnelId}"
    const val NEW_DEAL = "funnels/{funnelId}/deals/new?stageId={stageId}"
    const val DEAL = "funnels/{funnelId}/deals/{dealId}"
    const val EDIT_DEAL = "funnels/{funnelId}/deals/{dealId}/edit"

    fun board(funnelId: Long): String = "funnels/$funnelId"
    fun newDeal(funnelId: Long, stageId: Long? = null): String = if (stageId == null) "funnels/$funnelId/deals/new" else "funnels/$funnelId/deals/new?stageId=$stageId"
    fun deal(funnelId: Long, dealId: Long): String = "funnels/$funnelId/deals/$dealId"
    fun editDeal(funnelId: Long, dealId: Long): String = "funnels/$funnelId/deals/$dealId/edit"
}

/** Content of the main screen tab. */
@Composable
fun FunnelsHomeScreen() {
    WebPortalScreen(path = "/funnels", title = stringResource(R.string.module_funnels))
}

fun NavGraphBuilder.funnelsGraph() {
    composable(FunnelsRoutes.HOME) { FunnelsHomeScreen() }
    composable(
        route = FunnelsRoutes.BOARD,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/funnels/" + entry.longArg("funnelId"), title = stringResource(R.string.module_funnels))
    }
    composable(
        route = FunnelsRoutes.NEW_DEAL,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
            navArgument("stageId") {
                type = NavType.StringType
                nullable = true
                defaultValue = null
            },
        ),
    ) { entry ->
        WebPortalScreen(path = "/funnels/" + entry.longArg("funnelId"), title = stringResource(R.string.module_funnels))
    }
    composable(
        route = FunnelsRoutes.DEAL,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
            navArgument("dealId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/funnels/" + entry.longArg("funnelId"), title = stringResource(R.string.module_funnels))
    }
    composable(
        route = FunnelsRoutes.EDIT_DEAL,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
            navArgument("dealId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/funnels/" + entry.longArg("funnelId"), title = stringResource(R.string.module_funnels))
    }
}
