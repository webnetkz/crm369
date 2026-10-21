package com.appswebnetkz.crm369.feature.news

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.core.links.RouteArgs
import com.appswebnetkz.crm369.ui.navigation.stringArg

/**
 * Routes of the news module. The route key of a news item is its slug (encoded with
 * [RouteArgs.encode]); `news/new` wins over `news/{slug}` because literal routes are exact matches.
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
    composable(NewsRoutes.LIST) { NewsListScreen() }
    composable(NewsRoutes.NEW) { NewsFormScreen(slug = null) }
    composable(
        route = NewsRoutes.DETAIL,
        arguments = listOf(navArgument("slug") { type = NavType.StringType }),
    ) { entry ->
        NewsDetailScreen(slug = entry.stringArg("slug").orEmpty())
    }
    composable(
        route = NewsRoutes.EDIT,
        arguments = listOf(navArgument("slug") { type = NavType.StringType }),
    ) { entry ->
        NewsFormScreen(slug = entry.stringArg("slug").orEmpty())
    }
}
