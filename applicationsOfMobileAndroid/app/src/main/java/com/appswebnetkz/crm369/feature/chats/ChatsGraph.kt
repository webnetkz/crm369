package com.appswebnetkz.crm369.feature.chats

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
 * the public API: [ChatsRoutes], [ChatsListScreen] and `fun NavGraphBuilder.chatsGraph()`.
 */

object ChatsRoutes {
    const val LIST = "chats"
    const val NEW = "chats/new"
    const val CONVERSATION = "chats/{conversationId}"
    const val USER = "chats/users/{userId}"

    fun conversation(conversationId: Long): String = "chats/$conversationId"
    fun user(userId: Long): String = "chats/users/$userId"
}

/** Content of the main screen tab. */
@Composable
fun ChatsListScreen() {
    WebPortalScreen(path = "/chats", title = stringResource(R.string.module_chats))
}

fun NavGraphBuilder.chatsGraph() {
    composable(ChatsRoutes.LIST) { ChatsListScreen() }
    composable(ChatsRoutes.NEW) {
        WebPortalScreen(path = "/chats", title = stringResource(R.string.module_chats))
    }
    composable(
        route = ChatsRoutes.CONVERSATION,
        arguments = listOf(
            navArgument("conversationId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/chats?conversation=" + entry.longArg("conversationId"), title = stringResource(R.string.module_chats))
    }
    composable(
        route = ChatsRoutes.USER,
        arguments = listOf(
            navArgument("userId") { type = NavType.LongType },
        ),
    ) {
        WebPortalScreen(path = "/chats", title = stringResource(R.string.module_chats))
    }
}
