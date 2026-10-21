package com.appswebnetkz.crm369.feature.news

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
 * the public API: [NewsRoutes], the route constants and `fun NavGraphBuilder.newsGraph()`.
 */

object NewsRoutes {
    const val LIST = "news"
    const val NEW = "news/new"
    const val DETAIL = "news/{slug}"
    const val EDIT = "news/{slug}/edit"

    fun detail(slug: String): String = "news/${RouteArgs.encode(slug)}"
    fun edit(slug: String): String = "news/${RouteArgs.encode(slug)}/edit"
}

fun NavGraphBuilder.newsGraph() {
    composable(NewsRoutes.LIST) {
        WebPortalScreen(path = "/news", title = stringResource(R.string.module_news))
    }
    composable(NewsRoutes.NEW) {
        WebPortalScreen(path = "/news", title = stringResource(R.string.module_news))
    }
    composable(
        route = NewsRoutes.DETAIL,
        arguments = listOf(
            navArgument("slug") { type = NavType.StringType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/news/" + RouteArgs.encode(entry.stringArg("slug").orEmpty()), title = stringResource(R.string.module_news))
    }
    composable(
        route = NewsRoutes.EDIT,
        arguments = listOf(
            navArgument("slug") { type = NavType.StringType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/news/" + RouteArgs.encode(entry.stringArg("slug").orEmpty()), title = stringResource(R.string.module_news))
    }
}
