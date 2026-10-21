@file:OptIn(ExperimentalMaterial3Api::class)

package com.appswebnetkz.crm369.feature.edo

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
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** EDO document card: requisites, attached file, signing link and signature. */
@Composable
fun EdoDetailScreen(documentId: Long) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val container = LocalAppContainer.current
    val clipboard = LocalClipboardManager.current
    val repository = remember(api) { EdoRepository(api) }
    val runner = rememberActionRunner()
    val document = rememberLoadable(api, documentId) { repository.document(documentId) }
    var confirmDelete by remember { mutableStateOf(false) }
    val deletedMessage = stringResource(R.string.edo_deleted)
    val linkCreatedMessage = stringResource(R.string.edo_link_created)
    val copiedMessage = stringResource(R.string.edo_link_copied)

    CrmScaffold(
        title = document.data?.title ?: stringResource(R.string.module_edo),
        subtitle = document.data?.let { edoStatusLabel(it.status) },
        actions = {
            if (document.data != null) {
                IconButton(onClick = { navigator.open(EdoRoutes.edit(documentId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = document, modifier = Modifier.padding(padding)) { item ->
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState()),
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                ) {
                    StatusChip(text = edoStatusLabel(item.status), color = edoStatusColor(item.status))
                    Spacer(Modifier.height(8.dp))
                    Text(item.title, style = MaterialTheme.typography.titleLarge)
                }

                InfoCard(title = stringResource(R.string.edo_section_document)) {
                    KeyValueRow(label = stringResource(R.string.edo_external_reference), value = item.externalReference, icon = AppIcons.Tag)
                    KeyValueRow(label = stringResource(R.string.edo_counterparty), value = item.counterpartyName, icon = AppIcons.Company)
                    KeyValueRow(
                        label = stringResource(R.string.edo_counterparty_identifier),
                        value = item.counterpartyIdentifier,
                        icon = AppIcons.Key,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.edo_counterparty_email),
                        value = item.counterpartyEmail,
                        icon = AppIcons.Email,
                        onClick = item.counterpartyEmail?.let { email -> { navigator.email(email) } },
                    )
                    KeyValueRow(
                        label = stringResource(R.string.edo_document_source),
                        value = edoSourceLabel(item.documentSource),
                        icon = AppIcons.Document,
                    )
                }

                val file = item.file
                if (file != null) {
                    val fileName = file.name.ifBlank { item.title }
                    InfoCard(title = stringResource(R.string.edo_attached_file)) {
                        ListRow(
                            title = fileName,
                            subtitle = listOfNotNull(Formatters.fileSize(file.sizeBytes).ifBlank { null }, file.mimeType)
                                .joinToString(" · ")
                                .ifBlank { null },
                            leading = { Icon(AppIcons.File, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
                        )
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 16.dp, vertical = 4.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            OutlinedButton(
                                onClick = {
                                    runner.execute {
                                        container.fileTransfer.downloadAndOpen(api, repository.downloadPath(item), fileName)
                                    }
                                },
                                enabled = !runner.isBusy,
                            ) {
                                Icon(AppIcons.Download, contentDescription = null, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.size(8.dp))
                                Text(stringResource(R.string.edo_open_file))
                            }
                            OutlinedButton(
                                onClick = {
                                    runner.execute {
                                        container.fileTransfer.downloadAndShare(api, repository.downloadPath(item), fileName)
                                    }
                                },
                                enabled = !runner.isBusy,
                            ) {
                                Icon(AppIcons.Share, contentDescription = null, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.size(8.dp))
                                Text(stringResource(R.string.edo_share_file))
                            }
                        }
                    }
                }

                if (item.documentSource == EdoDocument.SOURCE_TEXT && !item.content.isNullOrBlank()) {
                    InfoCard(title = stringResource(R.string.edo_content)) {
                        SelectionContainer {
                            Text(
                                text = item.content,
                                style = MaterialTheme.typography.bodyMedium,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                            )
                        }
                    }
                }

                InfoCard(title = stringResource(R.string.edo_public_link)) {
                    val url = item.publicSignUrl
                    if (item.hasActivePublicLink && url != null) {
                        SelectionContainer {
                            Text(
                                text = url,
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.primary,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
                            )
                        }
                        KeyValueRow(
                            label = stringResource(R.string.edo_link_expires),
                            value = Formatters.dateTime(item.publicSignExpiresAt),
                            icon = AppIcons.Schedule,
                        )
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .horizontalScroll(rememberScrollState())
                                .padding(horizontal = 16.dp, vertical = 4.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            AssistChip(
                                onClick = {
                                    clipboard.setText(AnnotatedString(url))
                                    messenger.show(copiedMessage)
                                },
                                label = { Text(stringResource(R.string.edo_copy_link)) },
                                leadingIcon = { Icon(AppIcons.Copy, contentDescription = null, modifier = Modifier.size(18.dp)) },
                            )
                            AssistChip(
                                onClick = { shareText(context, url) },
                                label = { Text(stringResource(R.string.edo_share_link)) },
                                leadingIcon = { Icon(AppIcons.Share, contentDescription = null, modifier = Modifier.size(18.dp)) },
                            )
                            AssistChip(
                                onClick = { navigator.openUrl(url) },
                                label = { Text(stringResource(R.string.edo_open_link)) },
                                leadingIcon = { Icon(AppIcons.Public, contentDescription = null, modifier = Modifier.size(18.dp)) },
                            )
                        }
                    } else {
                        Text(
                            text = stringResource(if (item.isSigned) R.string.edo_link_not_needed else R.string.edo_link_hint),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                    if (!item.isSigned) {
                        Button(
                            onClick = {
                                runner.execute(
                                    successMessage = linkCreatedMessage,
                                    onSuccess = { updated: EdoDocument -> document.set(updated) },
                                ) { repository.createPublicLink(documentId) }
                            },
                            enabled = !runner.isBusy,
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 16.dp, vertical = 8.dp),
                        ) {
                            Icon(AppIcons.Link, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.size(8.dp))
                            Text(
                                stringResource(
                                    if (item.hasActivePublicLink) R.string.edo_link_recreate else R.string.edo_link_create,
                                ),
                            )
                        }
                    }
                }

                if (item.isSigned || item.signedAt != null) {
                    InfoCard(title = stringResource(R.string.edo_signature)) {
                        KeyValueRow(label = stringResource(R.string.edo_signed_at), value = Formatters.dateTime(item.signedAt), icon = AppIcons.Done)
                        KeyValueRow(label = stringResource(R.string.edo_signature_subject), value = item.signatureSubject, icon = AppIcons.Person)
                        KeyValueRow(label = stringResource(R.string.edo_signature_serial), value = item.signatureSerialNumber, icon = AppIcons.Key)
                        KeyValueRow(label = stringResource(R.string.edo_signature_algorithm), value = item.signatureAlgorithm, icon = AppIcons.Security)
                        KeyValueRow(label = stringResource(R.string.edo_signature_hash), value = item.signedPayloadHash, icon = AppIcons.Fingerprint)
                    }
                }

                InfoCard {
                    KeyValueRow(
                        label = stringResource(R.string.edo_created),
                        value = listOfNotNull(Formatters.dateTime(item.createdAt).ifBlank { null }, item.creatorName).joinToString(" · "),
                        icon = AppIcons.Time,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.edo_updated),
                        value = listOfNotNull(Formatters.dateTime(item.updatedAt).ifBlank { null }, item.updaterName).joinToString(" · "),
                        icon = AppIcons.History,
                    )
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.edo_delete_title),
            text = stringResource(R.string.edo_delete_text),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                confirmDelete = false
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.delete(documentId)
                }
            },
        )
    }
}
