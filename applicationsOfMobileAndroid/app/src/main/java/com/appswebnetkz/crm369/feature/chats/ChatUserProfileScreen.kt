package com.appswebnetkz.crm369.feature.chats

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.feature.equipment.EquipmentRoutes
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Profile of a portal user (`GET chats/users/{id}/profile`) with a "Message" button. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatUserProfileScreen(userId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val bootstrap = rememberBootstrap()
    val repository = remember(api) { ChatsRepository(api) }
    val runner = rememberActionRunner()
    val profile = rememberLoadable(api, userId) { repository.profile(userId) }
    val isMe = bootstrap?.user?.id == userId
    val canOpenEquipment = bootstrap?.can("canAccessEquipment") == true

    CrmScaffold(
        title = profile.data?.fullName ?: stringResource(R.string.chats_profile_title),
        subtitle = profile.data?.position,
    ) { padding ->
        LoadableContent(loadable = profile, modifier = Modifier.padding(padding)) { user ->
            val email = user.email
            val phone = user.phone
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState()),
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 20.dp, horizontal = 16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Avatar(name = user.fullName, imageUrl = user.avatarUrl, size = 88.dp)
                    Spacer(Modifier.height(12.dp))
                    Text(text = user.fullName, style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
                    val position = user.position
                    if (position != null) {
                        Spacer(Modifier.height(4.dp))
                        Text(
                            text = position,
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            textAlign = TextAlign.Center,
                        )
                    }
                    val group = user.groupName
                    if (group != null || !user.isActive) {
                        Spacer(Modifier.height(8.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            if (group != null) {
                                StatusChip(text = group, color = MaterialTheme.colorScheme.secondary)
                            }
                            if (!user.isActive) {
                                StatusChip(text = stringResource(R.string.chats_inactive), color = MaterialTheme.colorScheme.error)
                            }
                        }
                    }
                }

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 16.dp, vertical = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    if (!isMe) {
                        Button(
                            enabled = !runner.isBusy,
                            onClick = {
                                runner.execute(
                                    onSuccess = { conversationId: Long -> navigator.open(ChatsRoutes.conversation(conversationId)) },
                                ) { repository.openDirect(userId) }
                            },
                        ) {
                            Icon(AppIcons.Chat, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(stringResource(R.string.chats_write))
                        }
                    }
                    if (phone != null) {
                        AssistChip(
                            onClick = { navigator.dial(phone) },
                            label = { Text(stringResource(R.string.chats_call)) },
                            leadingIcon = { Icon(AppIcons.Call, contentDescription = null, modifier = Modifier.size(18.dp)) },
                        )
                    }
                    if (email != null) {
                        AssistChip(
                            onClick = { navigator.email(email) },
                            label = { Text(stringResource(R.string.chats_send_email)) },
                            leadingIcon = { Icon(AppIcons.Email, contentDescription = null, modifier = Modifier.size(18.dp)) },
                        )
                    }
                }

                InfoCard(title = stringResource(R.string.chats_section_contacts)) {
                    KeyValueRow(
                        label = stringResource(R.string.chats_email),
                        value = email,
                        icon = AppIcons.Email,
                        onClick = if (email != null) {
                            { navigator.email(email) }
                        } else {
                            null
                        },
                    )
                    KeyValueRow(
                        label = stringResource(R.string.chats_phone),
                        value = phone,
                        icon = AppIcons.Phone,
                        onClick = if (phone != null) {
                            { navigator.dial(phone) }
                        } else {
                            null
                        },
                    )
                    KeyValueRow(label = stringResource(R.string.chats_position), value = user.position, icon = AppIcons.Projects)
                    KeyValueRow(label = stringResource(R.string.chats_group), value = user.groupName, icon = AppIcons.Group)
                }

                InfoCard(title = stringResource(R.string.chats_manager)) {
                    val manager = user.manager
                    if (manager != null) {
                        ProfilePersonRow(person = manager, onClick = { navigator.open(ChatsRoutes.user(manager.id)) })
                    } else {
                        ProfileMutedText(text = stringResource(R.string.chats_no_manager))
                    }
                }

                InfoCard(title = stringResource(R.string.chats_subordinates_count, user.subordinatesCount)) {
                    if (user.subordinates.isEmpty()) {
                        ProfileMutedText(text = stringResource(R.string.chats_no_subordinates))
                    }
                    user.subordinates.forEach { person ->
                        ProfilePersonRow(person = person, onClick = { navigator.open(ChatsRoutes.user(person.id)) })
                    }
                }

                if (user.equipment.isNotEmpty()) {
                    InfoCard(title = stringResource(R.string.chats_issued_equipment)) {
                        user.equipment.forEach { item ->
                            val status = item.statusLabel
                            val qrCode = item.qrCode
                            val responsible = item.responsibleName
                            val details = listOfNotNull(
                                if (qrCode != null) stringResource(R.string.chats_qr_code_value, qrCode) else null,
                                if (responsible != null) stringResource(R.string.chats_responsible_value, responsible) else null,
                            ).joinToString("\n")
                            ListRow(
                                title = item.name,
                                subtitle = details.ifBlank { null },
                                leading = { Icon(AppIcons.Equipment, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
                                trailing = if (status != null) {
                                    { StatusChip(text = status) }
                                } else {
                                    null
                                },
                                onClick = if (canOpenEquipment) {
                                    { navigator.open(EquipmentRoutes.detail(item.id)) }
                                } else {
                                    null
                                },
                            )
                        }
                    }
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }
}

@Composable
private fun ProfilePersonRow(person: ChatPerson, onClick: () -> Unit) {
    ListRow(
        title = person.fullName,
        subtitle = person.position ?: person.email,
        leading = { Avatar(name = person.fullName, imageUrl = person.avatarUrl) },
        trailing = { Icon(AppIcons.ChevronRight, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant) },
        onClick = onClick,
    )
}

@Composable
private fun ProfileMutedText(text: String) {
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
    )
}
