package com.appswebnetkz.crm369.core.network

import android.content.ContentResolver
import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import java.io.File
import java.io.IOException
import okhttp3.MediaType
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.asRequestBody
import okio.BufferedSink
import okio.source

/** A file sent as a multipart form-data part (e.g. `avatar`, `file`, `attachments[]`). */
class UploadPart(
    val field: String,
    val fileName: String,
    val mimeType: String,
    val body: RequestBody,
    val size: Long = -1L,
) {
    companion object {
        /** Streams a document picked with the system picker (content:// Uri) without copying it. */
        fun fromUri(context: Context, field: String, uri: Uri): UploadPart {
            val resolver = context.contentResolver
            val info = queryFileInfo(resolver, uri)
            val mimeType = resolver.getType(uri) ?: guessMimeType(info.first)
            return UploadPart(
                field = field,
                fileName = info.first,
                mimeType = mimeType,
                body = ContentUriRequestBody(resolver, uri, mimeType.toMediaTypeOrNull(), info.second),
                size = info.second,
            )
        }

        fun fromFile(field: String, file: File, mimeType: String = guessMimeType(file.name)): UploadPart =
            UploadPart(
                field = field,
                fileName = file.name,
                mimeType = mimeType,
                body = file.asRequestBody(mimeType.toMediaTypeOrNull()),
                size = file.length(),
            )

        /** Display name and size of a content Uri. */
        fun queryFileInfo(resolver: ContentResolver, uri: Uri): Pair<String, Long> {
            var name: String? = null
            var size = -1L
            try {
                resolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)
                    ?.use { cursor ->
                        if (cursor.moveToFirst()) {
                            val nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                            val sizeIndex = cursor.getColumnIndex(OpenableColumns.SIZE)
                            if (nameIndex >= 0 && !cursor.isNull(nameIndex)) name = cursor.getString(nameIndex)
                            if (sizeIndex >= 0 && !cursor.isNull(sizeIndex)) size = cursor.getLong(sizeIndex)
                        }
                    }
            } catch (_: Exception) {
                // Some providers do not support the query; fall back to the Uri path segment.
            }
            val fallbackName = uri.lastPathSegment?.substringAfterLast('/')?.takeIf { it.isNotBlank() } ?: "file"
            return Pair(name?.takeIf { it.isNotBlank() } ?: fallbackName, size)
        }

        fun guessMimeType(fileName: String): String {
            val extension = fileName.substringAfterLast('.', "").lowercase()
            return when (extension) {
                "jpg", "jpeg" -> "image/jpeg"
                "png" -> "image/png"
                "webp" -> "image/webp"
                "gif" -> "image/gif"
                "heic" -> "image/heic"
                "pdf" -> "application/pdf"
                "doc" -> "application/msword"
                "docx" -> "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                "xls" -> "application/vnd.ms-excel"
                "xlsx" -> "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                "ppt" -> "application/vnd.ms-powerpoint"
                "pptx" -> "application/vnd.openxmlformats-officedocument.presentationml.presentation"
                "txt" -> "text/plain"
                "csv" -> "text/csv"
                "zip" -> "application/zip"
                "mp4" -> "video/mp4"
                "mp3" -> "audio/mpeg"
                "m4a" -> "audio/mp4"
                else -> "application/octet-stream"
            }
        }
    }
}

private class ContentUriRequestBody(
    private val resolver: ContentResolver,
    private val uri: Uri,
    private val mediaType: MediaType?,
    private val length: Long,
) : RequestBody() {
    override fun contentType(): MediaType? = mediaType

    override fun contentLength(): Long = length

    override fun writeTo(sink: BufferedSink) {
        val input = resolver.openInputStream(uri) ?: throw IOException("Cannot open $uri")
        input.use { stream -> sink.writeAll(stream.source()) }
    }
}
