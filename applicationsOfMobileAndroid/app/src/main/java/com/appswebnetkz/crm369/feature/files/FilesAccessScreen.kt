package com.appswebnetkz.crm369.feature.files

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.OptionPickerDialog
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/**
 * Access of a folder (`activeDirectory.permissions`): users and groups with read/edit access.
 * Editors can grant access (`POST files/directories/{id}/permissions`), change the level of an
 * existing permission (same request) and revoke it.
 */
@Composable
fun FilesAccessScreen(directoryId: Long) {
    val api = rememberApi()
    val repository = remember(api) { FilesRepository(api) }
    val runner = rememberActionRunner()
    val page = rememberLoadable(api, directoryId) { repository.page(directoryId) }
    var pickSubject by remember { mutableStateOf(false) }
    var pendingSubject by remember { mutableStateOf<Option?>(null) }
    var pendingLevel by remember { mutableStateOf<String?>(null) }
    var permissionToRemove by remember { mutableStateOf<FilePermission?>(null) }
    val savedMessage = stringResource(R.string.files_permission_saved)
    val removedMessage = stringResource(R.string.files_permission_removed)
    val userLabel = stringResource(R.string.files_subject_user)
    val groupLabel = stringResource(R.string.files_subject_group)

    val data = page.data
    val directory = data?.active?.takeIf { it.id == directoryId }
    val canEdit = directory?.canEdit == true
    val openPicker: () -> Unit = { pickSubject = true }

    fun submitGrant(subject: Option, level: String) {
        val subjectType = subject.value.substringBefore(':')
        val subjectId = subject.value.substringAfter(':').toLongOrNull() ?: return
        runner.execute(successMessage = savedMessage, onSuccess = { _: Unit -> page.refresh() }) {
            repository.grantAccess(directoryId, level, subjectType, subjectId)
        }
    }

    CrmScaffold(
        title = stringResource(R.string.files_access_title),
        subtitle = directory?.name,
        floatingActionButton = {
            if (canEdit) {
                ExtendedFloatingActionButton(
                    text = { Text(stringResource(R.string.files_grant_access)) },
                    icon = { Icon(AppIcons.PersonAdd, contentDescription = null) },
                    onClick = openPicker,
                )
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            if (runner.isBusy) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
            }
            LoadableContent(loadable = page, modifier = Modifier.weight(1f)) { pageData ->
                val current = pageData.active
                if (current == null || current.id != directoryId) {
                    EmptyState(
                        title = stringResource(R.string.files_unavailable_title),
                        message = stringResource(R.string.files_unavailable_message),
                        icon = AppIcons.Lock,
                    )
                } else if (current.permissions.isEmpty()) {
                    EmptyState(
                        title = stringResource(R.string.files_no_permissions),
                        message = current.ownerName?.let { owner -> stringResource(R.string.files_owner_line, owner) },
                        icon = AppIcons.Rights,
                        actionLabel = if (current.canEdit) stringResource(R.string.files_grant_access) else null,
                        onAction = if (current.canEdit) openPicker else null,
                    )
                } else {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 96.dp),
                    ) {
                        val owner = current.ownerName
                        if (owner != null) {
                            item(key = "owner") {
                                KeyValueRow(label = stringResource(R.string.files_owner), value = owner, icon = AppIcons.Person)
                                RowDivider()
                            }
                        }
                        items(current.permissions, key = { it.id }) { permission ->
                            PermissionRow(
                                permission = permission,
                                canEdit = current.canEdit,
                                onChangeLevel = {
                                    pendingLevel = permission.accessLevel
                                    pendingSubject = Option(
                                        value = permission.subjectType + ":" + permission.subjectId,
                                        label = permission.subjectName ?: (if (permission.isGroup) groupLabel else userLabel),
                                    )
                                },
                                onRemove = { permissionToRemove = permission },
                            )
                            RowDivider()
                        }
                    }
                }
            }
        }
    }

    if (pickSubject) {
        val options = data?.users.orEmpty().map { user ->
            Option(value = FilePermission.SUBJECT_USER + ":" + user.id, label = user.name, subtitle = user.email ?: userLabel)
        } + data?.groups.orEmpty().map { group ->
            Option(value = FilePermission.SUBJECT_GROUP + ":" + group.id, label = group.name, subtitle = groupLabel)
        }
        OptionPickerDialog(
            title = stringResource(R.string.files_select_subject),
            options = options,
            selected = null,
            allowClear = false,
            onDismiss = { pickSubject = false },
            onSelect = { option ->
                pickSubject = false
                pendingLevel = null
                pendingSubject = option
            },
        )
    }

    val subject = pendingSubject
    if (subject != null) {
        OptionPickerDialog(
            title = stringResource(R.string.files_access_level_for, subject.label),
            options = listOf(
                Option(value = FilesAccess.READ, label = stringResource(R.string.files_access_read)),
                Option(value = FilesAccess.EDIT, label = stringResource(R.string.files_access_edit)),
            ),
            selected = pendingLevel,
            allowClear = false,
            onDismiss = { pendingSubject = null },
            onSelect = { level ->
                pendingSubject = null
                if (level != null) {
                    submitGrant(subject, level.value)
                }
            },
        )
    }

    val removing = permissionToRemove
    if (removing != null) {
        ConfirmDialog(
            title = stringResource(R.string.files_remove_access_title),
            text = removing.subjectName,
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { permissionToRemove = null },
            onConfirm = {
                runner.execute(successMessage = removedMessage, onSuccess = { _: Unit -> page.refresh() }) {
                    repository.revoke(directoryId, removing.id)
                }
            },
        )
    }
}

@Composable
private fun PermissionRow(
    permission: FilePermission,
    canEdit: Boolean,
    onChangeLevel: () -> Unit,
    onRemove: () -> Unit,
) {
    val details = listOfNotNull(
        stringResource(if (permission.isGroup) R.string.files_subject_group else R.string.files_subject_user),
        stringResource(accessLabel(permission.accessLevel)),
        permission.grantedByName?.let { name -> stringResource(R.string.files_granted_by, name) },
    ).joinToString(" · ")
    ListRow(
        title = permission.subjectName ?: stringResource(if (permission.isGroup) R.string.files_subject_group else R.string.files_subject_user),
        subtitle = details,
        leading = {
            Icon(
                imageVector = if (permission.isGroup) AppIcons.Group else AppIcons.Person,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
            )
        },
        trailing = if (canEdit) {
            {
                IconButton(onClick = onRemove) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.files_remove_access))
                }
            }
        } else {
            null
        },
        onClick = if (canEdit) onChangeLevel else null,
    )
}
