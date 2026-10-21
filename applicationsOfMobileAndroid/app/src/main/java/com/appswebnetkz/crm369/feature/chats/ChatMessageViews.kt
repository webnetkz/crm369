package com.appswebnetkz.crm369.feature.chats

import android.net.Uri
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.InputChip
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import java.time.LocalDate

/** A row of the message list. The list uses `reverseLayout`, so rows are ordered newest first. */
internal sealed class ChatRow {
    abstract val key: String

    data class Day(val label: String, override val key: String) : ChatRow()

    data class Message(val message: ChatMessage, val showAuthor: Boolean) : ChatRow() {
        override val key: String get() = "message-${message.id}"
    }
}

/**
 * Builds the rows (newest first) from the chronological history: a day separator before the first
 * message of every day and, in group chats, the author at the start of each author group.
 */
internal fun buildChatRows(messages: List<ChatMessage>, isGroup: Boolean, today: String, yesterday: String): List<ChatRow> {
    val rows = ArrayList<ChatRow>(messages.size + 8)
    val usedDayKeys = HashSet<String>()
    val now = LocalDate.now()
    var previousDay: LocalDate? = null
    var previous: ChatMessage? = null
    for (message in messages) {
        val day = Formatters.parseDateTime(message.createdAt)?.toLocalDate()
        var startsDay = false
        if (day != null && day != previousDay) {
            startsDay = true
            previousDay = day
            val label = when (day) {
                now -> today
                now.minusDays(1) -> yesterday
                else -> Formatters.date(day)
            }
            val baseKey = "day-$day"
            rows += ChatRow.Day(label = label, key = if (usedDayKeys.add(baseKey)) baseKey else "$baseKey-${message.id}")
        }
        val sameAuthor = previous != null && !previous.isOwn && previous.user?.id == message.user?.id
        val showAuthor = isGroup && !message.isOwn && (startsDay || !sameAuthor)
        rows += ChatRow.Message(message = message, showAuthor = showAuthor)
        previous = message
    }
    return rows.reversed()
}

/** A file picked for the next message. */
data class PendingAttachment(val uri: Uri, val name: String, val size: Long)

/** Short text of a message for the pinned bar and lists. */
@Composable
internal fun chatMessagePreview(message: ChatMessage): String {
    val deleted = stringResource(R.string.chats_deleted_message)
    val attachmentLabel = stringResource(R.string.chats_attachment)
    return when {
        message.isDeleted -> deleted
        message.body.isNotBlank() -> message.body
        message.attachments.isNotEmpty() -> message.attachments.joinToString(", ") { it.name }
        else -> attachmentLabel
    }
}

/** Round icon "avatar" (general chat, group conversations). */
@Composable
internal fun ChatIconAvatar(icon: ImageVector, modifier: Modifier = Modifier, size: Dp = 40.dp) {
    Box(
        modifier = modifier
            .size(size)
            .clip(CircleShape)
            .background(MaterialTheme.colorScheme.primaryContainer),
        contentAlignment = Alignment.Center,
    ) {
        Icon(
            icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onPrimaryContainer,
            modifier = Modifier.size(size * 0.55f),
        )
    }
}

@Composable
internal fun ChatDaySeparator(label: String) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        contentAlignment = Alignment.Center,
    ) {
        Surface(
            shape = RoundedCornerShape(12.dp),
            color = MaterialTheme.colorScheme.surfaceContainerHighest,
        ) {
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
            )
        }
    }
}

/** Message bubble: own messages on the right; author avatar + name in group chats; long press → [menu]. */
@OptIn(ExperimentalFoundationApi::class)
@Composable
internal fun ChatMessageBubble(
    message: ChatMessage,
    showAuthor: Boolean,
    isGroup: Boolean,
    highlighted: Boolean,
    menuExpanded: Boolean,
    onLongPress: () -> Unit,
    onDismissMenu: () -> Unit,
    onOpenAttachment: (ChatAttachment) -> Unit,
    onShowReaders: () -> Unit,
    onOpenAuthor: () -> Unit,
    menu: @Composable ColumnScope.() -> Unit,
) {
    val own = message.isOwn
    val shape = RoundedCornerShape(16.dp)
    val containerColor = if (own) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceContainerHigh
    val contentColor = if (own) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurface
    val unknownUser = stringResource(R.string.chats_unknown_user)
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 2.dp),
        horizontalArrangement = if (own) Arrangement.End else Arrangement.Start,
        verticalAlignment = Alignment.Top,
    ) {
        if (isGroup && !own) {
            if (showAuthor) {
                Avatar(
                    name = message.user?.name,
                    imageUrl = message.user?.avatarUrl,
                    size = 32.dp,
                    modifier = Modifier.clickable(onClick = onOpenAuthor),
                )
            } else {
                Spacer(Modifier.width(32.dp))
            }
            Spacer(Modifier.width(8.dp))
        }
        Box {
            Surface(
                shape = shape,
                color = containerColor,
                contentColor = contentColor,
                border = if (highlighted) BorderStroke(2.dp, MaterialTheme.colorScheme.primary) else null,
                modifier = Modifier
                    .widthIn(max = 300.dp)
                    .clip(shape)
                    .combinedClickable(onClick = {}, onLongClick = onLongPress),
            ) {
                Column(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
                    if (showAuthor) {
                        Text(
                            text = message.user?.name ?: unknownUser,
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.primary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                    if (message.isDeleted) {
                        Text(
                            text = stringResource(R.string.chats_deleted_message),
                            style = MaterialTheme.typography.bodyMedium,
                            fontStyle = FontStyle.Italic,
                            color = LocalContentColor.current.copy(alpha = 0.7f),
                        )
                    } else {
                        message.attachments.forEach { attachment ->
                            ChatAttachmentView(attachment = attachment, onOpen = { onOpenAttachment(attachment) })
                        }
                        if (message.body.isNotBlank()) {
                            Text(text = message.body, style = MaterialTheme.typography.bodyMedium)
                        }
                    }
                    Row(
                        modifier = Modifier
                            .align(Alignment.End)
                            .padding(top = 2.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp),
                    ) {
                        val metaColor = LocalContentColor.current.copy(alpha = 0.7f)
                        if (message.isPinned) {
                            Icon(
                                AppIcons.Pin,
                                contentDescription = stringResource(R.string.chats_pinned_message),
                                tint = metaColor,
                                modifier = Modifier.size(12.dp),
                            )
                        }
                        if (message.isEdited && !message.isDeleted) {
                            Text(text = stringResource(R.string.chats_edited), style = MaterialTheme.typography.labelSmall, color = metaColor)
                        }
                        Text(text = Formatters.time(message.createdAt), style = MaterialTheme.typography.labelSmall, color = metaColor)
                        if (own && !message.isDeleted) {
                            ChatReadIndicator(message = message, isGroup = isGroup, onClick = onShowReaders)
                        }
                    }
                }
            }
            DropdownMenu(expanded = menuExpanded, onDismissRequest = onDismissMenu, content = menu)
        }
    }
}

@Composable
private fun ChatReadIndicator(message: ChatMessage, isGroup: Boolean, onClick: () -> Unit) {
    val read = message.isRead || message.readCount > 0
    val tint = if (read) MaterialTheme.colorScheme.primary else LocalContentColor.current.copy(alpha = 0.7f)
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .clickable(onClick = onClick),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            if (read) AppIcons.DoneAll else AppIcons.Done,
            contentDescription = stringResource(if (read) R.string.chats_read else R.string.chats_sent),
            tint = tint,
            modifier = Modifier.size(14.dp),
        )
        if (isGroup && message.readCount > 0) {
            Text(
                text = message.readCount.toString(),
                style = MaterialTheme.typography.labelSmall,
                color = tint,
                modifier = Modifier.padding(start = 2.dp),
            )
        }
    }
}

/** Image attachments inline (`previewUrl`), other files and audio as tappable tiles. */
@Composable
private fun ChatAttachmentView(attachment: ChatAttachment, onOpen: () -> Unit) {
    val api = rememberApi()
    val preview = attachment.previewUrl
    if (attachment.isImage && preview != null) {
        AsyncImage(
            model = api.absoluteUrl(preview),
            contentDescription = attachment.name,
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .padding(bottom = 4.dp)
                .size(width = 220.dp, height = 160.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(MaterialTheme.colorScheme.surfaceVariant)
                .clickable(onClick = onOpen),
        )
    } else {
        val details = listOfNotNull(
            if (attachment.isAudio) stringResource(R.string.chats_audio) else null,
            Formatters.fileSize(attachment.sizeBytes).ifBlank { null },
        ).joinToString(" · ")
        Row(
            modifier = Modifier
                .padding(bottom = 4.dp)
                .widthIn(max = 260.dp)
                .clip(RoundedCornerShape(10.dp))
                .background(MaterialTheme.colorScheme.surface.copy(alpha = 0.6f))
                .clickable(onClick = onOpen)
                .padding(horizontal = 10.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(
                if (attachment.isAudio) AppIcons.Mic else AppIcons.File,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(28.dp),
            )
            Spacer(Modifier.width(10.dp))
            Column {
                Text(
                    text = attachment.name,
                    style = MaterialTheme.typography.bodyMedium,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                if (details.isNotEmpty()) {
                    Text(
                        text = details,
                        style = MaterialTheme.typography.labelSmall,
                        color = LocalContentColor.current.copy(alpha = 0.7f),
                    )
                }
            }
        }
    }
}

/** Bar under the top bar with the latest pinned message; tap opens the list of pinned messages. */
@Composable
internal fun ChatPinnedBar(message: ChatMessage, count: Int, onClick: () -> Unit) {
    Surface(
        color = MaterialTheme.colorScheme.surfaceContainer,
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(AppIcons.Pin, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = if (count > 1) {
                        stringResource(R.string.chats_pinned_count, count)
                    } else {
                        stringResource(R.string.chats_pinned_message)
                    },
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary,
                )
                Text(
                    text = chatMessagePreview(message),
                    style = MaterialTheme.typography.bodySmall,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            Icon(
                AppIcons.ChevronRight,
                contentDescription = stringResource(R.string.chats_pinned_messages),
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
}

/** Message input: multiline text, picked files as chips, attach and send buttons (IME aware). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun ChatComposer(
    text: String,
    onTextChange: (String) -> Unit,
    attachments: List<PendingAttachment>,
    onRemoveAttachment: (PendingAttachment) -> Unit,
    onAttach: () -> Unit,
    onSend: () -> Unit,
    isSending: Boolean,
) {
    val canSend = !isSending && (text.isNotBlank() || attachments.isNotEmpty())
    Surface(tonalElevation = 3.dp, modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = 8.dp, vertical = 6.dp),
        ) {
            if (attachments.isNotEmpty()) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    attachments.forEach { file ->
                        InputChip(
                            selected = false,
                            enabled = !isSending,
                            onClick = { onRemoveAttachment(file) },
                            label = {
                                Text(
                                    text = file.name,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis,
                                    modifier = Modifier.widthIn(max = 160.dp),
                                )
                            },
                            leadingIcon = { Icon(AppIcons.Attach, contentDescription = null, modifier = Modifier.size(16.dp)) },
                            trailingIcon = {
                                Icon(
                                    AppIcons.Close,
                                    contentDescription = stringResource(R.string.chats_remove_attachment),
                                    modifier = Modifier.size(16.dp),
                                )
                            },
                        )
                    }
                }
            }
            Row(verticalAlignment = Alignment.Bottom) {
                IconButton(onClick = onAttach, enabled = !isSending) {
                    Icon(AppIcons.Attach, contentDescription = stringResource(R.string.chats_attach_files))
                }
                OutlinedTextField(
                    value = text,
                    onValueChange = onTextChange,
                    readOnly = isSending,
                    placeholder = { Text(stringResource(R.string.chats_message_placeholder)) },
                    maxLines = 5,
                    shape = RoundedCornerShape(20.dp),
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
                    modifier = Modifier.weight(1f),
                )
                Box(modifier = Modifier.size(48.dp), contentAlignment = Alignment.Center) {
                    if (isSending) {
                        CircularProgressIndicator(modifier = Modifier.size(22.dp), strokeWidth = 2.dp)
                    } else {
                        IconButton(onClick = onSend, enabled = canSend) {
                            Icon(
                                AppIcons.Send,
                                contentDescription = stringResource(R.string.action_send),
                                tint = if (canSend) MaterialTheme.colorScheme.primary else LocalContentColor.current,
                            )
                        }
                    }
                }
            }
        }
    }
}

/** "Who has read the message" (`readBy`). */
@Composable
internal fun ChatReadersDialog(message: ChatMessage, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.action_close)) } },
        title = { Text(stringResource(R.string.chats_message_viewers)) },
        text = {
            Column(
                modifier = Modifier
                    .heightIn(max = 360.dp)
                    .verticalScroll(rememberScrollState()),
            ) {
                if (message.readBy.isEmpty()) {
                    Text(
                        text = stringResource(R.string.chats_not_read_yet),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                } else {
                    Text(
                        text = stringResource(R.string.chats_seen_by_count, maxOf(message.readCount, message.readBy.size)),
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                message.readBy.forEach { reader ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Avatar(name = reader.name, imageUrl = null, size = 32.dp)
                        Spacer(Modifier.width(12.dp))
                        Text(text = reader.name, style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
        },
    )
}

/** All pinned messages, newest pin first; tap jumps to the message. */
@Composable
internal fun ChatPinnedDialog(messages: List<ChatMessage>, onSelect: (ChatMessage) -> Unit, onDismiss: () -> Unit) {
    val unknownUser = stringResource(R.string.chats_unknown_user)
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.action_close)) } },
        title = { Text(stringResource(R.string.chats_pinned_messages)) },
        text = {
            Column(
                modifier = Modifier
                    .heightIn(max = 420.dp)
                    .verticalScroll(rememberScrollState()),
            ) {
                messages.sortedByDescending { it.pinnedAt.orEmpty() }.forEach { message ->
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .clickable { onSelect(message) }
                            .padding(horizontal = 4.dp, vertical = 8.dp),
                    ) {
                        Text(
                            text = listOfNotNull(
                                message.user?.name ?: unknownUser,
                                Formatters.dateTime(message.createdAt).ifBlank { null },
                            ).joinToString(" · "),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.primary,
                        )
                        Text(
                            text = chatMessagePreview(message),
                            style = MaterialTheme.typography.bodyMedium,
                            maxLines = 3,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }
        },
    )
}

/** Participants of a general/task chat; tap opens the profile. */
@Composable
internal fun ChatParticipantsDialog(participants: List<ChatUser>, onSelect: (ChatUser) -> Unit, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.action_close)) } },
        title = { Text(stringResource(R.string.chats_participants)) },
        text = {
            Column(
                modifier = Modifier
                    .heightIn(max = 420.dp)
                    .verticalScroll(rememberScrollState()),
            ) {
                participants.forEach { user ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .clickable { onSelect(user) }
                            .padding(horizontal = 4.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Avatar(name = user.name, imageUrl = user.avatarUrl, size = 32.dp)
                        Spacer(Modifier.width(12.dp))
                        Column {
                            Text(text = user.name, style = MaterialTheme.typography.bodyMedium)
                            val email = user.email
                            if (email != null) {
                                Text(
                                    text = email,
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    }
                }
            }
        },
    )
}
