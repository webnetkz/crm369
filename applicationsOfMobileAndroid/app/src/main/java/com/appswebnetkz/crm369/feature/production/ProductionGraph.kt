package com.appswebnetkz.crm369.feature.production

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.links.RouteArgs
import com.appswebnetkz.crm369.ui.navigation.stringArg
import com.appswebnetkz.crm369.ui.web.WebPortalScreen

/*
 * STUB: temporary web fallback. The native implementation replaces this file and keeps
 * the public API: [ProductionRoutes], the route constants and `fun NavGraphBuilder.productionGraph()`.
 */

object ProductionRoutes {
    const val ROOT = "production"
    const val SECTION = "production/{section}"

    fun section(section: String): String = "production/${RouteArgs.encode(section)}"
}

fun NavGraphBuilder.productionGraph() {
    composable(ProductionRoutes.ROOT) {
        WebPortalScreen(path = "/production", title = stringResource(R.string.module_production))
    }
    composable(
        route = ProductionRoutes.SECTION,
        arguments = listOf(
            navArgument("section") { type = NavType.StringType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/production/" + RouteArgs.encode(entry.stringArg("section").orEmpty()), title = stringResource(R.string.module_production))
    }
}
