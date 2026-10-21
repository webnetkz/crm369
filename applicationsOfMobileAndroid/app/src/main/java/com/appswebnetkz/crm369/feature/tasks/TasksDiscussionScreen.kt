package com.appswebnetkz.crm369.feature.tasks

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.AssistChip
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import coil.compose.AsyncImage
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.ChipRow
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.delay

private const val POLL_INTERVAL_MS = 10_000L
private const val MAX_ATTACHMENTS = 10
private const val MAX_BODY_LENGTH = 4000

/**
 * Task discussion (`GET tasks/{id}/conversation`): message history with attachments, a composer
 * (`POST chats/{conversation}/messages`, JSON or multipart) and polling every 10 s while visible.
 */
@Composable
fun TaskDiscussionScreen(taskId: Long) {
    val api = rememberApi()
    val context = LocalContext.current
    val container = LocalAppContainer.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val repository = remember(api) { TasksRepository(api) }
    val sendRunner = rememberActionRunner()
    val fileRunner = rememberActionRunner()
    val conversation = rememberLoadable(api, taskId) { repository.conversation(taskId) }
    var draft by rememberSaveable(taskId) { mutableStateOf("") }
    val attachments = remember(taskId) { mutableStateListOf<Uri>() }
    var countersRefreshed by remember(taskId) { mutableStateOf(false) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetMultipleContents()) { uris ->
        val free = (MAX_ATTACHMENTS - attachments.size).coerceAtLeast(0)
        attachments.addAll(uris.take(free))
    }

    // Poll for new messages while the screen is at least STARTED (visible).
    LaunchedEffect(conversation, lifecycleOwner) {
        lifecycleOwner.repeatOnLifecycle(Lifecycle.State.STARTED) {
            while (true) {
                delay(POLL_INTERVAL_MS)
                conversation.load()
            }
        }
    }
    // Opening the discussion marks it read: refresh the unread chat counters once.
    val hasData = conversation.data != null
    LaunchedEffect(hasData) {
        if (hasData && !countersRefreshed) {
            countersRefreshed = true
            container.session.refreshBootstrapQuietly()
        }
    }

    val current = conversation.data
    val canSend = current != null && (draft.isNotBlank() || attachments.isNotEmpty()) && !sendRunner.isBusy

    CrmScaffold(
        title = stringResource(R.string.tasks_discussion),
        subtitle = current?.title?.ifBlank { null },
        bottomBar = {
            Surface(tonalElevation = 3.dp) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .imePadding(),
                ) {
                    if (attachments.isNotEmpty()) {
                        ChipRow {
                            attachments.forEachIndexed { index, uri ->
                                AssistChip(
                                    onClick = { attachments.remove(uri) },
                                    label = { Text(stringResource(R.string.tasks_attachment_number, index + 1)) },
                                    leadingIcon = { Icon(AppIcons.Attach, contentDescription = null, modifier = Modifier.size(18.dp)) },
                                    trailingIcon = {
                                        Icon(
                                            AppIcons.Close,
                                            contentDescription = stringResource(R.string.tasks_attachment_remove),
                                            modifier = Modifier.size(18.dp),
                                        )
                                    },
                                )
                            }
                        }
                    }
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 4.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        IconButton(
                            onClick = { picker.launch("*/*") },
                            enabled = current != null && attachments.size < MAX_ATTACHMENTS && !sendRunner.isBusy,
                        ) {
                            Icon(AppIcons.Attach, contentDescription = stringResource(R.string.tasks_attach))
                        }
                        OutlinedTextField(
                            value = draft,
                            onValueChange = { value -> draft = value.take(MAX_BODY_LENGTH) },
                            placeholder = { Text(stringResource(R.string.tasks_discussion_placeholder)) },
                            modifier = Modifier.weight(1f),
                            maxLines = 5,
                        )
                        IconButton(
                            enabled = canSend,
                            onClick = {
                                val target = conversation.data
                                if (target != null) {
                                    val text = draft.trim()
                                    val files = attachments.toList()
                                    sendRunner.execute(
                                        onSuccess = { _: Unit ->
                                            draft = ""
                                            attachments.clear()
                                            conversation.load()
                                        },
                                    ) {
                                        repository.sendMessage(context, target.id, text, files)
                                    }
                                }
                            },
                        ) {
                            if (sendRunner.isBusy) {
                                CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                            } else {
                                Icon(AppIcons.Send, contentDescription = stringResource(R.string.action_send))
                            }
                        }
                    }
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = conversation, modifier = Modifier.padding(padding)) { data ->
            if (data.messages.isEmpty()) {
                EmptyState(
                    title = stringResource(R.string.tasks_discussion_empty),
                    message = stringResource(R.string.tasks_discussion_empty_hint),
                    icon = AppIcons.Chat,
                )
            } else {
                val messages = data.messages.reversed()
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    reverseLayout = true,
                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp),
                ) {
                    items(messages, key = { message -> message.id }) { message ->
                        MessageBubble(
                            message = message,
                            onOpenAttachment = { attachment ->
                                val url = attachment.downloadUrl
                                if (url.isNotBlank()) {
                                    fileRunner.execute { container.fileTransfer.downloadAndOpen(api, url, attachment.name) }
                                }
                            },
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun MessageBubble(message: DiscussionMessage, onOpenAttachment: (DiscussionAttachment) -> Unit) {
    val own = message.isOwn
    val author = message.author
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        horizontalArrangement = if (own) Arrangement.End else Arrangement.Start,
        verticalAlignment = Alignment.Bottom,
    ) {
        if (!own) {
            Avatar(name = author?.name, imageUrl = author?.avatarUrl, size = 32.dp)
            Spacer(Modifier.width(8.dp))
        }
        Surface(
            shape = RoundedCornerShape(16.dp),
            color = if (own) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceVariant,
            modifier = Modifier.widthIn(max = 300.dp),
        ) {
            Column(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
                if (!own && author != null) {
                    Text(
                        text = author.name,
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.primary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                if (message.body.isNotBlank()) {
                    Text(
                        text = message.body,
                        style = MaterialTheme.typography.bodyMedium,
                        fontStyle = if (message.isDeleted) FontStyle.Italic else null,
                    )
                }
                if (!message.isDeleted) {
                    message.attachments.forEach { attachment ->
                        AttachmentView(attachment = attachment, onOpen = { onOpenAttachment(attachment) })
                    }
                }
                val stamp = Formatters.shortStamp(message.createdAt)
                Text(
                    text = if (message.isEdited && !message.isDeleted) stringResource(R.string.tasks_message_edited, stamp) else stamp,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.align(Alignment.End),
                )
            }
        }
    }
}

@Composable
private fun AttachmentView(attachment: DiscussionAttachment, onOpen: () -> Unit) {
    val api = rememberApi()
    val previewUrl = attachment.previewUrl
    if (attachment.isImage && previewUrl != null) {
        AsyncImage(
            model = api.absoluteUrl(previewUrl),
            contentDescription = attachment.name,
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .padding(top = 6.dp)
                .size(width = 220.dp, height = 160.dp)
                .clip(RoundedCornerShape(12.dp))
                .clickable(onClick = onOpen),
        )
    } else {
        AssistChip(
            onClick = onOpen,
            label = {
                Text(
                    text = stringResource(R.string.tasks_attachment_label, attachment.name, Formatters.fileSize(attachment.sizeBytes)),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            },
            leadingIcon = { Icon(AppIcons.Attach, contentDescription = null, modifier = Modifier.size(18.dp)) },
        )
    }
}
