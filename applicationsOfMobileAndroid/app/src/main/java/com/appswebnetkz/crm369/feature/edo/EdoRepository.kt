package com.appswebnetkz.crm369.feature.edo

import android.content.Context
import android.net.Uri
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.dataObjects
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.json.toMultipartFields
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.UploadPart
import com.appswebnetkz.crm369.core.util.Formatters
import kotlinx.serialization.json.JsonObject

/** File attached to an EDO document (`document_file`). */
data class EdoDocumentFile(
    val name: String,
    val mimeType: String?,
    val sizeBytes: Long?,
    val mobileDownloadUrl: String?,
) {
    companion object {
        fun fromJson(json: JsonObject?): EdoDocumentFile? {
            if (json == null) return null
            return EdoDocumentFile(
                name = json.text("original_name").orEmpty(),
                mimeType = json.text("mime_type"),
                sizeBytes = json.long("size_bytes"),
                mobileDownloadUrl = json.text("mobile_download_url"),
            )
        }
    }
}

/** `EdoDoc` of the mobile API. */
data class EdoDocument(
    val id: Long,
    val title: String,
    val externalReference: String?,
    val counterpartyName: String,
    val counterpartyIdentifier: String?,
    val counterpartyEmail: String?,
    val content: String?,
    val documentSource: String,
    val sourceFileEntryId: Long?,
    val file: EdoDocumentFile?,
    val status: String,
    val publicSignUrl: String?,
    val publicSignExpiresAt: String?,
    val hasActivePublicLink: Boolean,
    val signatureSubject: String?,
    val signatureSerialNumber: String?,
    val signatureAlgorithm: String?,
    val signedPayloadHash: String?,
    val signedAt: String?,
    val metadata: JsonObject?,
    val createdAt: String?,
    val updatedAt: String?,
    val creatorName: String?,
    val updaterName: String?,
) {
    val isSigned: Boolean get() = status == STATUS_SIGNED

    companion object {
        const val STATUS_DRAFT = "draft"
        const val STATUS_PENDING = "pending_signature"
        const val STATUS_SIGNED = "signed"
        const val STATUS_CANCELLED = "cancelled"

        const val SOURCE_TEXT = "text"
        const val SOURCE_UPLOAD = "upload"
        const val SOURCE_FILE_ENTRY = "file_entry"

        val STATUSES: List<String> = listOf(STATUS_DRAFT, STATUS_PENDING, STATUS_SIGNED, STATUS_CANCELLED)
        val SOURCES: List<String> = listOf(SOURCE_TEXT, SOURCE_UPLOAD, SOURCE_FILE_ENTRY)

        fun fromJson(json: JsonObject): EdoDocument = EdoDocument(
            id = json.long("id") ?: 0L,
            title = json.str("title").orEmpty(),
            externalReference = json.text("external_reference"),
            counterpartyName = json.str("counterparty_name").orEmpty(),
            counterpartyIdentifier = json.text("counterparty_identifier"),
            counterpartyEmail = json.text("counterparty_email"),
            content = json.text("content"),
            documentSource = json.text("document_source") ?: SOURCE_TEXT,
            sourceFileEntryId = json.long("source_file_entry_id"),
            file = EdoDocumentFile.fromJson(json.obj("document_file")),
            status = json.text("status") ?: STATUS_DRAFT,
            publicSignUrl = json.text("public_sign_url"),
            publicSignExpiresAt = json.text("public_sign_expires_at"),
            hasActivePublicLink = json.boolOr("has_active_public_link", false),
            signatureSubject = json.text("signature_subject"),
            signatureSerialNumber = json.text("signature_serial_number"),
            signatureAlgorithm = json.text("signature_algorithm"),
            signedPayloadHash = json.text("signed_payload_hash"),
            signedAt = json.text("signed_at"),
            metadata = json.obj("metadata"),
            createdAt = json.text("created_at"),
            updatedAt = json.text("updated_at"),
            creatorName = personName(json.obj("creator")),
            updaterName = personName(json.obj("updater")),
        )

        private fun personName(json: JsonObject?): String? {
            if (json == null) return null
            return Formatters.fullName(json.str("name"), json.str("last_name")).ifBlank { null } ?: json.text("email")
        }
    }
}

/** Folder of the Files module shown in the EDO file picker. */
data class EdoFileFolder(
    val id: Long,
    val name: String,
    val filesCount: Int,
    val childrenCount: Int,
) {
    companion object {
        fun fromJson(json: JsonObject): EdoFileFolder = EdoFileFolder(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            filesCount = json.int("files_count") ?: 0,
            childrenCount = json.int("children_count") ?: 0,
        )
    }
}

/** File of the Files module that can become the source of an EDO document. */
data class EdoFileEntry(
    val id: Long,
    val name: String,
    val sizeBytes: Long?,
    val ownerName: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): EdoFileEntry = EdoFileEntry(
            id = json.long("id") ?: 0L,
            name = json.str("original_name").orEmpty(),
            sizeBytes = json.long("size_bytes"),
            ownerName = json.text("owner_name"),
        )
    }
}

/** One level of the Files tree: the root folders ([directoryId] = null) or one folder with its files. */
data class EdoFilesPage(
    val directoryId: Long?,
    val directoryName: String?,
    val parentId: Long?,
    val folders: List<EdoFileFolder>,
    val entries: List<EdoFileEntry>,
)

/** EDO API (`/api/mobile/v1/edo/documents`) and the Files listing used by the file picker. */
class EdoRepository(private val api: ApiClient) {

    suspend fun documents(): List<EdoDocument> =
        api.get("edo/documents").dataObjects().map { EdoDocument.fromJson(it) }

    suspend fun document(id: Long): EdoDocument =
        EdoDocument.fromJson(api.get("edo/documents/$id").dataObject())

    /** Creates a document; [upload] (content Uri) switches the request to multipart `document_upload`. */
    suspend fun create(context: Context, body: JsonObject, upload: Uri?): EdoDocument {
        val response = if (upload != null) {
            api.multipart(
                "edo/documents",
                body.toMultipartFields(),
                listOf(UploadPart.fromUri(context, "document_upload", upload)),
            )
        } else {
            api.post("edo/documents", body)
        }
        return EdoDocument.fromJson(response.dataObject())
    }

    /** Full update; without a new [upload] the server keeps the existing file of an upload document. */
    suspend fun update(context: Context, id: Long, body: JsonObject, upload: Uri?): EdoDocument {
        val response = if (upload != null) {
            api.multipart(
                "edo/documents/$id",
                body.toMultipartFields(),
                listOf(UploadPart.fromUri(context, "document_upload", upload)),
                methodOverride = "PATCH",
            )
        } else {
            api.patch("edo/documents/$id", body)
        }
        return EdoDocument.fromJson(response.dataObject())
    }

    suspend fun delete(id: Long) {
        api.delete("edo/documents/$id")
    }

    /** Issues a new public signing link (valid for 12 hours). */
    suspend fun createPublicLink(id: Long): EdoDocument =
        EdoDocument.fromJson(api.post("edo/documents/$id/public-link").dataObject())

    /** API URL (or path) of the attached file. */
    fun downloadPath(document: EdoDocument): String =
        document.file?.mobileDownloadUrl ?: "edo/documents/${document.id}/file"

    /** Root folders when [directoryId] is null, otherwise the folder content (`GET files?directory=`). */
    suspend fun files(directoryId: Long?): EdoFilesPage {
        val page = api.get("files", mapOf("directory" to directoryId)).dataObject()
        val roots = page.objects("tree").map { EdoFileFolder.fromJson(it) }
        val active = page.obj("activeDirectory")
        if (directoryId == null || active == null) {
            return EdoFilesPage(directoryId = null, directoryName = null, parentId = null, folders = roots, entries = emptyList())
        }
        return EdoFilesPage(
            directoryId = active.long("id"),
            directoryName = active.text("name"),
            parentId = active.long("parent_id"),
            folders = active.objects("children").map { EdoFileFolder.fromJson(it) },
            entries = active.objects("entries").map { EdoFileEntry.fromJson(it) },
        )
    }
}
