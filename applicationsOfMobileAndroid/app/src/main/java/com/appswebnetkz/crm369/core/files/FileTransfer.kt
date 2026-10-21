package com.appswebnetkz.crm369.core.files

import android.content.ActivityNotFoundException
import android.content.ClipData
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.webkit.MimeTypeMap
import androidx.core.content.FileProvider
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.DownloadedFile
import java.io.File

/**
 * Downloads protected portal files (with the bearer token) into the cache and hands them to
 * other applications through the [FileProvider] (`${applicationId}.files`).
 */
class FileTransfer(private val context: Context) {

    private val downloadsDirectory: File get() = File(context.cacheDir, "downloads")

    /** Downloads [pathOrUrl] (absolute URL, portal path or API path) into the cache. */
    suspend fun download(api: ApiClient, pathOrUrl: String, fallbackName: String = "file"): DownloadedFile {
        cleanupOldFiles()
        return api.download(pathOrUrl, File(downloadsDirectory, System.currentTimeMillis().toString()), fallbackName)
    }

    /** Downloads the file and opens it with a suitable application. */
    suspend fun downloadAndOpen(api: ApiClient, pathOrUrl: String, fallbackName: String = "file"): DownloadedFile {
        val downloaded = download(api, pathOrUrl, fallbackName)
        this.open(downloaded)
        return downloaded
    }

    /** Downloads the file and opens the share sheet. */
    suspend fun downloadAndShare(api: ApiClient, pathOrUrl: String, fallbackName: String = "file"): DownloadedFile {
        val downloaded = download(api, pathOrUrl, fallbackName)
        share(downloaded)
        return downloaded
    }

    fun uriFor(file: File): Uri =
        FileProvider.getUriForFile(context, context.packageName + ".files", file)

    fun open(downloaded: DownloadedFile) {
        val uri = uriFor(downloaded.file)
        val mimeType = resolveMimeType(downloaded)
        val viewIntent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(uri, mimeType)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        val chooser = Intent.createChooser(viewIntent, context.getString(R.string.file_open_with)).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        try {
            context.startActivity(chooser)
        } catch (_: ActivityNotFoundException) {
            share(downloaded)
        }
    }

    fun share(downloaded: DownloadedFile) {
        val uri = uriFor(downloaded.file)
        val sendIntent = Intent(Intent.ACTION_SEND).apply {
            type = resolveMimeType(downloaded)
            putExtra(Intent.EXTRA_STREAM, uri)
            clipData = ClipData.newRawUri(downloaded.fileName, uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        val chooser = Intent.createChooser(sendIntent, context.getString(R.string.file_share)).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        context.startActivity(chooser)
    }

    private fun resolveMimeType(downloaded: DownloadedFile): String {
        val fromServer = downloaded.mimeType?.takeIf { it.isNotBlank() && it != "application/octet-stream" }
        if (fromServer != null) return fromServer
        val extension = downloaded.fileName.substringAfterLast('.', "").lowercase()
        return MimeTypeMap.getSingleton().getMimeTypeFromExtension(extension) ?: "*/*"
    }

    private fun cleanupOldFiles() {
        val threshold = System.currentTimeMillis() - MAX_CACHE_AGE_MILLIS
        downloadsDirectory.listFiles()?.forEach { entry ->
            if (entry.lastModified() < threshold) {
                entry.deleteRecursively()
            }
        }
    }

    private companion object {
        const val MAX_CACHE_AGE_MILLIS = 24L * 60L * 60L * 1000L
    }
}
