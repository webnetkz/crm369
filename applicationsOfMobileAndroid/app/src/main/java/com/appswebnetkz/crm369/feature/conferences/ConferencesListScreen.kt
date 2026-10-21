package com.appswebnetkz.crm369.feature.conferences

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Conferences grouped into "now", "scheduled" and "past" (`GET conferences`). */
@Composable
fun ConferencesListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { ConferencesRepository(api) }
    val overview = rememberLoadable(api) { repository.overview() }

    CrmScaffold(
        title = stringResource(R.string.conferences_title),
        subtitle = overview.data?.providerLabel,
        floatingActionButton = {
            FloatingActionButton(onClick = { navigator.open(ConferencesRoutes.NEW) }) {
                Icon(AppIcons.Add, contentDescription = stringResource(R.string.conferences_new))
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = overview,
            modifier = Modifier.padding(padding),
            isEmpty = { it.isEmpty },
            empty = {
                EmptyState(
                    title = stringResource(R.string.conferences_empty),
                    message = stringResource(R.string.conferences_empty_description),
                    icon = AppIcons.Conferences,
                    actionLabel = stringResource(R.string.conferences_new),
                    onAction = { navigator.open(ConferencesRoutes.NEW) },
                )
            },
        ) { data ->
            val sections = listOf(
                Triple("current", R.string.conferences_group_current, data.current),
                Triple("upcoming", R.string.conferences_group_upcoming, data.upcoming),
                Triple("past", R.string.conferences_group_past, data.past),
            )
            LazyColumn(modifier = Modifier.fillMaxSize()) {
                sections.forEach { (group, titleRes, conferences) ->
                    item(key = "header-$group") {
                        SectionHeader(title = stringResource(titleRes))
                    }
                    if (conferences.isEmpty()) {
                        item(key = "empty-$group") {
                            Text(
                                text = stringResource(R.string.conferences_group_empty),
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                            )
                        }
                    } else {
                        items(conferences, key = { "$group-${it.id}" }) { conference ->
                            ConferenceRow(
                                conference = conference,
                                onClick = { navigator.open(ConferencesRoutes.detail(conference.id)) },
                            )
                            RowDivider()
                        }
                    }
                }
                item(key = "fab-space") { Spacer(Modifier.height(88.dp)) }
            }
        }
    }
}

@Composable
private fun ConferenceRow(conference: ConferenceItem, onClick: () -> Unit) {
    val time = Formatters.dateTime(conference.startsAt).ifBlank { null }
        ?: stringResource(
            when {
                conference.isEnded -> R.string.conferences_no_start_time
                conference.status == ConferenceItem.STATUS_LIVE -> R.string.conferences_live_now
                else -> R.string.conferences_starts_now
            },
        )
    val details = listOfNotNull(
        conference.creator?.let { stringResource(R.string.conferences_host_value, it.name) },
        stringResource(R.string.conferences_invited_count, conference.invitedUsersCount),
    ).joinToString(" · ")
    ListRow(
        title = conference.title,
        overline = time,
        subtitle = details,
        leading = { Icon(AppIcons.VideoCall, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
        trailing = {
            Column(
                horizontalAlignment = Alignment.End,
                verticalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                ConferenceStatusChip(status = conference.status)
                if (conference.allowExternalGuests) {
                    StatusChip(
                        text = stringResource(R.string.conferences_guest_badge),
                        color = LocalExtendedColors.current.accent,
                    )
                }
            }
        },
        onClick = onClick,
    )
}
