package com.appswebnetkz.crm369.feature.knowledge

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
 * the public API: [KnowledgeRoutes], the route constants and `fun NavGraphBuilder.knowledgeGraph()`.
 */

object KnowledgeRoutes {
    const val LIST = "knowledge"
    const val BASE = "knowledge/{baseId}"
    const val ARTICLE = "knowledge/{baseId}/articles/{articleId}"

    fun base(baseId: Long): String = "knowledge/$baseId"
    fun article(baseId: Long, articleId: Long): String = "knowledge/$baseId/articles/$articleId"
}

fun NavGraphBuilder.knowledgeGraph() {
    composable(KnowledgeRoutes.LIST) {
        WebPortalScreen(path = "/knowledge-bases", title = stringResource(R.string.module_knowledge_bases))
    }
    composable(
        route = KnowledgeRoutes.BASE,
        arguments = listOf(
            navArgument("baseId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/knowledge-bases/" + entry.longArg("baseId"), title = stringResource(R.string.module_knowledge_bases))
    }
    composable(
        route = KnowledgeRoutes.ARTICLE,
        arguments = listOf(
            navArgument("baseId") { type = NavType.LongType },
            navArgument("articleId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/knowledge-bases/" + entry.longArg("baseId") + "/articles/" + entry.longArg("articleId"), title = stringResource(R.string.module_knowledge_bases))
    }
}
