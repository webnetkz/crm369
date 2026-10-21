package com.appswebnetkz.crm369.feature.funnels

import androidx.compose.runtime.Composable
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg
import com.appswebnetkz.crm369.ui.navigation.optionalLongArg

/** Routes of the funnels (CRM deals) module. */
object FunnelsRoutes {
    const val HOME = "funnels"
    const val BOARD = "funnels/{funnelId}"
    const val NEW_DEAL = "funnels/{funnelId}/deals/new?stageId={stageId}"
    const val DEAL = "funnels/{funnelId}/deals/{dealId}"
    const val EDIT_DEAL = "funnels/{funnelId}/deals/{dealId}/edit"

    /** Funnel management (`can.manageFunnels`). `funnels/new` wins over BOARD: `funnelId` is a Long. */
    const val NEW_FUNNEL = "funnels/new"
    const val EDIT_FUNNEL = "funnels/{funnelId}/edit"
    const val SETTINGS = "funnels/{funnelId}/settings"
    const val NEW_STAGE = "funnels/{funnelId}/stages/new"
    const val EDIT_STAGE = "funnels/{funnelId}/stages/{stageId}/edit"

    fun board(funnelId: Long): String = "funnels/$funnelId"
    fun newDeal(funnelId: Long, stageId: Long? = null): String = if (stageId == null) "funnels/$funnelId/deals/new" else "funnels/$funnelId/deals/new?stageId=$stageId"
    fun deal(funnelId: Long, dealId: Long): String = "funnels/$funnelId/deals/$dealId"
    fun editDeal(funnelId: Long, dealId: Long): String = "funnels/$funnelId/deals/$dealId/edit"
    fun editFunnel(funnelId: Long): String = "funnels/$funnelId/edit"
    fun settings(funnelId: Long): String = "funnels/$funnelId/settings"
    fun newStage(funnelId: Long): String = "funnels/$funnelId/stages/new"
    fun editStage(funnelId: Long, stageId: Long): String = "funnels/$funnelId/stages/$stageId/edit"
}

/** Content of the main screen tab (CRM): the board of the first visible funnel. */
@Composable
fun FunnelsHomeScreen() {
    FunnelBoardScreen(funnelId = null)
}

fun NavGraphBuilder.funnelsGraph() {
    composable(FunnelsRoutes.HOME) { FunnelsHomeScreen() }
    composable(FunnelsRoutes.NEW_FUNNEL) { FunnelFormScreen(funnelId = null) }
    composable(
        route = FunnelsRoutes.BOARD,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
        ),
    ) { entry ->
        FunnelBoardScreen(funnelId = entry.longArg("funnelId"))
    }
    composable(
        route = FunnelsRoutes.EDIT_FUNNEL,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
        ),
    ) { entry ->
        FunnelFormScreen(funnelId = entry.longArg("funnelId"))
    }
    composable(
        route = FunnelsRoutes.SETTINGS,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
        ),
    ) { entry ->
        FunnelSettingsScreen(funnelId = entry.longArg("funnelId"))
    }
    composable(
        route = FunnelsRoutes.NEW_STAGE,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
        ),
    ) { entry ->
        StageFormScreen(funnelId = entry.longArg("funnelId"), stageId = null)
    }
    composable(
        route = FunnelsRoutes.EDIT_STAGE,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
            navArgument("stageId") { type = NavType.LongType },
        ),
    ) { entry ->
        StageFormScreen(funnelId = entry.longArg("funnelId"), stageId = entry.longArg("stageId"))
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
        DealFormScreen(
            funnelId = entry.longArg("funnelId"),
            dealId = null,
            initialStageId = entry.optionalLongArg("stageId"),
        )
    }
    composable(
        route = FunnelsRoutes.DEAL,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
            navArgument("dealId") { type = NavType.LongType },
        ),
    ) { entry ->
        DealDetailScreen(funnelId = entry.longArg("funnelId"), dealId = entry.longArg("dealId"))
    }
    composable(
        route = FunnelsRoutes.EDIT_DEAL,
        arguments = listOf(
            navArgument("funnelId") { type = NavType.LongType },
            navArgument("dealId") { type = NavType.LongType },
        ),
    ) { entry ->
        DealFormScreen(
            funnelId = entry.longArg("funnelId"),
            dealId = entry.longArg("dealId"),
            initialStageId = null,
        )
    }
}
