package com.appswebnetkz.crm369.feature.chats

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.interaction.collectIsDraggedAsState
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.SmallFloatingActionButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.network.UploadPart
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.TextInputDialog
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private const val CONVERSATION_POLL_MILLIS = 5_000L
private const val MAX_ATTACHMENTS = 10
private const val MAX_ATTACHMENT_BYTES = 20L * 1024L * 1024L
private const val MAX_BODY_LENGTH = 4000
private const val HIGHLIGHT_MILLIS = 2_000L

/**
 * A conversation (`GET chats?conversation=ID`): full history with day separators, attachments,
 * pinned messages, read state, message actions (long press) and the composer. Polls every 5 s
 * while visible and keeps the scroll position unless the user is at the newest message.
 */
@Composable
fun ChatConversationScreen(conversationId: Long) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val container = LocalAppContainer.current
    val messenger = LocalMessenger.current
    val clipboard = LocalClipboardManager.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    val repository = remember(api) { ChatsRepository(api) }
    val sendRunner = rememberActionRunner()
    val actionRunner = rememberActionRunner()
    val fileRunner = rememberActionRunner()

    val state = rememberLoadable(api, conversationId) {
        repository.conversation(conversationId).also { opened ->
            container.session.updateCounters(chats = opened.unreadCount)
        }
    }

    LaunchedEffect(lifecycleOwner, state) {
        var firstResume = true
        lifecycleOwner.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            if (!firstResume && state.data != null) state.load()
            firstResume = false
            while (true) {
                delay(CONVERSATION_POLL_MILLIS)
                if (state.data != null && !sendRunner.isBusy && !actionRunner.isBusy) state.load()
            }
        }
    }

    var draft by rememberSaveable(conversationId) { mutableStateOf("") }
    val pending = remember(conversationId) { mutableStateListOf<PendingAttachment>() }

    // reverseLayout: index 0 is the newest message at the bottom of the screen.
    val listState = rememberLazyListState()
    val isDragged by listState.interactionSource.collectIsDraggedAsState()
    val atBottom by remember(listState) { derivedStateOf { listState.firstVisibleItemIndex == 0 } }
    var follow by remember { mutableStateOf(true) }
    LaunchedEffect(atBottom, isDragged) {
        if (isDragged) {
            follow = atBottom
        } else if (atBottom) {
            follow = true
        }
    }
    var highlightedId by remember { mutableStateOf<Long?>(null) }
    LaunchedEffect(highlightedId) {
        if (highlightedId != null) {
            delay(HIGHLIGHT_MILLIS)
            highlightedId = null
        }
    }

    var menuMessageId by remember { mutableStateOf<Long?>(null) }
    var editing by remember { mutableStateOf<ChatMessage?>(null) }
    var deleting by remember { mutableStateOf<ChatMessage?>(null) }
    var readersOf by remember { mutableStateOf<ChatMessage?>(null) }
    var showPinned by remember { mutableStateOf(false) }
    var showParticipants by remember { mutableStateOf(false) }

    val requiredMessage = stringResource(R.string.chats_message_required)
    val limitMessage = stringResource(R.string.chats_attachments_limit, MAX_ATTACHMENTS)
    val copiedMessage = stringResource(R.string.chats_copied)
    val deletedMessage = stringResource(R.string.chats_message_deleted)
    val pinnedMessage = stringResource(R.string.chats_pinned_done)
    val unpinnedMessage = stringResource(R.string.chats_unpinned_done)
    val todayLabel = stringResource(R.string.chats_today)
    val yesterdayLabel = stringResource(R.string.chats_yesterday)
    val untitled = stringResource(R.string.chats_untitled)

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetMultipleContents()) { uris: List<Uri> ->
        var limitReached = false
        for (uri in uris) {
            if (pending.any { it.uri == uri }) continue
            if (pending.size >= MAX_ATTACHMENTS) {
                limitReached = true
                break
            }
            val part = runCatching { UploadPart.fromUri(context, "attachments[]", uri) }.getOrNull() ?: continue
            if (part.size > MAX_ATTACHMENT_BYTES) {
                messenger.show(context.getString(R.string.chats_attachment_too_large, part.fileName))
                continue
            }
            pending += PendingAttachment(uri = uri, name = part.fileName, size = part.size)
        }
        if (limitReached) messenger.show(limitMessage)
    }

    val conversation = state.data?.conversation
    val rows = remember(conversation?.messages, conversation?.isGroup, todayLabel, yesterdayLabel) {
        if (conversation == null) {
            emptyList<ChatRow>()
        } else {
            buildChatRows(conversation.messages, conversation.isGroup, todayLabel, yesterdayLabel)
        }
    }

    val send: () -> Unit = {
        val text = draft.trim()
        val files = pending.toList()
        if (text.isEmpty() && files.isEmpty()) {
            messenger.show(requiredMessage)
        } else {
            sendRunner.execute(
                onSuccess = { message: ChatMessage ->
                    draft = ""
                    pending.clear()
                    follow = true
                    state.update { current -> current.copy(conversation = current.conversation.appendMessage(message)) }
                    state.load()
                },
            ) { repository.send(context, conversationId, text, files.map { it.uri }) }
        }
    }

    val togglePin: (ChatMessage) -> Unit = { message ->
        val pin = !message.isPinned
        actionRunner.execute(
            successMessage = if (pin) pinnedMessage else unpinnedMessage,
            onSuccess = { _: ChatMessage -> state.load() },
        ) { repository.setPinned(conversationId, message.id, pin) }
    }

    val openAttachment: (ChatAttachment) -> Unit = { attachment ->
        val url = attachment.openUrl
        if (url != null) {
            fileRunner.execute { container.fileTransfer.downloadAndOpen(api, url, attachment.name) }
        }
    }

    val jumpTo: (Long) -> Unit = { messageId ->
        val index = rows.indexOfFirst { row -> row is ChatRow.Message && row.message.id == messageId }
        if (index >= 0) {
            follow = false
            highlightedId = messageId
            scope.launch { listState.animateScrollToItem(index) }
        }
    }

    val participantsLabel = stringResource(R.string.chats_participants_count, conversation?.participants?.size ?: 0)
    val subtitle = when {
        conversation == null -> null
        conversation.isDirect -> conversation.subtitle ?: conversation.participant?.email
        else -> conversation.subtitle ?: participantsLabel
    }

    CrmScaffold(
        title = conversation?.title?.ifBlank { untitled } ?: stringResource(R.string.module_chats),
        subtitle = subtitle,
        actions = {
            if (conversation != null) {
                val participant = conversation.participant
                if (conversation.isDirect && participant != null) {
                    IconButton(onClick = { navigator.open(ChatsRoutes.user(participant.id)) }) {
                        Avatar(name = participant.name, imageUrl = participant.avatarUrl, size = 32.dp)
                    }
                } else if (!conversation.isDirect) {
                    IconButton(onClick = { showParticipants = true }) {
                        Icon(AppIcons.People, contentDescription = stringResource(R.string.chats_participants))
                    }
                }
            }
        },
        bottomBar = {
            if (conversation != null) {
                ChatComposer(
                    text = draft,
                    onTextChange = { value -> draft = value.take(MAX_BODY_LENGTH) },
                    attachments = pending,
                    onRemoveAttachment = { file -> pending.remove(file) },
                    onAttach = { picker.launch("*/*") },
                    onSend = send,
                    isSending = sendRunner.isBusy,
                )
            }
        },
    ) { padding ->
        LoadableContent(loadable = state, modifier = Modifier.padding(padding)) { data ->
            val current = data.conversation
            val newestKey = rows.firstOrNull()?.key
            LaunchedEffect(newestKey) {
                if (newestKey != null && follow) listState.animateScrollToItem(0)
            }
            Column(modifier = Modifier.fillMaxSize()) {
                val pinned = current.latestPinned
                if (pinned != null) {
                    ChatPinnedBar(message = pinned, count = current.pinnedMessages.size, onClick = { showPinned = true })
                }
                if (fileRunner.isBusy || actionRunner.isBusy) {
                    LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                }
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth(),
                ) {
                    if (rows.isEmpty()) {
                        EmptyState(
                            title = stringResource(R.string.chats_no_messages_yet),
                            message = stringResource(R.string.chats_first_message_hint),
                            icon = AppIcons.Chat,
                        )
                    } else {
                        LazyColumn(
                            state = listState,
                            reverseLayout = true,
                            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp),
                            modifier = Modifier.fillMaxSize(),
                        ) {
                            items(rows, key = { it.key }) { row ->
                                when (row) {
                                    is ChatRow.Day -> ChatDaySeparator(label = row.label)
                                    is ChatRow.Message -> {
                                        val message = row.message
                                        ChatMessageBubble(
                                            message = message,
                                            showAuthor = row.showAuthor,
                                            isGroup = current.isGroup,
                                            highlighted = highlightedId == message.id,
                                            menuExpanded = menuMessageId == message.id,
                                            onLongPress = { if (!message.isDeleted) menuMessageId = message.id },
                                            onDismissMenu = { menuMessageId = null },
                                            onOpenAttachment = openAttachment,
                                            onShowReaders = { readersOf = message },
                                            onOpenAuthor = {
                                                val author = message.user
                                                if (author != null) navigator.open(ChatsRoutes.user(author.id))
                                            },
                                        ) {
                                            if (message.body.isNotBlank()) {
                                                DropdownMenuItem(
                                                    text = { Text(stringResource(R.string.chats_copy)) },
                                                    leadingIcon = { Icon(AppIcons.Copy, contentDescription = null) },
                                                    onClick = {
                                                        menuMessageId = null
                                                        clipboard.setText(AnnotatedString(message.body))
                                                        messenger.show(copiedMessage)
                                                    },
                                                )
                                            }
                                            DropdownMenuItem(
                                                text = {
                                                    Text(stringResource(if (message.isPinned) R.string.chats_unpin else R.string.chats_pin))
                                                },
                                                leadingIcon = {
                                                    Icon(if (message.isPinned) AppIcons.PinOutline else AppIcons.Pin, contentDescription = null)
                                                },
                                                onClick = {
                                                    menuMessageId = null
                                                    togglePin(message)
                                                },
                                            )
                                            if (message.isOwn) {
                                                DropdownMenuItem(
                                                    text = { Text(stringResource(R.string.action_edit)) },
                                                    leadingIcon = { Icon(AppIcons.Edit, contentDescription = null) },
                                                    onClick = {
                                                        menuMessageId = null
                                                        editing = message
                                                    },
                                                )
                                                DropdownMenuItem(
                                                    text = { Text(stringResource(R.string.action_delete)) },
                                                    leadingIcon = { Icon(AppIcons.Delete, contentDescription = null) },
                                                    onClick = {
                                                        menuMessageId = null
                                                        deleting = message
                                                    },
                                                )
                                                DropdownMenuItem(
                                                    text = { Text(stringResource(R.string.chats_message_viewers)) },
                                                    leadingIcon = { Icon(AppIcons.Visibility, contentDescription = null) },
                                                    onClick = {
                                                        menuMessageId = null
                                                        readersOf = message
                                                    },
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if (!atBottom && rows.isNotEmpty()) {
                        SmallFloatingActionButton(
                            onClick = {
                                follow = true
                                scope.launch { listState.animateScrollToItem(0) }
                            },
                            modifier = Modifier
                                .align(Alignment.BottomEnd)
                                .padding(16.dp),
                        ) {
                            Icon(AppIcons.ExpandMore, contentDescription = stringResource(R.string.chats_scroll_to_latest))
                        }
                    }
                }
            }
        }
    }

    val editingMessage = editing
    if (editingMessage != null) {
        TextInputDialog(
            title = stringResource(R.string.chats_editing_message),
            initialValue = editingMessage.body,
            singleLine = false,
            onDismiss = { editing = null },
            onConfirm = { value ->
                editing = null
                val body = value.trim().take(MAX_BODY_LENGTH)
                if (body.isEmpty() && editingMessage.attachments.isEmpty()) {
                    messenger.show(requiredMessage)
                } else if (body != editingMessage.body) {
                    actionRunner.execute(
                        onSuccess = { _: ChatMessage ->
                            state.update { current ->
                                current.copy(
                                    conversation = current.conversation.updateMessage(editingMessage.id) { message ->
                                        message.copy(body = body, isEdited = true)
                                    },
                                )
                            }
                            state.load()
                        },
                    ) { repository.edit(conversationId, editingMessage.id, body) }
                }
            },
        )
    }

    val deletingMessage = deleting
    if (deletingMessage != null) {
        ConfirmDialog(
            title = stringResource(R.string.chats_delete_confirm),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { deleting = null },
            onConfirm = {
                deleting = null
                actionRunner.execute(
                    successMessage = deletedMessage,
                    onSuccess = { _: Unit ->
                        state.update { current ->
                            current.copy(
                                conversation = current.conversation.updateMessage(deletingMessage.id) { message ->
                                    message.copy(isDeleted = true, body = "", attachments = emptyList())
                                },
                            )
                        }
                        state.load()
                    },
                ) { repository.delete(conversationId, deletingMessage.id) }
            },
        )
    }

    val readersMessage = readersOf
    if (readersMessage != null) {
        ChatReadersDialog(message = readersMessage, onDismiss = { readersOf = null })
    }

    if (showPinned && conversation != null) {
        ChatPinnedDialog(
            messages = conversation.pinnedMessages,
            onDismiss = { showPinned = false },
            onSelect = { message ->
                showPinned = false
                jumpTo(message.id)
            },
        )
    }

    if (showParticipants && conversation != null) {
        ChatParticipantsDialog(
            participants = conversation.participants,
            onDismiss = { showParticipants = false },
            onSelect = { user ->
                showParticipants = false
                navigator.open(ChatsRoutes.user(user.id))
            },
        )
    }
}
