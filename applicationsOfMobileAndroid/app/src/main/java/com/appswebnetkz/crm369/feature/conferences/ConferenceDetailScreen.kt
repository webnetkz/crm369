package com.appswebnetkz.crm369.feature.conferences

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.MultiOptionPickerDialog
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/**
 * Conference details: join (opens the WebRTC room in the authenticated web view), guest link,
 * participants, inviting users (`can.invite`) and ending the conference (`can.manage`).
 */
@Composable
fun ConferenceDetailScreen(conferenceId: Long) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { ConferencesRepository(api) }
    val runner = rememberActionRunner()
    val copy = rememberConferenceCopy()
    val screen = rememberLoadable(api, conferenceId) { repository.conference(conferenceId) }
    var confirmEnd by remember { mutableStateOf(false) }
    var invitePicker by remember { mutableStateOf(false) }
    val endedMessage = stringResource(R.string.conferences_ended_success)
    val invitedMessage = stringResource(R.string.conferences_invited_success)
    val nothingToInviteMessage = stringResource(R.string.conferences_invited_already)

    val loaded = screen.data
    val inviteOptions = if (loaded != null) {
        val invitedIds = loaded.detail.invitations.mapNotNull { it.user?.id }.toSet() +
            listOfNotNull(loaded.detail.conference.creator?.id)
        loaded.availableUsers.filter { it.id !in invitedIds }.map { it.toOption() }
    } else {
        emptyList()
    }

    CrmScaffold(
        title = loaded?.detail?.conference?.title ?: stringResource(R.string.conferences_title),
        subtitle = loaded?.detail?.providerLabel,
    ) { padding ->
        LoadableContent(loadable = screen, modifier = Modifier.padding(padding)) { data ->
            val info = data.detail
            val conference = info.conference
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState()),
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 16.dp),
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        ConferenceStatusChip(status = conference.status)
                        StatusChip(
                            text = stringResource(
                                if (conference.allowExternalGuests) {
                                    R.string.conferences_guest_access_enabled
                                } else {
                                    R.string.conferences_guest_access_disabled
                                },
                            ),
                            color = if (conference.allowExternalGuests) {
                                LocalExtendedColors.current.accent
                            } else {
                                MaterialTheme.colorScheme.onSurfaceVariant
                            },
                        )
                    }
                    Spacer(Modifier.height(8.dp))
                    Text(conference.title, style = MaterialTheme.typography.titleLarge)
                    Spacer(Modifier.height(12.dp))
                    if (conference.isEnded) {
                        Text(
                            text = stringResource(R.string.conferences_ended_notice),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    } else {
                        Button(
                            onClick = { navigator.openWeb(info.webPath, conference.title) },
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Icon(AppIcons.VideoCall, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.size(8.dp))
                            Text(stringResource(R.string.conferences_join))
                        }
                    }
                }

                InfoCard(title = stringResource(R.string.conferences_section_details)) {
                    val description = conference.description
                    if (description != null) {
                        Text(
                            text = description,
                            style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                    KeyValueRow(
                        label = stringResource(R.string.conferences_field_starts_at),
                        value = Formatters.dateTime(conference.startsAt).ifBlank { null }
                            ?: stringResource(R.string.conferences_starts_now),
                        icon = AppIcons.Schedule,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.conferences_ended_at),
                        value = Formatters.dateTime(conference.endedAt),
                        icon = AppIcons.History,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.conferences_status),
                        value = conferenceStatusLabel(conference.status),
                        icon = AppIcons.Info,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.conferences_provider_label),
                        value = info.providerLabel,
                        icon = AppIcons.Video,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.conferences_host),
                        value = conference.creator?.name,
                        icon = AppIcons.Person,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.conferences_room_code),
                        value = info.roomName,
                        icon = AppIcons.Key,
                    )
                }

                InfoCard(title = stringResource(R.string.conferences_public_link)) {
                    val link = info.guestLink
                    if (link != null) {
                        Text(
                            text = link,
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.primary,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                        Row(
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            OutlinedButton(onClick = { copy(link) }) {
                                Icon(AppIcons.Copy, contentDescription = null, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.size(8.dp))
                                Text(stringResource(R.string.conferences_copy_public_link))
                            }
                            OutlinedButton(onClick = { shareConferenceLink(context, link, conference.title) }) {
                                Icon(AppIcons.Share, contentDescription = null, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.size(8.dp))
                                Text(stringResource(R.string.conferences_share_link))
                            }
                        }
                    } else {
                        Text(
                            text = stringResource(R.string.conferences_public_link_disabled),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                }

                InfoCard(title = stringResource(R.string.conferences_participants_count, info.invitations.size)) {
                    if (info.invitations.isEmpty()) {
                        Text(
                            text = stringResource(R.string.conferences_no_invited_users),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                    info.invitations.forEach { invitation -> InvitationRow(invitation = invitation) }
                    if (info.canInvite && !conference.isEnded) {
                        TextButton(
                            onClick = {
                                if (inviteOptions.isEmpty()) {
                                    messenger.show(nothingToInviteMessage)
                                } else {
                                    invitePicker = true
                                }
                            },
                            enabled = !runner.isBusy,
                            modifier = Modifier.padding(horizontal = 8.dp),
                        ) {
                            Icon(AppIcons.PersonAdd, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.size(8.dp))
                            Text(stringResource(R.string.conferences_invite_more))
                        }
                    }
                }

                if (info.canManage && !conference.isEnded) {
                    OutlinedButton(
                        onClick = { confirmEnd = true },
                        enabled = !runner.isBusy,
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 12.dp),
                    ) {
                        Text(stringResource(R.string.conferences_end_conference))
                    }
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }

    if (invitePicker) {
        MultiOptionPickerDialog(
            title = stringResource(R.string.conferences_invite_more),
            options = inviteOptions,
            selected = emptySet(),
            onDismiss = { invitePicker = false },
            onConfirm = { selected ->
                invitePicker = false
                val ids = selected.mapNotNull { it.toLongOrNull() }
                if (ids.isNotEmpty()) {
                    runner.execute(
                        onSuccess = { result: ConferenceInviteResult ->
                            screen.update { current -> current.copy(detail = result.detail) }
                            messenger.show(if (result.invitedCount > 0) invitedMessage else nothingToInviteMessage)
                        },
                    ) { repository.invite(conferenceId, ids) }
                }
            },
        )
    }

    if (confirmEnd) {
        ConfirmDialog(
            title = stringResource(R.string.conferences_end_title),
            text = stringResource(R.string.conferences_end_text),
            confirmLabel = stringResource(R.string.conferences_end_conference),
            destructive = true,
            onDismiss = { confirmEnd = false },
            onConfirm = {
                confirmEnd = false
                runner.execute(
                    successMessage = endedMessage,
                    onSuccess = { ended: ConferenceDetail -> screen.update { current -> current.copy(detail = ended) } },
                ) { repository.end(conferenceId) }
            },
        )
    }
}

@Composable
private fun InvitationRow(invitation: ConferenceInvitation) {
    val user = invitation.user
    val activity = listOfNotNull(
        invitation.joinedAt?.let { stringResource(R.string.conferences_joined_at, Formatters.dateTime(it)) },
        invitation.lastOpenedAt?.let { stringResource(R.string.conferences_last_opened_at, Formatters.dateTime(it)) },
    ).ifEmpty {
        listOf(stringResource(R.string.conferences_not_joined))
    }
    ListRow(
        title = user?.name ?: stringResource(R.string.conferences_unknown_user),
        subtitle = (listOfNotNull(user?.email) + activity).joinToString("\n"),
        maxSubtitleLines = 3,
        leading = { Avatar(name = user?.name, imageUrl = user?.avatarUrl, size = 36.dp) },
    )
}
