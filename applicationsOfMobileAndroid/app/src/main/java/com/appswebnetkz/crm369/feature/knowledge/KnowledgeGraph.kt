package com.appswebnetkz.crm369.feature.knowledge

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg
import com.appswebnetkz.crm369.ui.navigation.optionalLongArg

/**
 * Routes of the knowledge bases module. Ids are `LongType`, so the literal `new` segments do not
 * collide with `{baseId}` / `{articleId}`.
 */
object KnowledgeRoutes {
    const val LIST = "knowledge"
    const val BASE = "knowledge/{baseId}"
    const val ARTICLE = "knowledge/{baseId}/articles/{articleId}"
    const val NEW_BASE = "knowledge/new"
    const val EDIT_BASE = "knowledge/{baseId}/edit"
    const val NEW_ARTICLE = "knowledge/{baseId}/articles/new?parentId={parentId}"
    const val EDIT_ARTICLE = "knowledge/{baseId}/articles/{articleId}/edit"

    fun base(baseId: Long): String = "knowledge/$baseId"
    fun article(baseId: Long, articleId: Long): String = "knowledge/$baseId/articles/$articleId"
    fun editBase(baseId: Long): String = "knowledge/$baseId/edit"
    fun newArticle(baseId: Long, parentId: Long? = null): String =
        if (parentId == null) "knowledge/$baseId/articles/new" else "knowledge/$baseId/articles/new?parentId=$parentId"
    fun editArticle(baseId: Long, articleId: Long): String = "knowledge/$baseId/articles/$articleId/edit"
}

fun NavGraphBuilder.knowledgeGraph() {
    composable(KnowledgeRoutes.LIST) { KnowledgeListScreen() }
    composable(KnowledgeRoutes.NEW_BASE) { KnowledgeBaseFormScreen(baseId = null) }
    composable(
        route = KnowledgeRoutes.BASE,
        arguments = listOf(navArgument("baseId") { type = NavType.LongType }),
    ) { entry ->
        KnowledgeBaseScreen(baseId = entry.longArg("baseId"))
    }
    composable(
        route = KnowledgeRoutes.EDIT_BASE,
        arguments = listOf(navArgument("baseId") { type = NavType.LongType }),
    ) { entry ->
        KnowledgeBaseFormScreen(baseId = entry.longArg("baseId"))
    }
    composable(
        route = KnowledgeRoutes.ARTICLE,
        arguments = listOf(
            navArgument("baseId") { type = NavType.LongType },
            navArgument("articleId") { type = NavType.LongType },
        ),
    ) { entry ->
        KnowledgeArticleScreen(baseId = entry.longArg("baseId"), articleId = entry.longArg("articleId"))
    }
    composable(
        route = KnowledgeRoutes.NEW_ARTICLE,
        arguments = listOf(
            navArgument("baseId") { type = NavType.LongType },
            navArgument("parentId") {
                type = NavType.StringType
                nullable = true
                defaultValue = null
            },
        ),
    ) { entry ->
        KnowledgeArticleFormScreen(
            baseId = entry.longArg("baseId"),
            articleId = null,
            parentId = entry.optionalLongArg("parentId"),
        )
    }
    composable(
        route = KnowledgeRoutes.EDIT_ARTICLE,
        arguments = listOf(
            navArgument("baseId") { type = NavType.LongType },
            navArgument("articleId") { type = NavType.LongType },
        ),
    ) { entry ->
        KnowledgeArticleFormScreen(
            baseId = entry.longArg("baseId"),
            articleId = entry.longArg("articleId"),
            parentId = null,
        )
    }
}
