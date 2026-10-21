package com.appswebnetkz.crm369.ui.notifications

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObjects
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ChipRow
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.InlineProgress
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadMoreEffect
import com.appswebnetkz.crm369.ui.components.PageResult
import com.appswebnetkz.crm369.ui.components.PagedContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberPagedLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

private data class NotificationItem(
    val id: String,
    val title: String,
    val message: String,
    val actionUrl: String?,
    val actionLabel: String?,
    val createdAt: String?,
    val isRead: Boolean,
)

/** `GET notifications` with filters, mark one / all as read, open the target screen. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsScreen() {
    val api = rememberApi()
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val runner = rememberActionRunner()
    var status by rememberSaveable { mutableStateOf("all") }
    val listState = rememberLazyListState()

    val paged = rememberPagedLoadable(status, distinctBy = { item: NotificationItem -> item.id }) { page ->
        val response = api.get("notifications", mapOf("status" to status, "per_page" to 30, "page" to page))
        val meta = response.obj("meta")
        meta.int("unread_count")?.let { container.session.updateCounters(notifications = it) }
        PageResult(
            items = response.dataObjects().map { json ->
                NotificationItem(
                    id = json.str("id").orEmpty(),
                    title = json.text("title") ?: "",
                    message = json.str("message").orEmpty(),
                    actionUrl = json.text("action_url"),
                    actionLabel = json.text("action_label"),
                    createdAt = json.text("created_at"),
                    isRead = json.boolOr("is_read", false),
                )
            },
            hasMore = (meta.int("current_page") ?: 1) < (meta.int("last_page") ?: 1),
            total = meta.int("total"),
        )
    }

    LoadMoreEffect(listState = listState, enabled = paged.hasMore) { paged.loadMore() }

    fun markRead(item: NotificationItem) {
        if (item.isRead) return
        paged.update { list -> list.map { if (it.id == item.id) it.copy(isRead = true) else it } }
        runner.execute(onError = { true }) {
            val response = api.patch("notifications/${item.id}/read")
            response.obj("meta").int("unread_count")?.let { container.session.updateCounters(notifications = it) }
        }
    }

    CrmScaffold(
        title = stringResource(R.string.notifications_title),
        actions = {
            IconButton(
                onClick = {
                    runner.execute(successMessage = null) {
                        api.patch("notifications/read-all")
                        container.session.updateCounters(notifications = 0)
                        paged.update { list -> list.map { it.copy(isRead = true) } }
                    }
                },
                enabled = !runner.isBusy,
            ) {
                Icon(AppIcons.DoneAll, contentDescription = stringResource(R.string.notifications_read_all))
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            ChipRow {
                listOf(
                    "all" to R.string.notifications_filter_all,
                    "unread" to R.string.notifications_filter_unread,
                    "read" to R.string.notifications_filter_read,
                ).forEach { (key, label) ->
                    FilterChip(
                        selected = status == key,
                        onClick = { status = key },
                        label = { Text(stringResource(label)) },
                    )
                }
            }
            PagedContent(
                paged = paged,
                empty = {
                    EmptyState(
                        title = stringResource(R.string.notifications_empty),
                        icon = AppIcons.Notifications,
                    )
                },
            ) { list ->
                LazyColumn(state = listState, modifier = Modifier.fillMaxSize()) {
                    items(list, key = { it.id }) { item ->
                        ListRow(
                            title = item.title,
                            subtitle = item.message,
                            overline = Formatters.relative(item.createdAt),
                            maxSubtitleLines = 4,
                            leading = {
                                Box {
                                    Icon(
                                        imageVector = AppIcons.Notifications,
                                        contentDescription = null,
                                        tint = if (item.isRead) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.primary,
                                    )
                                    if (!item.isRead) {
                                        Surface(
                                            color = MaterialTheme.colorScheme.error,
                                            shape = CircleShape,
                                            modifier = Modifier.size(8.dp),
                                        ) {}
                                    }
                                }
                            },
                            trailing = if (!item.actionUrl.isNullOrBlank()) {
                                { Icon(AppIcons.ChevronRight, contentDescription = null) }
                            } else {
                                null
                            },
                            onClick = {
                                markRead(item)
                                val target = item.actionUrl
                                if (!target.isNullOrBlank()) {
                                    navigator.openDeepLink(target, item.actionLabel ?: item.title)
                                }
                            },
                        )
                        RowDivider()
                    }
                    if (paged.isLoadingMore) {
                        item { InlineProgress() }
                    }
                }
            }
        }
    }
}

/** Bold title helper for unread items (kept for list customizations). */
internal val UnreadWeight = FontWeight.SemiBold
