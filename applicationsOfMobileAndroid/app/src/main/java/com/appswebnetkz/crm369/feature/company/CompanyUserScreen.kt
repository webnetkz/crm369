package com.appswebnetkz.crm369.feature.company

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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.feature.chats.ChatsRoutes
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons

/**
 * Employee card (`GET company-structure/users/{id}`): chain of managers, contacts, "Message"
 * (only with `canAccessChats`), the manager and the direct reports.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CompanyUserScreen(userId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val bootstrap = rememberBootstrap()
    val repository = remember(api) { CompanyRepository(api) }
    val runner = rememberActionRunner()
    val detail = rememberLoadable(api, userId) { repository.user(userId) }
    val canChat = bootstrap != null && bootstrap.can("canAccessChats") && bootstrap.user.id != userId

    CrmScaffold(
        title = detail.data?.node?.person?.fullName ?: stringResource(R.string.company_employee_card),
        subtitle = detail.data?.node?.person?.position,
    ) { padding ->
        LoadableContent(loadable = detail, modifier = Modifier.padding(padding)) { data ->
            val node = data.node
            val person = node.person
            val email = person.email
            val phone = person.phone
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
                    Avatar(name = person.fullName, imageUrl = person.avatarUrl, size = 88.dp)
                    Spacer(Modifier.height(12.dp))
                    Text(text = person.fullName, style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        text = person.position ?: stringResource(R.string.company_no_position),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        textAlign = TextAlign.Center,
                    )
                    if (!person.isActive) {
                        Spacer(Modifier.height(8.dp))
                        StatusChip(text = stringResource(R.string.company_inactive), color = MaterialTheme.colorScheme.error)
                    }
                }

                if (data.ancestors.isNotEmpty()) {
                    SectionHeader(title = stringResource(R.string.company_chain))
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .horizontalScroll(rememberScrollState())
                            .padding(horizontal = 16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        data.ancestors.forEach { ancestor ->
                            AssistChip(
                                onClick = { navigator.open(CompanyRoutes.user(ancestor.id)) },
                                label = { Text(text = ancestor.fullName, maxLines = 1, overflow = TextOverflow.Ellipsis) },
                                leadingIcon = { Avatar(name = ancestor.fullName, imageUrl = ancestor.avatarUrl, size = 20.dp) },
                            )
                            Icon(
                                AppIcons.ChevronRight,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.size(18.dp),
                            )
                        }
                        Text(
                            text = person.fullName,
                            style = MaterialTheme.typography.labelLarge,
                            fontWeight = FontWeight.SemiBold,
                            maxLines = 1,
                            modifier = Modifier.padding(horizontal = 4.dp),
                        )
                    }
                }

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    if (canChat) {
                        Button(
                            enabled = !runner.isBusy,
                            onClick = {
                                runner.execute(
                                    onSuccess = { conversationId: Long -> navigator.open(ChatsRoutes.conversation(conversationId)) },
                                ) { repository.openDirectChat(userId) }
                            },
                        ) {
                            Icon(AppIcons.Chat, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(stringResource(R.string.company_write))
                        }
                    }
                    if (email != null) {
                        AssistChip(
                            onClick = { navigator.email(email) },
                            label = { Text(stringResource(R.string.company_send_email)) },
                            leadingIcon = { Icon(AppIcons.Email, contentDescription = null, modifier = Modifier.size(18.dp)) },
                        )
                    }
                    if (phone != null) {
                        AssistChip(
                            onClick = { navigator.dial(phone) },
                            label = { Text(stringResource(R.string.company_call)) },
                            leadingIcon = { Icon(AppIcons.Call, contentDescription = null, modifier = Modifier.size(18.dp)) },
                        )
                    }
                }

                InfoCard(title = stringResource(R.string.company_contacts)) {
                    KeyValueRow(
                        label = stringResource(R.string.company_email),
                        value = email,
                        icon = AppIcons.Email,
                        onClick = if (email != null) {
                            { navigator.email(email) }
                        } else {
                            null
                        },
                    )
                    KeyValueRow(
                        label = stringResource(R.string.company_phone),
                        value = phone,
                        icon = AppIcons.Phone,
                        onClick = if (phone != null) {
                            { navigator.dial(phone) }
                        } else {
                            null
                        },
                    )
                    KeyValueRow(label = stringResource(R.string.company_position), value = person.position, icon = AppIcons.Projects)
                }

                InfoCard(title = stringResource(R.string.company_manager)) {
                    val manager = node.manager
                    if (manager != null) {
                        CompanyPersonRow(person = manager, onClick = { navigator.open(CompanyRoutes.user(manager.id)) })
                    } else {
                        CompanyMutedText(text = stringResource(R.string.company_no_manager))
                    }
                }

                val team = node.subordinates.ifEmpty { node.children.map { it.person } }
                InfoCard(title = stringResource(R.string.company_subordinates_count, maxOf(node.subordinatesCount, team.size))) {
                    if (team.isEmpty()) {
                        CompanyMutedText(text = stringResource(R.string.company_no_subordinates))
                    }
                    team.forEach { member ->
                        CompanyPersonRow(person = member, onClick = { navigator.open(CompanyRoutes.user(member.id)) })
                    }
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }
}

@Composable
private fun CompanyMutedText(text: String) {
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
    )
}
