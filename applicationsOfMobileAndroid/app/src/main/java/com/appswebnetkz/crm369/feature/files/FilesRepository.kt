package com.appswebnetkz.crm369.feature.files

import android.content.Context
import android.net.Uri
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.UploadPart
import kotlinx.serialization.json.JsonObject

/** `DirPerm`: access of a user or a group to a directory. */
data class FilePermission(
    val id: Long,
    val accessLevel: String,
    val subjectType: String,
    val subjectId: Long,
    val subjectName: String?,
    val grantedByName: String?,
    val createdAt: String?,
) {
    val isGroup: Boolean get() = subjectType == SUBJECT_GROUP

    companion object {
        const val SUBJECT_USER = "user"
        const val SUBJECT_GROUP = "group"

        fun fromJson(json: JsonObject): FilePermission = FilePermission(
            id = json.long("id") ?: 0L,
            accessLevel = json.text("access_level") ?: FilesAccess.READ,
            subjectType = json.text("subject_type") ?: SUBJECT_USER,
            subjectId = json.long("subject_id") ?: 0L,
            subjectName = json.text("subject_name"),
            grantedByName = json.text("granted_by_name"),
            createdAt = json.text("created_at"),
        )
    }
}

/** `TreeDir`: a directory (recursive). */
data class FileFolder(
    val id: Long,
    val name: String,
    val parentId: Long?,
    val canEdit: Boolean,
    val permissionLevel: String,
    val childrenCount: Int,
    val filesCount: Int,
    val children: List<FileFolder>,
) {
    companion object {
        fun fromJson(json: JsonObject): FileFolder = FileFolder(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            parentId = json.long("parent_id"),
            canEdit = json.boolOr("can_edit", false),
            permissionLevel = json.text("permission_level") ?: FilesAccess.READ,
            childrenCount = json.int("children_count") ?: 0,
            filesCount = json.int("files_count") ?: 0,
            children = json.objects("children").map { fromJson(it) },
        )
    }
}

/** `FileEntry`. Download with `mobile_download_url` (API URL, bearer); `download_url` is the web URL. */
data class FileEntry(
    val id: Long,
    val originalName: String,
    val mimeType: String?,
    val extension: String?,
    val sizeBytes: Long,
    val ownerName: String?,
    val createdAt: String?,
    val downloadUrl: String,
) {
    companion object {
        fun fromJson(json: JsonObject): FileEntry {
            val id = json.long("id") ?: 0L
            return FileEntry(
                id = id,
                originalName = json.text("original_name") ?: "file",
                mimeType = json.text("mime_type"),
                extension = json.text("extension"),
                sizeBytes = json.long("size_bytes") ?: 0L,
                ownerName = json.text("owner_name"),
                createdAt = json.text("created_at"),
                downloadUrl = json.text("mobile_download_url") ?: "files/entries/$id/download",
            )
        }
    }
}

data class FileBreadcrumb(val id: Long, val name: String)

/** `ActiveDir`: the opened directory with its subdirectories, files and permissions. */
data class ActiveDirectory(
    val id: Long,
    val name: String,
    val parentId: Long?,
    val ownerName: String?,
    val permissionLevel: String,
    val canEdit: Boolean,
    val breadcrumbs: List<FileBreadcrumb>,
    val children: List<FileFolder>,
    val entries: List<FileEntry>,
    val permissions: List<FilePermission>,
) {
    companion object {
        fun fromJson(json: JsonObject): ActiveDirectory = ActiveDirectory(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            parentId = json.long("parent_id"),
            ownerName = json.obj("owner")?.text("name"),
            permissionLevel = json.text("permission_level") ?: FilesAccess.READ,
            canEdit = json.boolOr("can_edit", false),
            breadcrumbs = json.objects("breadcrumbs").map { FileBreadcrumb(id = it.long("id") ?: 0L, name = it.str("name").orEmpty()) },
            children = json.objects("children").map { FileFolder.fromJson(it) },
            entries = json.objects("entries").map { FileEntry.fromJson(it) },
            permissions = json.objects("permissions").map { FilePermission.fromJson(it) },
        )
    }
}

data class FileSubject(val id: Long, val name: String, val email: String?)

/** `FilesPage` (`GET files?directory=`). */
data class FilesPage(
    val tree: List<FileFolder>,
    val active: ActiveDirectory?,
    val users: List<FileSubject>,
    val groups: List<FileSubject>,
    val canCreateRoot: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject): FilesPage = FilesPage(
            tree = json.objects("tree").map { FileFolder.fromJson(it) },
            active = json.obj("activeDirectory")?.let { ActiveDirectory.fromJson(it) },
            users = json.objects("availableUsers").map {
                FileSubject(id = it.long("id") ?: 0L, name = it.text("name") ?: it.str("email").orEmpty(), email = it.text("email"))
            },
            groups = json.objects("availableGroups").map {
                FileSubject(id = it.long("id") ?: 0L, name = it.text("display_name") ?: it.str("name").orEmpty(), email = null)
            },
            canCreateRoot = json.obj("can").boolOr("createRoot", true),
        )
    }
}

/** Access levels of a directory permission. */
object FilesAccess {
    const val READ = "read"
    const val EDIT = "edit"
}

/** All file storage API calls (`/api/mobile/v1/files`). */
class FilesRepository(private val api: ApiClient) {

    /** Opens [directoryId] (null → the API opens the first readable root). */
    suspend fun page(directoryId: Long?): FilesPage =
        FilesPage.fromJson(api.get("files", mapOf("directory" to directoryId)).dataObject())

    suspend fun createDirectory(name: String, parentId: Long?) {
        api.post("files/directories", jsonObjectOf("name" to name, "parent_id" to parentId))
    }

    suspend fun deleteDirectory(directoryId: Long) {
        api.delete("files/directories/$directoryId")
    }

    /** Uploads one file (multipart `directory_id` + `file`). */
    suspend fun upload(context: Context, directoryId: Long, uri: Uri) {
        api.multipart(
            "files/entries",
            mapOf("directory_id" to directoryId.toString()),
            listOf(UploadPart.fromUri(context, "file", uri)),
        )
    }

    suspend fun deleteEntry(entryId: Long) {
        api.delete("files/entries/$entryId")
    }

    /** Grants (or changes) access of a user or a group. */
    suspend fun grantAccess(directoryId: Long, accessLevel: String, subjectType: String, subjectId: Long) {
        val subjectKey = if (subjectType == FilePermission.SUBJECT_GROUP) "user_group_id" else "user_id"
        api.post(
            "files/directories/$directoryId/permissions",
            jsonObjectOf("access_level" to accessLevel, subjectKey to subjectId),
        )
    }

    suspend fun revoke(directoryId: Long, permissionId: Long) {
        api.delete("files/directories/$directoryId/permissions/$permissionId")
    }
}
