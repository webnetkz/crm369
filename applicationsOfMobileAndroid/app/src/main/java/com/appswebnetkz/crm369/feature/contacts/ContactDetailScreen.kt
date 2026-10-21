package com.appswebnetkz.crm369.feature.contacts

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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AssistChip
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
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
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Contact card: contacts, requisites, notes and the comment history. */
@Composable
fun ContactDetailScreen(contactId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { ContactsRepository(api) }
    val runner = rememberActionRunner()
    val contact = rememberLoadable(api, contactId) { repository.contact(contactId) }
    val comments = rememberLoadable(api, contactId) { repository.comments(contactId) }
    var confirmDelete by remember { mutableStateOf(false) }
    var newComment by rememberSaveable { mutableStateOf("") }
    val deletedMessage = stringResource(R.string.contacts_deleted)

    CrmScaffold(
        title = contact.data?.name ?: stringResource(R.string.contacts_title),
        subtitle = contact.data?.typeLabel,
        actions = {
            if (contact.data != null) {
                IconButton(onClick = { navigator.open(ContactsRoutes.edit(contactId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = contact, modifier = Modifier.padding(padding)) { item ->
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
                    Avatar(name = item.name, imageUrl = item.avatarUrl, size = 88.dp)
                    Spacer(Modifier.height(12.dp))
                    Text(item.name, style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        StatusChip(text = item.typeLabel)
                        if (item.isBlacklisted) {
                            StatusChip(text = stringResource(R.string.contacts_blacklisted), color = MaterialTheme.colorScheme.error)
                        }
                    }
                }

                QuickActions(contact = item)

                InfoCard(title = stringResource(R.string.contacts_section_contacts)) {
                    KeyValueRow(
                        label = stringResource(R.string.contacts_phone),
                        value = item.phone,
                        icon = AppIcons.Phone,
                        onClick = item.phone?.let { phone -> { navigator.dial(phone) } },
                    )
                    KeyValueRow(
                        label = stringResource(R.string.contacts_email),
                        value = item.email,
                        icon = AppIcons.Email,
                        onClick = item.email?.let { email -> { navigator.email(email) } },
                    )
                    KeyValueRow(label = stringResource(R.string.contacts_contact_person), value = item.contactPerson, icon = AppIcons.Person)
                    KeyValueRow(label = stringResource(R.string.contacts_position), value = item.position, icon = AppIcons.Projects)
                }

                val requisites = item.requisites
                if (requisites != null) {
                    InfoCard(title = stringResource(R.string.contacts_section_requisites)) {
                        KeyValueRow(label = stringResource(R.string.contacts_iin), value = requisites.iin)
                        KeyValueRow(label = stringResource(R.string.contacts_bin), value = requisites.bin)
                        KeyValueRow(
                            label = stringResource(R.string.contacts_legal_address),
                            value = requisites.legalAddress,
                            icon = AppIcons.Place,
                            onClick = requisites.legalAddress?.let { address -> { navigator.map(address) } },
                        )
                        KeyValueRow(
                            label = stringResource(R.string.contacts_actual_address),
                            value = requisites.actualAddress,
                            icon = AppIcons.Place,
                            onClick = requisites.actualAddress?.let { address -> { navigator.map(address) } },
                        )
                        KeyValueRow(label = stringResource(R.string.contacts_bank_name), value = requisites.bankName)
                        KeyValueRow(label = stringResource(R.string.contacts_bank_bik), value = requisites.bankBik)
                        KeyValueRow(label = stringResource(R.string.contacts_iban), value = requisites.iban)
                        KeyValueRow(label = stringResource(R.string.contacts_kbe), value = requisites.kbe)
                    }
                }

                if (!item.notes.isNullOrBlank()) {
                    InfoCard(title = stringResource(R.string.contacts_notes)) {
                        Text(
                            text = item.notes,
                            style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                }

                InfoCard(title = stringResource(R.string.contacts_comments)) {
                    val commentList = comments.data.orEmpty()
                    if (commentList.isEmpty()) {
                        Text(
                            text = stringResource(R.string.contacts_comments_empty),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                    commentList.forEach { comment ->
                        ListRow(
                            title = comment.content,
                            overline = listOfNotNull(comment.authorName, Formatters.dateTime(comment.createdAt).ifBlank { null })
                                .joinToString(" · "),
                            leading = { Avatar(name = comment.authorName, imageUrl = comment.authorAvatar, size = 32.dp) },
                        )
                    }
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        OutlinedTextField(
                            value = newComment,
                            onValueChange = { newComment = it },
                            placeholder = { Text(stringResource(R.string.contacts_comment_placeholder)) },
                            modifier = Modifier.weight(1f),
                            maxLines = 4,
                        )
                        IconButton(
                            enabled = newComment.isNotBlank() && !runner.isBusy,
                            onClick = {
                                val text = newComment.trim()
                                runner.execute(
                                    onSuccess = { created: ContactComment ->
                                        newComment = ""
                                        comments.set(listOf(created) + comments.data.orEmpty())
                                    },
                                ) { repository.addComment(contactId, text) }
                            },
                        ) {
                            Icon(AppIcons.Send, contentDescription = stringResource(R.string.action_send))
                        }
                    }
                }

                InfoCard {
                    KeyValueRow(
                        label = stringResource(R.string.contacts_created),
                        value = listOfNotNull(Formatters.dateTime(item.createdAt).ifBlank { null }, item.createdBy).joinToString(" · "),
                        icon = AppIcons.Time,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.contacts_updated),
                        value = listOfNotNull(Formatters.dateTime(item.updatedAt).ifBlank { null }, item.updatedBy).joinToString(" · "),
                        icon = AppIcons.History,
                    )
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.contacts_delete_title),
            text = stringResource(R.string.contacts_delete_text),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.delete(contactId)
                }
            },
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class) // AssistChip was experimental in earlier material3 releases
@Composable
private fun QuickActions(contact: Contact) {
    val navigator = LocalNavigator.current
    val phone = contact.phone
    val email = contact.email
    if (phone == null && email == null) return
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 16.dp, vertical = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        if (phone != null) {
            AssistChip(
                onClick = { navigator.dial(phone) },
                label = { Text(stringResource(R.string.contacts_call)) },
                leadingIcon = { Icon(AppIcons.Call, contentDescription = null, modifier = Modifier.size(18.dp)) },
            )
            AssistChip(
                onClick = { navigator.whatsApp(phone) },
                label = { Text(stringResource(R.string.contacts_whatsapp)) },
                leadingIcon = { Icon(AppIcons.Chat, contentDescription = null, modifier = Modifier.size(18.dp)) },
            )
        }
        if (email != null) {
            AssistChip(
                onClick = { navigator.email(email) },
                label = { Text(stringResource(R.string.contacts_write_email)) },
                leadingIcon = { Icon(AppIcons.Email, contentDescription = null, modifier = Modifier.size(18.dp)) },
            )
        }
    }
}
