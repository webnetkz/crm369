package com.appswebnetkz.crm369.feature.chats

import android.content.Context
import android.net.Uri
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.core.network.UploadPart

/** All chat API calls (`/api/mobile/v1/chats`). Models: `ChatsModels.kt`. */
class ChatsRepository(private val api: ApiClient) {

    /** Conversations (general first), people to start a chat with and the total unread counter. */
    suspend fun index(search: String? = null): ChatsIndex =
        ChatsIndex.fromJson(
            api.get("chats", mapOf("search" to search?.trim()?.takeIf { it.isNotEmpty() })),
        )

    /** Opens a conversation with its full history. Opening marks it read on the server. */
    suspend fun conversation(conversationId: Long): OpenedConversation {
        val index = ChatsIndex.fromJson(api.get("chats", mapOf("conversation" to conversationId)))
        val active = index.active
        if (active == null || active.id != conversationId) {
            throw ApiException(status = 404, message = "Not Found")
        }
        return OpenedConversation(conversation = active, unreadCount = index.unreadCount)
    }

    /** Returns the id of the (existing or new) direct conversation with [userId]. */
    suspend fun openDirect(userId: Long): Long {
        val response = api.post("chats/direct", jsonObjectOf("user_id" to userId))
        return response.dataObject().long("conversation_id")
            ?: throw ApiException(status = 404, message = "Not Found")
    }

    /** Sends a text message; with [attachments] (content Uris) the request becomes multipart. */
    suspend fun send(context: Context, conversationId: Long, body: String, attachments: List<Uri>): ChatMessage {
        val path = "chats/$conversationId/messages"
        val response = if (attachments.isEmpty()) {
            api.post(path, jsonObjectOf("body" to body))
        } else {
            api.multipart(
                path,
                mapOf("body" to body),
                attachments.map { uri -> UploadPart.fromUri(context, "attachments[]", uri) },
            )
        }
        return ChatMessage.fromJson(response.dataObject())
    }

    suspend fun edit(conversationId: Long, messageId: Long, body: String): ChatMessage =
        ChatMessage.fromJson(
            api.patch("chats/$conversationId/messages/$messageId", jsonObjectOf("body" to body)).dataObject(),
        )

    suspend fun delete(conversationId: Long, messageId: Long) {
        api.delete("chats/$conversationId/messages/$messageId")
    }

    suspend fun setPinned(conversationId: Long, messageId: Long, pinned: Boolean): ChatMessage {
        val path = "chats/$conversationId/messages/$messageId/pin"
        val response = if (pinned) api.patch(path) else api.delete(path)
        return ChatMessage.fromJson(response.dataObject())
    }

    suspend fun profile(userId: Long): ChatProfile =
        ChatProfile.fromJson(api.get("chats/users/$userId/profile").dataObject())
}
