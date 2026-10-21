package com.appswebnetkz.crm369.feature.chats

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/** Routes of the chats module. `ChatsListScreen()` (ChatsListScreen.kt) is the main "Chats" tab. */
object ChatsRoutes {
    const val LIST = "chats"
    const val NEW = "chats/new"
    const val CONVERSATION = "chats/{conversationId}"
    const val USER = "chats/users/{userId}"

    fun conversation(conversationId: Long): String = "chats/$conversationId"
    fun user(userId: Long): String = "chats/users/$userId"
}

fun NavGraphBuilder.chatsGraph() {
    composable(ChatsRoutes.LIST) { ChatsListScreen() }
    composable(ChatsRoutes.NEW) { ChatNewScreen() }
    composable(
        route = ChatsRoutes.CONVERSATION,
        arguments = listOf(navArgument("conversationId") { type = NavType.LongType }),
    ) { entry ->
        ChatConversationScreen(conversationId = entry.longArg("conversationId"))
    }
    composable(
        route = ChatsRoutes.USER,
        arguments = listOf(navArgument("userId") { type = NavType.LongType }),
    ) { entry ->
        ChatUserProfileScreen(userId = entry.longArg("userId"))
    }
}
