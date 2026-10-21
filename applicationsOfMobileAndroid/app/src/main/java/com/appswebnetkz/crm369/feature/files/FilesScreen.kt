package com.appswebnetkz.crm369.feature.files

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.StringRes
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.AssistChip
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
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
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.TextInputDialog
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

/** Progress of a multi-file upload ([done] files finished out of [total]). */
private data class UploadProgress(val done: Int, val total: Int)

/** Folder selected for deletion; [isCurrent] = the opened folder itself. */
private data class FolderRef(val id: Long, val name: String, val parentId: Long?, val isCurrent: Boolean)

/**
 * File storage. Without [directoryId] the root folders (`tree`) are listed; with it the folder is
 * opened (`GET files?directory=`): breadcrumbs, subfolders, files, upload, folders and access.
 */
@Composable
fun FilesScreen(directoryId: Long?) {
    val api = rememberApi()
    val context = LocalContext.current
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { FilesRepository(api) }
    val runner = rememberActionRunner()
    val scope = rememberCoroutineScope()
    val page = rememberLoadable(api, directoryId) { repository.page(directoryId) }
    var menuOpen by remember { mutableStateOf(false) }
    var showCreateFolder by remember { mutableStateOf(false) }
    var folderToDelete by remember { mutableStateOf<FolderRef?>(null) }
    var entryToDelete by remember { mutableStateOf<FileEntry?>(null) }
    var upload by remember { mutableStateOf<UploadProgress?>(null) }

    val data = page.data
    val active = data?.active?.takeIf { directoryId != null && it.id == directoryId }
    val canEdit = active?.canEdit == true
    val canCreateFolder = if (directoryId == null) data?.canCreateRoot == true else canEdit

    val folderCreatedMessage = stringResource(R.string.files_folder_created)
    val folderDeletedMessage = stringResource(R.string.files_folder_deleted)
    val fileDeletedMessage = stringResource(R.string.files_file_deleted)
    val uploadedOneMessage = stringResource(R.string.files_file_uploaded)

    val uploadPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetMultipleContents()) { uris ->
        val targetId = active?.id
        if (uris.isNotEmpty() && targetId != null && upload == null) {
            scope.launch {
                var uploaded = 0
                try {
                    uris.forEachIndexed { index, uri ->
                        upload = UploadProgress(done = index, total = uris.size)
                        try {
                            repository.upload(context, targetId, uri)
                            uploaded += 1
                        } catch (exception: CancellationException) {
                            throw exception
                        } catch (exception: Exception) {
                            messenger.showError(exception)
                        }
                    }
                } finally {
                    upload = null
                }
                if (uploaded > 0) {
                    messenger.show(if (uploaded == 1) uploadedOneMessage else context.getString(R.string.files_files_uploaded, uploaded))
                    page.refresh()
                }
            }
        }
    }
    val startUpload: () -> Unit = {
        if (upload == null) {
            uploadPicker.launch("*/*")
        }
    }
    val openEntry: (FileEntry) -> Unit = { entry ->
        runner.execute { container.fileTransfer.downloadAndOpen(api, entry.downloadUrl, entry.originalName) }
    }
    val shareEntry: (FileEntry) -> Unit = { entry ->
        runner.execute { container.fileTransfer.downloadAndShare(api, entry.downloadUrl, entry.originalName) }
    }
    val openFolder: (Long) -> Unit = { id -> navigator.open(FilesRoutes.directory(id)) }
    val askDeleteFolder: (FileFolder) -> Unit = { folder ->
        folderToDelete = FolderRef(id = folder.id, name = folder.name, parentId = folder.parentId, isCurrent = false)
    }

    fun afterFolderDeleted(folder: FolderRef) {
        if (!folder.isCurrent) {
            page.refresh()
        } else if (!navigator.back()) {
            navigator.replace(FilesRoutes.directory(folder.parentId))
        }
    }

    CrmScaffold(
        title = active?.name ?: stringResource(R.string.module_files),
        subtitle = if (active != null) directorySubtitle(active) else null,
        actions = {
            if (canCreateFolder) {
                IconButton(onClick = { showCreateFolder = true }) {
                    Icon(AppIcons.CreateFolder, contentDescription = stringResource(R.string.files_create_folder))
                }
            }
            if (active != null) {
                Box {
                    IconButton(onClick = { menuOpen = true }) {
                        Icon(AppIcons.More, contentDescription = stringResource(R.string.files_actions))
                    }
                    DropdownMenu(expanded = menuOpen, onDismissRequest = { menuOpen = false }) {
                        DropdownMenuItem(
                            text = { Text(stringResource(R.string.files_access_title)) },
                            leadingIcon = { Icon(AppIcons.Rights, contentDescription = null) },
                            onClick = {
                                menuOpen = false
                                navigator.open(FilesRoutes.access(active.id))
                            },
                        )
                        if (canEdit) {
                            DropdownMenuItem(
                                text = { Text(stringResource(R.string.files_delete_folder)) },
                                leadingIcon = { Icon(AppIcons.Delete, contentDescription = null) },
                                onClick = {
                                    menuOpen = false
                                    folderToDelete = FolderRef(id = active.id, name = active.name, parentId = active.parentId, isCurrent = true)
                                },
                            )
                        }
                    }
                }
            }
        },
        floatingActionButton = {
            if (active != null && canEdit) {
                ExtendedFloatingActionButton(
                    text = { Text(stringResource(R.string.files_upload)) },
                    icon = { Icon(AppIcons.Upload, contentDescription = null) },
                    onClick = startUpload,
                    expanded = upload == null,
                )
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            val progress = upload
            if (progress != null) {
                UploadBanner(progress = progress)
            } else if (runner.isBusy) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
            }
            LoadableContent(loadable = page, modifier = Modifier.weight(1f)) { pageData ->
                if (directoryId == null) {
                    RootFolders(
                        page = pageData,
                        onOpen = openFolder,
                        onCreate = { showCreateFolder = true },
                        onDelete = askDeleteFolder,
                    )
                } else {
                    val current = pageData.active
                    if (current == null || current.id != directoryId) {
                        EmptyState(
                            title = stringResource(R.string.files_unavailable_title),
                            message = stringResource(R.string.files_unavailable_message),
                            icon = AppIcons.Lock,
                            actionLabel = stringResource(R.string.files_open_root),
                            onAction = { navigator.replace(FilesRoutes.directory()) },
                        )
                    } else {
                        DirectoryContent(
                            directory = current,
                            onOpenFolder = openFolder,
                            onOpenFile = openEntry,
                            onShareFile = shareEntry,
                            onDeleteFolder = askDeleteFolder,
                            onDeleteFile = { entry -> entryToDelete = entry },
                            onUpload = startUpload,
                        )
                    }
                }
            }
        }
    }

    if (showCreateFolder) {
        TextInputDialog(
            title = stringResource(if (directoryId == null) R.string.files_create_root_folder else R.string.files_create_folder),
            label = stringResource(R.string.files_folder_name),
            confirmLabel = stringResource(R.string.files_create),
            onDismiss = { showCreateFolder = false },
            onConfirm = { name ->
                val parentId = active?.id
                runner.execute(successMessage = folderCreatedMessage, onSuccess = { _: Unit -> page.refresh() }) {
                    repository.createDirectory(name, parentId)
                }
            },
        )
    }

    val pendingFolder = folderToDelete
    if (pendingFolder != null) {
        ConfirmDialog(
            title = stringResource(R.string.files_delete_folder_title, pendingFolder.name),
            text = stringResource(R.string.files_delete_folder_text),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { folderToDelete = null },
            onConfirm = {
                runner.execute(successMessage = folderDeletedMessage, onSuccess = { _: Unit -> afterFolderDeleted(pendingFolder) }) {
                    repository.deleteDirectory(pendingFolder.id)
                }
            },
        )
    }

    val pendingEntry = entryToDelete
    if (pendingEntry != null) {
        ConfirmDialog(
            title = stringResource(R.string.files_delete_file_title),
            text = pendingEntry.originalName,
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { entryToDelete = null },
            onConfirm = {
                runner.execute(successMessage = fileDeletedMessage, onSuccess = { _: Unit -> page.refresh() }) {
                    repository.deleteEntry(pendingEntry.id)
                }
            },
        )
    }
}

@Composable
private fun directorySubtitle(directory: ActiveDirectory): String {
    val parents = directory.breadcrumbs.dropLast(1).joinToString(" / ") { it.name }
    val access = stringResource(accessLabel(directory.permissionLevel))
    return if (parents.isBlank()) access else "$parents · $access"
}

@Composable
private fun UploadBanner(progress: UploadProgress) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Text(
            text = stringResource(R.string.files_uploading, progress.done + 1, progress.total),
            style = MaterialTheme.typography.bodyMedium,
        )
        LinearProgressIndicator(
            progress = { progress.done.toFloat() / progress.total.coerceAtLeast(1) },
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun RootFolders(
    page: FilesPage,
    onOpen: (Long) -> Unit,
    onCreate: () -> Unit,
    onDelete: (FileFolder) -> Unit,
) {
    if (page.tree.isEmpty()) {
        EmptyState(
            title = stringResource(R.string.files_empty_title),
            message = stringResource(R.string.files_empty_message),
            icon = AppIcons.Files,
            actionLabel = if (page.canCreateRoot) stringResource(R.string.files_create_folder) else null,
            onAction = if (page.canCreateRoot) onCreate else null,
        )
    } else {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 88.dp),
        ) {
            item(key = "roots") { SectionHeader(title = stringResource(R.string.files_root_folders)) }
            items(page.tree, key = { "folder-${it.id}" }) { folder ->
                FolderRow(
                    folder = folder,
                    onOpen = { onOpen(folder.id) },
                    onDelete = if (folder.canEdit) {
                        { onDelete(folder) }
                    } else {
                        null
                    },
                )
                RowDivider()
            }
        }
    }
}

@Composable
private fun DirectoryContent(
    directory: ActiveDirectory,
    onOpenFolder: (Long) -> Unit,
    onOpenFile: (FileEntry) -> Unit,
    onShareFile: (FileEntry) -> Unit,
    onDeleteFolder: (FileFolder) -> Unit,
    onDeleteFile: (FileEntry) -> Unit,
    onUpload: () -> Unit,
) {
    if (directory.children.isEmpty() && directory.entries.isEmpty()) {
        EmptyState(
            title = stringResource(R.string.files_folder_empty_title),
            message = stringResource(if (directory.canEdit) R.string.files_folder_empty_message else R.string.files_readonly_notice),
            icon = AppIcons.FolderOpen,
            actionLabel = if (directory.canEdit) stringResource(R.string.files_upload) else null,
            onAction = if (directory.canEdit) onUpload else null,
        )
    } else {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 96.dp),
        ) {
            if (directory.breadcrumbs.size > 1) {
                item(key = "breadcrumbs") {
                    Breadcrumbs(crumbs = directory.breadcrumbs, currentId = directory.id, onOpen = onOpenFolder)
                }
            }
            if (!directory.canEdit) {
                item(key = "readonly") {
                    Text(
                        text = stringResource(R.string.files_readonly_notice),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    )
                }
            }
            if (directory.children.isNotEmpty()) {
                item(key = "folders") { SectionHeader(title = stringResource(R.string.files_folders)) }
                items(directory.children, key = { "folder-${it.id}" }) { folder ->
                    FolderRow(
                        folder = folder,
                        onOpen = { onOpenFolder(folder.id) },
                        onDelete = if (folder.canEdit) {
                            { onDeleteFolder(folder) }
                        } else {
                            null
                        },
                    )
                    RowDivider()
                }
            }
            if (directory.entries.isNotEmpty()) {
                item(key = "files") { SectionHeader(title = stringResource(R.string.files_files)) }
                items(directory.entries, key = { "file-${it.id}" }) { entry ->
                    FileRow(
                        entry = entry,
                        canDelete = directory.canEdit,
                        onOpen = { onOpenFile(entry) },
                        onShare = { onShareFile(entry) },
                        onDelete = { onDeleteFile(entry) },
                    )
                    RowDivider()
                }
            }
        }
    }
}

@Composable
private fun Breadcrumbs(crumbs: List<FileBreadcrumb>, currentId: Long, onOpen: (Long) -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 16.dp, vertical = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        crumbs.forEach { crumb ->
            AssistChip(
                onClick = { onOpen(crumb.id) },
                enabled = crumb.id != currentId,
                label = { Text(crumb.name) },
                leadingIcon = { Icon(AppIcons.Folder, contentDescription = null, modifier = Modifier.size(18.dp)) },
            )
        }
    }
}

@Composable
private fun FolderRow(folder: FileFolder, onOpen: () -> Unit, onDelete: (() -> Unit)?) {
    val details = listOfNotNull(
        stringResource(R.string.files_files_count, folder.filesCount),
        if (folder.childrenCount > 0) stringResource(R.string.files_folders_count, folder.childrenCount) else null,
        stringResource(accessLabel(folder.permissionLevel)),
    ).joinToString(" · ")
    ListRow(
        title = folder.name,
        subtitle = details,
        leading = { Icon(AppIcons.Folder, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
        trailing = if (onDelete != null) {
            {
                RowMenu { close ->
                    DropdownMenuItem(
                        text = { Text(stringResource(R.string.files_open)) },
                        leadingIcon = { Icon(AppIcons.FolderOpen, contentDescription = null) },
                        onClick = {
                            close()
                            onOpen()
                        },
                    )
                    DropdownMenuItem(
                        text = { Text(stringResource(R.string.action_delete)) },
                        leadingIcon = { Icon(AppIcons.Delete, contentDescription = null) },
                        onClick = {
                            close()
                            onDelete()
                        },
                    )
                }
            }
        } else {
            null
        },
        onClick = onOpen,
    )
}

@Composable
private fun FileRow(
    entry: FileEntry,
    canDelete: Boolean,
    onOpen: () -> Unit,
    onShare: () -> Unit,
    onDelete: () -> Unit,
) {
    val details = listOfNotNull(
        Formatters.fileSize(entry.sizeBytes).ifBlank { null },
        entry.ownerName,
        Formatters.date(entry.createdAt).ifBlank { null },
    ).joinToString(" · ")
    ListRow(
        title = entry.originalName,
        subtitle = details.ifBlank { null },
        leading = { Icon(fileIcon(entry), contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
        trailing = {
            RowMenu { close ->
                DropdownMenuItem(
                    text = { Text(stringResource(R.string.files_open)) },
                    leadingIcon = { Icon(AppIcons.Visibility, contentDescription = null) },
                    onClick = {
                        close()
                        onOpen()
                    },
                )
                DropdownMenuItem(
                    text = { Text(stringResource(R.string.files_share)) },
                    leadingIcon = { Icon(AppIcons.Share, contentDescription = null) },
                    onClick = {
                        close()
                        onShare()
                    },
                )
                if (canDelete) {
                    DropdownMenuItem(
                        text = { Text(stringResource(R.string.action_delete)) },
                        leadingIcon = { Icon(AppIcons.Delete, contentDescription = null) },
                        onClick = {
                            close()
                            onDelete()
                        },
                    )
                }
            }
        },
        onClick = onOpen,
    )
}

/** Overflow button with a dropdown; [content] receives a callback that closes the menu. */
@Composable
private fun RowMenu(content: @Composable (close: () -> Unit) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    Box {
        IconButton(onClick = { expanded = true }) {
            Icon(AppIcons.More, contentDescription = stringResource(R.string.files_actions))
        }
        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            content { expanded = false }
        }
    }
}

@StringRes
internal fun accessLabel(level: String): Int =
    if (level == FilesAccess.EDIT) R.string.files_access_edit else R.string.files_access_read

private val IMAGE_EXTENSIONS = setOf("jpg", "jpeg", "png", "gif", "webp", "bmp", "svg", "heic", "heif", "tif", "tiff")
private val VIDEO_EXTENSIONS = setOf("mp4", "mov", "avi", "mkv", "webm", "3gp", "m4v", "wmv")
private val AUDIO_EXTENSIONS = setOf("mp3", "wav", "ogg", "m4a", "aac", "flac", "opus", "wma")
private val SHEET_EXTENSIONS = setOf("xls", "xlsx", "xlsm", "csv", "ods", "numbers")
private val ARCHIVE_EXTENSIONS = setOf("zip", "rar", "7z", "tar", "gz", "tgz", "bz2", "xz")
private val DOCUMENT_EXTENSIONS = setOf("doc", "docx", "odt", "rtf", "txt", "md", "ppt", "pptx", "odp", "pages", "key", "xml", "json", "html")

/** Icon of a file by its extension / MIME type. */
internal fun fileIcon(entry: FileEntry): ImageVector {
    val extension = (entry.extension ?: entry.originalName.substringAfterLast('.', "")).lowercase()
    val mime = entry.mimeType.orEmpty().lowercase()
    return when {
        extension == "pdf" || mime == "application/pdf" -> FilesIcons.Pdf
        mime.startsWith("image/") || extension in IMAGE_EXTENSIONS -> AppIcons.Image
        mime.startsWith("video/") || extension in VIDEO_EXTENSIONS -> AppIcons.Video
        mime.startsWith("audio/") || extension in AUDIO_EXTENSIONS -> AppIcons.Mic
        extension in SHEET_EXTENSIONS -> AppIcons.Assessment
        extension in ARCHIVE_EXTENSIONS -> AppIcons.Archive
        extension in DOCUMENT_EXTENSIONS || mime.startsWith("text/") -> AppIcons.Document
        else -> AppIcons.File
    }
}
