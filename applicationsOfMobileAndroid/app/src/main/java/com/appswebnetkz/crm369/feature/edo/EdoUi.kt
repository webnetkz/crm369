package com.appswebnetkz.crm369.feature.edo

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.provider.OpenableColumns
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.core.util.describeError
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

@Composable
internal fun edoStatusLabel(status: String): String = when (status) {
    EdoDocument.STATUS_DRAFT -> stringResource(R.string.edo_status_draft)
    EdoDocument.STATUS_PENDING -> stringResource(R.string.edo_status_pending_signature)
    EdoDocument.STATUS_SIGNED -> stringResource(R.string.edo_status_signed)
    EdoDocument.STATUS_CANCELLED -> stringResource(R.string.edo_status_cancelled)
    else -> status
}

@Composable
internal fun edoStatusColor(status: String): Color = when (status) {
    EdoDocument.STATUS_SIGNED -> LocalExtendedColors.current.success
    EdoDocument.STATUS_PENDING -> LocalExtendedColors.current.warning
    EdoDocument.STATUS_CANCELLED -> MaterialTheme.colorScheme.error
    else -> MaterialTheme.colorScheme.secondary
}

@Composable
internal fun edoSourceLabel(source: String): String = when (source) {
    EdoDocument.SOURCE_UPLOAD -> stringResource(R.string.edo_source_upload)
    EdoDocument.SOURCE_FILE_ENTRY -> stringResource(R.string.edo_source_file_entry)
    else -> stringResource(R.string.edo_source_text)
}

/** Card of the form that shows the chosen file of an upload / file_entry document. */
@Composable
internal fun EdoSourceFileCard(
    title: String,
    fileName: String?,
    hint: String?,
    error: String?,
    buttonLabel: String,
    buttonIcon: ImageVector,
    onClick: () -> Unit,
) {
    InfoCard(title = title) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(AppIcons.File, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(12.dp))
            Text(
                text = fileName ?: stringResource(R.string.edo_no_file),
                style = MaterialTheme.typography.bodyMedium,
                color = if (fileName == null) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
        }
        if (hint != null) {
            Text(
                text = hint,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 16.dp),
            )
        }
        if (error != null) {
            Text(
                text = error,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.error,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
            )
        }
        OutlinedButton(onClick = onClick, modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) {
            Icon(buttonIcon, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.size(8.dp))
            Text(buttonLabel)
        }
    }
}

/** Simple browser over the Files module (`GET files?directory=`) to pick an existing file. */
@Composable
internal fun EdoFilePickerDialog(
    repository: EdoRepository,
    onSelect: (EdoFileEntry) -> Unit,
    onDismiss: () -> Unit,
) {
    val context = LocalContext.current
    var directoryId by remember { mutableStateOf<Long?>(null) }
    val page = rememberLoadable(repository, directoryId) { repository.files(directoryId) }
    val data = page.data
    val error = page.error
    val currentDirectory = directoryId

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(
                text = data?.directoryName ?: stringResource(R.string.edo_file_picker_title),
                maxLines = 2,
            )
        },
        text = {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 120.dp, max = 440.dp),
            ) {
                when {
                    data != null -> {
                        Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                            if (data.folders.isEmpty() && data.entries.isEmpty()) {
                                Text(
                                    text = stringResource(R.string.edo_file_picker_empty),
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    modifier = Modifier.padding(vertical = 16.dp),
                                )
                            }
                            data.folders.forEach { folder ->
                                ListRow(
                                    title = folder.name,
                                    subtitle = stringResource(R.string.edo_file_picker_folder_files, folder.filesCount),
                                    leading = { Icon(AppIcons.Folder, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
                                    onClick = { directoryId = folder.id },
                                )
                            }
                            data.entries.forEach { entry ->
                                ListRow(
                                    title = entry.name,
                                    subtitle = listOfNotNull(Formatters.fileSize(entry.sizeBytes).ifBlank { null }, entry.ownerName)
                                        .joinToString(" · ")
                                        .ifBlank { null },
                                    leading = { Icon(AppIcons.File, contentDescription = null) },
                                    onClick = { onSelect(entry) },
                                )
                            }
                        }
                    }
                    error != null -> Text(
                        text = describeError(context, error),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.error,
                    )
                    else -> CircularProgressIndicator(modifier = Modifier.align(Alignment.Center))
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) { Text(stringResource(R.string.action_cancel)) }
        },
        dismissButton = if (currentDirectory != null) {
            {
                TextButton(onClick = { directoryId = data?.parentId }) {
                    Text(stringResource(R.string.edo_file_picker_up))
                }
            }
        } else {
            null
        },
    )
}

/** Display name of a content Uri picked with the system file picker. */
internal fun uriDisplayName(context: Context, uri: Uri): String? = try {
    context.contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)?.use { cursor ->
        if (cursor.moveToFirst() && !cursor.isNull(0)) cursor.getString(0) else null
    } ?: uri.lastPathSegment
} catch (exception: Exception) {
    uri.lastPathSegment
}

/** Opens the system share sheet for a plain text (e.g. a signing link). */
internal fun shareText(context: Context, text: String) {
    val send = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_TEXT, text)
    }
    val chooser = Intent.createChooser(send, null).apply { addFlags(Intent.FLAG_ACTIVITY_NEW_TASK) }
    context.startActivity(chooser)
}
