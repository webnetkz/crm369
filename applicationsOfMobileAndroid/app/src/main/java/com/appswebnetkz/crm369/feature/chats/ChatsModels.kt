package com.appswebnetkz.crm369.feature.chats

import com.appswebnetkz.crm369.core.json.bool
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import kotlinx.serialization.json.JsonObject

/*
 * Models of the chats module. Pure Kotlin (no Android imports) so they can be unit tested.
 * `GET chats` returns camelCase objects, message POST/PATCH/DELETE return snake_case
 * (`ApiChatMessage`); the parsers below accept both shapes.
 */

/** Joins name parts like `Formatters.fullName` (first, middle, last). */
internal fun chatPersonName(name: String?, lastName: String? = null, middleName: String? = null): String =
    listOfNotNull(name, middleName, lastName)
        .map { it.trim() }
        .filter { it.isNotEmpty() }
        .joinToString(" ")

object ChatTypes {
    const val DIRECT = "direct"
    const val GENERAL = "general"
    const val TASK = "task"
}

/** `ChatUser` (camelCase, `name` is the full name) or `ApiUser` (snake_case, separate name parts). */
data class ChatUser(
    val id: Long,
    val name: String,
    val email: String?,
    val phone: String?,
    val avatarUrl: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): ChatUser {
            val email = json.text("email")
            val name = json.text("full_name")
                ?: chatPersonName(json.str("name"), json.str("last_name"), json.str("middle_name")).takeIf { it.isNotEmpty() }
                ?: email.orEmpty()
            return ChatUser(
                id = json.long("id") ?: 0L,
                name = name,
                email = email,
                phone = json.text("phone"),
                avatarUrl = json.text("avatar"),
            )
        }
    }
}

/** `ChatAttachment` (camelCase) or an `ApiChatMessage` attachment (snake_case). */
data class ChatAttachment(
    val id: Long,
    val name: String,
    val mimeType: String?,
    val sizeBytes: Long?,
    val downloadUrl: String?,
    val previewUrl: String?,
    val audioUrl: String?,
) {
    val isAudio: Boolean
        get() = audioUrl != null || mimeType?.startsWith("audio/") == true

    /** Images (except SVG) come with a preview URL. */
    val isImage: Boolean
        get() = previewUrl != null && !isAudio && (mimeType == null || mimeType.startsWith("image/"))

    /** URL used to download/open the file. */
    val openUrl: String?
        get() = downloadUrl ?: audioUrl ?: previewUrl

    companion object {
        fun fromJson(json: JsonObject): ChatAttachment = ChatAttachment(
            id = json.long("id") ?: 0L,
            name = json.text("name") ?: json.text("original_name") ?: "file",
            mimeType = json.text("mimeType") ?: json.text("mime_type"),
            sizeBytes = json.long("sizeBytes") ?: json.long("size_bytes"),
            downloadUrl = json.text("downloadUrl") ?: json.text("download_url"),
            previewUrl = json.text("previewUrl") ?: json.text("preview_url"),
            audioUrl = json.text("audioUrl") ?: json.text("audio_url"),
        )
    }
}

data class ChatReader(val id: Long, val name: String)

/** `ChatMessage` (camelCase) or `ApiChatMessage` (snake_case; no pin/read data there). */
data class ChatMessage(
    val id: Long,
    val body: String,
    val createdAt: String?,
    val pinnedAt: String?,
    val isEdited: Boolean,
    val isDeleted: Boolean,
    val isPinned: Boolean,
    val isOwn: Boolean,
    val isRead: Boolean,
    val readCount: Int,
    val readBy: List<ChatReader>,
    val user: ChatUser?,
    val attachments: List<ChatAttachment>,
) {
    companion object {
        fun fromJson(json: JsonObject): ChatMessage {
            val readBy = json.objects("readBy").map { reader ->
                ChatReader(id = reader.long("id") ?: 0L, name = reader.str("name").orEmpty())
            }
            val pinnedAt = json.text("pinnedAt") ?: json.text("pinned_at")
            return ChatMessage(
                id = json.long("id") ?: 0L,
                body = json.str("body").orEmpty(),
                createdAt = json.text("createdAt") ?: json.text("created_at"),
                pinnedAt = pinnedAt,
                isEdited = json.bool("isEdited") ?: json.bool("is_edited") ?: false,
                isDeleted = json.bool("isDeleted") ?: json.bool("is_deleted") ?: false,
                isPinned = json.bool("isPinned") ?: (pinnedAt != null),
                isOwn = json.bool("isOwn") ?: json.bool("is_own") ?: false,
                isRead = json.bool("isRead") ?: false,
                readCount = json.int("readCount") ?: readBy.size,
                readBy = readBy,
                user = json.obj("user")?.let { ChatUser.fromJson(it) },
                attachments = json.objects("attachments").map { ChatAttachment.fromJson(it) },
            )
        }
    }
}

/** `ConversationListItem`. */
data class ChatConversationItem(
    val id: Long,
    val type: String,
    val title: String,
    val subtitle: String?,
    val excerpt: String?,
    val lastMessageAt: String?,
    val unreadCount: Int,
    val participant: ChatUser?,
) {
    val isGeneral: Boolean get() = type == ChatTypes.GENERAL

    companion object {
        fun fromJson(json: JsonObject): ChatConversationItem = ChatConversationItem(
            id = json.long("id") ?: 0L,
            type = json.str("type") ?: ChatTypes.DIRECT,
            title = json.str("title").orEmpty(),
            subtitle = json.text("subtitle"),
            excerpt = json.text("excerpt"),
            lastMessageAt = json.text("lastMessageAt"),
            unreadCount = json.int("unreadCount") ?: 0,
            participant = json.obj("participant")?.let { ChatUser.fromJson(it) },
        )
    }
}

/** `ActiveConversation`: full history (oldest first) and the pinned messages. */
data class ChatConversation(
    val id: Long,
    val type: String,
    val title: String,
    val subtitle: String?,
    val participant: ChatUser?,
    val participants: List<ChatUser>,
    val messages: List<ChatMessage>,
    val pinnedMessages: List<ChatMessage>,
) {
    val isDirect: Boolean get() = type == ChatTypes.DIRECT

    /** General and task chats show the author of every message group. */
    val isGroup: Boolean get() = !isDirect

    /** The most recently pinned message (shown in the pinned bar). */
    val latestPinned: ChatMessage?
        get() = pinnedMessages.maxByOrNull { it.pinnedAt.orEmpty() }

    fun appendMessage(message: ChatMessage): ChatConversation =
        this.copy(messages = messages.filter { it.id != message.id } + message)

    fun updateMessage(messageId: Long, transform: (ChatMessage) -> ChatMessage): ChatConversation = this.copy(
        messages = messages.map { if (it.id == messageId) transform(it) else it },
        pinnedMessages = pinnedMessages.map { if (it.id == messageId) transform(it) else it },
    )

    companion object {
        fun fromJson(json: JsonObject): ChatConversation = ChatConversation(
            id = json.long("id") ?: 0L,
            type = json.str("type") ?: ChatTypes.DIRECT,
            title = json.str("title").orEmpty(),
            subtitle = json.text("subtitle"),
            participant = json.obj("participant")?.let { ChatUser.fromJson(it) },
            participants = json.objects("participants").map { ChatUser.fromJson(it) },
            messages = json.objects("messages").map { ChatMessage.fromJson(it) }.distinctBy { it.id },
            pinnedMessages = json.objects("pinnedMessages").map { ChatMessage.fromJson(it) }.distinctBy { it.id },
        )
    }
}

/** `GET chats` (not wrapped in `data`). */
data class ChatsIndex(
    val unreadCount: Int,
    val conversations: List<ChatConversationItem>,
    val contacts: List<ChatUser>,
    val active: ChatConversation?,
) {
    companion object {
        fun fromJson(json: JsonObject): ChatsIndex = ChatsIndex(
            unreadCount = json.int("unreadCount") ?: 0,
            conversations = json.objects("conversations")
                .map { ChatConversationItem.fromJson(it) }
                .distinctBy { it.id }
                .sortedBy { if (it.isGeneral) 0 else 1 },
            contacts = json.objects("contacts").map { ChatUser.fromJson(it) }.distinctBy { it.id },
            active = json.obj("activeConversation")?.let { ChatConversation.fromJson(it) },
        )
    }
}

/** An opened conversation together with the fresh total unread counter. */
data class OpenedConversation(
    val conversation: ChatConversation,
    val unreadCount: Int,
)

/** `StructureUser` inside the profile (manager, subordinates). */
data class ChatPerson(
    val id: Long,
    val fullName: String,
    val email: String?,
    val position: String?,
    val avatarUrl: String?,
    val isActive: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject): ChatPerson {
            val email = json.text("email")
            return ChatPerson(
                id = json.long("id") ?: 0L,
                fullName = json.text("full_name")
                    ?: chatPersonName(json.str("name"), json.str("last_name"), json.str("middle_name")).takeIf { it.isNotEmpty() }
                    ?: email.orEmpty(),
                email = email,
                position = json.text("position"),
                avatarUrl = json.text("avatar"),
                isActive = json.boolOr("is_active", true),
            )
        }
    }
}

/** An item of `issued_equipment`. */
data class ChatEquipment(
    val id: Long,
    val name: String,
    val qrCode: String?,
    val statusLabel: String?,
    val responsibleName: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): ChatEquipment {
            val responsible = json.obj("responsible_user")
            return ChatEquipment(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                qrCode = json.text("qr_code"),
                statusLabel = json.text("status_label") ?: json.text("status"),
                responsibleName = responsible?.let { person ->
                    chatPersonName(person.str("name"), person.str("last_name")).takeIf { it.isNotEmpty() } ?: person.text("email")
                },
            )
        }
    }
}

/** `GET chats/users/{id}/profile` → `data` (`Profile`). */
data class ChatProfile(
    val id: Long,
    val fullName: String,
    val email: String?,
    val phone: String?,
    val position: String?,
    val avatarUrl: String?,
    val isActive: Boolean,
    val groupName: String?,
    val manager: ChatPerson?,
    val subordinatesCount: Int,
    val subordinates: List<ChatPerson>,
    val equipment: List<ChatEquipment>,
) {
    companion object {
        fun fromJson(json: JsonObject): ChatProfile {
            val group = json.obj("group")
            val subordinates = json.objects("subordinates").map { ChatPerson.fromJson(it) }.distinctBy { it.id }
            return ChatProfile(
                id = json.long("id") ?: 0L,
                fullName = chatPersonName(json.str("name"), json.str("last_name"), json.str("middle_name"))
                    .ifEmpty { json.str("email").orEmpty() },
                email = json.text("email"),
                phone = json.text("phone"),
                position = json.text("position"),
                avatarUrl = json.text("avatar"),
                isActive = json.boolOr("is_active", true),
                groupName = group.text("display_name") ?: group.text("name"),
                manager = json.obj("manager")?.let { ChatPerson.fromJson(it) },
                subordinatesCount = json.int("subordinates_count") ?: subordinates.size,
                subordinates = subordinates,
                equipment = json.objects("issued_equipment").map { ChatEquipment.fromJson(it) }.distinctBy { it.id },
            )
        }
    }
}
