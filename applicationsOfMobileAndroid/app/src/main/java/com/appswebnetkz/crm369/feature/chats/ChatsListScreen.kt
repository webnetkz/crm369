package com.appswebnetkz.crm369.feature.chats

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.CountBadge
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.DebouncedSearchField
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons
import java.time.Duration
import java.time.Instant
import kotlinx.coroutines.delay

private const val LIST_POLL_MILLIS = 15_000L

/**
 * Content of the main screen "Chats" tab (also the `chats` route): the general chat first, then
 * direct chats; the search also finds people to start a new direct chat with.
 */
@Composable
fun ChatsListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val container = LocalAppContainer.current
    val bootstrap = rememberBootstrap()
    val lifecycleOwner = LocalLifecycleOwner.current
    val repository = remember(api) { ChatsRepository(api) }
    val runner = rememberActionRunner()
    var search by rememberSaveable { mutableStateOf("") }
    val myId = bootstrap?.user?.id

    val index = rememberLoadable(api, search) {
        repository.index(search).also { loaded -> container.session.updateCounters(chats = loaded.unreadCount) }
    }

    // Poll while the screen is visible; refresh right away when coming back from a conversation.
    LaunchedEffect(lifecycleOwner, index) {
        var firstResume = true
        lifecycleOwner.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            if (!firstResume) index.load()
            firstResume = false
            while (true) {
                delay(LIST_POLL_MILLIS)
                index.load()
            }
        }
    }

    val startChat: (ChatUser) -> Unit = { user ->
        runner.execute(onSuccess = { conversationId: Long -> navigator.open(ChatsRoutes.conversation(conversationId)) }) {
            repository.openDirect(user.id)
        }
    }

    val unread = index.data?.unreadCount ?: 0
    CrmScaffold(
        title = stringResource(R.string.module_chats),
        subtitle = if (unread > 0) stringResource(R.string.chats_unread_total, unread) else null,
        floatingActionButton = {
            FloatingActionButton(onClick = { navigator.open(ChatsRoutes.NEW) }) {
                Icon(AppIcons.Add, contentDescription = stringResource(R.string.chats_new))
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            DebouncedSearchField(
                onQuery = { query -> search = query },
                initialValue = search,
                placeholder = stringResource(R.string.chats_search_placeholder),
            )
            if (runner.isBusy) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
            }
            val searching = search.isNotBlank()
            LoadableContent(
                loadable = index,
                isEmpty = { data -> data.conversations.isEmpty() && (!searching || data.contacts.none { it.id != myId }) },
                empty = {
                    if (searching) {
                        EmptyState(title = stringResource(R.string.chats_nothing_found), icon = AppIcons.Search)
                    } else {
                        EmptyState(
                            title = stringResource(R.string.chats_empty),
                            message = stringResource(R.string.chats_empty_message),
                            icon = AppIcons.Chats,
                            actionLabel = stringResource(R.string.chats_start_chat),
                            onAction = { navigator.open(ChatsRoutes.NEW) },
                        )
                    }
                },
            ) { data ->
                val people = if (searching) data.contacts.filter { it.id != myId } else emptyList()
                LazyColumn(modifier = Modifier.fillMaxSize()) {
                    if (searching && data.conversations.isNotEmpty()) {
                        item(key = "header-chats") { SectionHeader(title = stringResource(R.string.chats_recent)) }
                    }
                    items(data.conversations, key = { "conversation-${it.id}" }) { conversation ->
                        ConversationRow(
                            conversation = conversation,
                            onClick = { navigator.open(ChatsRoutes.conversation(conversation.id)) },
                        )
                        RowDivider()
                    }
                    if (searching) {
                        item(key = "header-people") { SectionHeader(title = stringResource(R.string.chats_people)) }
                        if (people.isEmpty()) {
                            item(key = "people-empty") {
                                Text(
                                    text = stringResource(R.string.chats_no_contacts),
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                                )
                            }
                        }
                        items(people, key = { "person-${it.id}" }) { user ->
                            ListRow(
                                title = user.name,
                                subtitle = user.email,
                                leading = { Avatar(name = user.name, imageUrl = user.avatarUrl) },
                                trailing = {
                                    Icon(
                                        AppIcons.Chat,
                                        contentDescription = stringResource(R.string.chats_start_chat),
                                        tint = MaterialTheme.colorScheme.primary,
                                    )
                                },
                                onClick = { startChat(user) },
                            )
                            RowDivider()
                        }
                    }
                    item(key = "fab-space") { Spacer(Modifier.height(88.dp)) }
                }
            }
        }
    }
}

@Composable
private fun ConversationRow(conversation: ChatConversationItem, onClick: () -> Unit) {
    val untitled = stringResource(R.string.chats_untitled)
    val noMessages = stringResource(R.string.chats_no_messages_yet)
    val title = conversation.title.ifBlank { untitled }
    val participant = conversation.participant
    val stamp = chatListStamp(conversation.lastMessageAt)
    ListRow(
        title = title,
        subtitle = conversation.excerpt ?: conversation.subtitle ?: noMessages,
        maxSubtitleLines = 1,
        leading = {
            if (conversation.isGeneral) {
                ChatIconAvatar(icon = AppIcons.People)
            } else {
                Avatar(name = participant?.name ?: title, imageUrl = participant?.avatarUrl)
            }
        },
        trailing = {
            Column(
                horizontalAlignment = Alignment.End,
                verticalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                if (stamp.isNotEmpty()) {
                    Text(
                        text = stamp,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 1,
                    )
                }
                CountBadge(count = conversation.unreadCount)
            }
        },
        onClick = onClick,
    )
}

/** Relative time for the last week ("5 мин. назад"), a short date for older messages. */
private fun chatListStamp(value: String?): String {
    val parsed = Formatters.parseDateTime(value) ?: return ""
    val age = Duration.between(parsed.toInstant(), Instant.now())
    return if (age.toDays() >= 7) Formatters.date(value) else Formatters.relative(value)
}
