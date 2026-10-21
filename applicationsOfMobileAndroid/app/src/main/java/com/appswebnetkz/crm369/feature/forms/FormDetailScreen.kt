@file:OptIn(ExperimentalMaterial3Api::class)

package com.appswebnetkz.crm369.feature.forms

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
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
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/**
 * Form card (`GET forms/{id}`): settings, public link (copy/share/open), fields and the latest
 * submissions with links to the created task or chat. Edit/delete when `can.manage`.
 */
@Composable
fun FormDetailScreen(formId: Long) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val clipboard = LocalClipboardManager.current
    val repository = remember(api) { FormsRepository(api) }
    val runner = rememberActionRunner()
    val form = rememberLoadable(api, formId) { repository.form(formId) }
    val expanded = remember(formId) { mutableStateListOf<Long>() }
    var confirmDelete by remember { mutableStateOf(false) }
    val deletedMessage = stringResource(R.string.forms_deleted)
    val copiedMessage = stringResource(R.string.forms_link_copied)
    val shareTitle = stringResource(R.string.forms_share_link)
    val detail = form.data

    CrmScaffold(
        title = detail?.summary?.name ?: stringResource(R.string.module_forms),
        subtitle = if (detail != null) stringResource(formModeLabel(detail.summary.submissionMode)) else null,
        actions = {
            if (detail != null && detail.canManage) {
                IconButton(onClick = { navigator.open(FormsRoutes.edit(formId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = form, modifier = Modifier.padding(padding)) { data ->
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState()),
            ) {
                InfoCard(title = stringResource(R.string.forms_section_main)) {
                    val description = data.summary.description
                    if (description != null) {
                        Text(
                            text = description,
                            style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                    KeyValueRow(
                        label = stringResource(R.string.forms_status),
                        value = stringResource(if (data.summary.isActive) R.string.forms_active else R.string.forms_inactive),
                        icon = AppIcons.Info,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.forms_submission_mode),
                        value = stringResource(formModeLabel(data.summary.submissionMode)),
                        icon = formModeIcon(data.summary.submissionMode),
                    )
                    KeyValueRow(
                        label = stringResource(R.string.forms_target_user),
                        value = data.targetUser?.name ?: data.summary.targetUserName,
                        icon = AppIcons.Person,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.forms_owner),
                        value = data.owner?.name,
                        icon = AppIcons.Account,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.forms_completion_action),
                        value = stringResource(formCompletionLabel(data.completion.action)),
                        icon = AppIcons.Done,
                    )
                    KeyValueRow(
                        label = stringResource(R.string.forms_submissions_total),
                        value = data.summary.submissionsCount.toString(),
                        icon = AppIcons.Inbox,
                    )
                }

                val publicUrl = data.summary.publicUrl
                if (publicUrl != null) {
                    InfoCard(title = stringResource(R.string.forms_public_link)) {
                        Text(
                            text = publicUrl,
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.primary,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .horizontalScroll(rememberScrollState())
                                .padding(horizontal = 16.dp, vertical = 8.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            AssistChip(
                                onClick = {
                                    clipboard.setText(AnnotatedString(publicUrl))
                                    messenger.show(copiedMessage)
                                },
                                label = { Text(stringResource(R.string.forms_copy_link)) },
                                leadingIcon = { Icon(AppIcons.Copy, contentDescription = null, modifier = Modifier.size(18.dp)) },
                            )
                            AssistChip(
                                onClick = { shareLink(context, publicUrl, shareTitle) },
                                label = { Text(stringResource(R.string.forms_share_link)) },
                                leadingIcon = { Icon(AppIcons.Share, contentDescription = null, modifier = Modifier.size(18.dp)) },
                            )
                            AssistChip(
                                onClick = { navigator.openUrl(publicUrl) },
                                label = { Text(stringResource(R.string.forms_open_public_form)) },
                                leadingIcon = { Icon(AppIcons.Public, contentDescription = null, modifier = Modifier.size(18.dp)) },
                            )
                        }
                    }
                }

                InfoCard(title = stringResource(R.string.forms_fields_title)) {
                    if (data.fields.isEmpty()) {
                        Text(
                            text = stringResource(R.string.forms_no_fields),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                        )
                    }
                    data.fields.forEach { field ->
                        val placeholder = field.placeholder
                        ListRow(
                            title = if (field.isRequired) stringResource(R.string.forms_required_mark, field.label) else field.label,
                            subtitle = listOfNotNull(
                                stringResource(formFieldTypeLabel(field.type)),
                                if (placeholder != null) stringResource(R.string.forms_placeholder_line, placeholder) else null,
                            ).joinToString(" · "),
                            leading = {
                                Icon(AppIcons.Edit, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                            },
                        )
                    }
                }

                SectionHeader(title = stringResource(R.string.forms_submissions_title))
                val submissions = data.submissions.sortedByDescending { it.id }
                if (submissions.isEmpty()) {
                    Text(
                        text = stringResource(R.string.forms_no_submissions),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    )
                }
                submissions.forEach { submission ->
                    SubmissionCard(
                        submission = submission,
                        isExpanded = submission.id in expanded,
                        onToggle = {
                            if (submission.id in expanded) expanded.remove(submission.id) else expanded.add(submission.id)
                        },
                        onOpenTask = { taskId -> navigator.openDeepLink("/tasks/$taskId") },
                        onOpenChat = { conversationId -> navigator.openDeepLink("/chats?conversation=$conversationId") },
                    )
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.forms_delete_title),
            text = stringResource(R.string.forms_delete_text),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.delete(formId)
                }
            },
        )
    }
}

@Composable
private fun SubmissionCard(
    submission: FormSubmission,
    isExpanded: Boolean,
    onToggle: () -> Unit,
    onOpenTask: (Long) -> Unit,
    onOpenChat: (Long) -> Unit,
) {
    val emptyValue = stringResource(R.string.forms_empty_value)
    InfoCard(onClick = onToggle) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = stringResource(R.string.forms_submission_number, submission.id),
                    style = MaterialTheme.typography.titleSmall,
                )
                val meta = listOfNotNull(
                    Formatters.dateTime(submission.createdAt).ifBlank { null },
                    submission.targetUserName,
                ).joinToString(" · ")
                if (meta.isNotBlank()) {
                    Text(
                        text = meta,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (!isExpanded) {
                    val preview = submission.payload.firstOrNull { !it.value.isNullOrBlank() }?.value
                    if (preview != null) {
                        Text(
                            text = preview,
                            style = MaterialTheme.typography.bodyMedium,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }
            Icon(
                imageVector = if (isExpanded) AppIcons.ExpandLess else AppIcons.ExpandMore,
                contentDescription = null,
            )
        }
        if (isExpanded) {
            submission.payload.forEach { answer ->
                KeyValueRow(label = answer.label, value = answer.value?.takeIf { it.isNotBlank() } ?: emptyValue)
            }
            val taskId = submission.projectTaskId
            val conversationId = submission.chatConversationId
            if (taskId != null || conversationId != null) {
                Row(
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    if (taskId != null) {
                        TextButton(onClick = { onOpenTask(taskId) }) {
                            Icon(AppIcons.Tasks, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.size(6.dp))
                            Text(stringResource(R.string.forms_open_task))
                        }
                    }
                    if (conversationId != null) {
                        TextButton(onClick = { onOpenChat(conversationId) }) {
                            Icon(AppIcons.Chats, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.size(6.dp))
                            Text(stringResource(R.string.forms_open_chat))
                        }
                    }
                }
            }
        }
    }
}

/** Shares the public link through the system share sheet. */
private fun shareLink(context: Context, url: String, title: String) {
    val intent = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_TEXT, url)
    }
    try {
        context.startActivity(Intent.createChooser(intent, title))
    } catch (exception: ActivityNotFoundException) {
        // No application can share plain text; nothing else to do.
    }
}
