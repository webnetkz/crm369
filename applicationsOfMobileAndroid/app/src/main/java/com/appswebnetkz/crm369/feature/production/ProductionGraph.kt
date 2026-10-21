package com.appswebnetkz.crm369.feature.production

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.core.links.RouteArgs
import com.appswebnetkz.crm369.ui.navigation.stringArg

/** Routes of the production module. */
object ProductionRoutes {
    const val ROOT = "production"
    const val SECTION = "production/{section}"

    fun section(section: String): String = "production/${RouteArgs.encode(section)}"
}

fun NavGraphBuilder.productionGraph() {
    composable(ProductionRoutes.ROOT) { ProductionScreen(initialSection = null) }
    composable(
        route = ProductionRoutes.SECTION,
        arguments = listOf(navArgument("section") { type = NavType.StringType }),
    ) { entry ->
        ProductionScreen(initialSection = entry.stringArg("section"))
    }
}
