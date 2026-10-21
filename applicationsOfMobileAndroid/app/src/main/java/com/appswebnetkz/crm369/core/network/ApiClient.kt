package com.appswebnetkz.crm369.core.network

import com.appswebnetkz.crm369.BuildConfig
import com.appswebnetkz.crm369.core.json.EmptyJsonObject
import com.appswebnetkz.crm369.core.json.parseJsonObject
import java.io.File
import java.io.IOException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.JsonObject
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response

/** A file downloaded from the portal. */
data class DownloadedFile(
    val file: File,
    val fileName: String,
    val mimeType: String?,
)

/**
 * HTTP client bound to one portal account.
 *
 * Paths are relative to `<portal>/api/mobile/v1/`, e.g. `api.get("contacts", mapOf("page" to 2))`.
 * Every call runs on [Dispatchers.IO] and returns the JSON root object (an empty object when the
 * response has no body). Errors are thrown as [ApiException] (HTTP errors) or
 * [NetworkException] (connectivity problems).
 */
class ApiClient(
    private val httpClient: OkHttpClient,
    val baseUrl: String,
    private val tokenProvider: () -> String?,
    private val languageProvider: () -> String,
    private val onUnauthorized: () -> Unit = {},
) {
    val apiRoot: String = baseUrl.trimEnd('/') + "/api/mobile/v1/"

    private val baseHost: String? = baseUrl.toHttpUrlOrNull()?.host

    suspend fun get(path: String, query: Map<String, Any?> = emptyMap()): JsonObject =
        execute(newRequest(apiUrl(path, query)).get().build())

    suspend fun post(path: String, body: JsonObject? = null): JsonObject =
        execute(newRequest(apiUrl(path)).post(jsonBody(body)).build())

    suspend fun put(path: String, body: JsonObject? = null): JsonObject =
        execute(newRequest(apiUrl(path)).put(jsonBody(body)).build())

    suspend fun patch(path: String, body: JsonObject? = null): JsonObject =
        execute(newRequest(apiUrl(path)).patch(jsonBody(body)).build())

    suspend fun delete(path: String, body: JsonObject? = null): JsonObject {
        val builder = newRequest(apiUrl(path))
        if (body == null) builder.delete() else builder.delete(jsonBody(body))
        return execute(builder.build())
    }

    /**
     * Sends a multipart/form-data request. Use [methodOverride] = "PATCH" (or "PUT") to update a
     * resource with files: PHP does not parse multipart bodies of real PATCH requests, so the
     * request is sent as POST with Laravel's `_method` field.
     */
    suspend fun multipart(
        path: String,
        fields: Map<String, String?>,
        files: List<UploadPart> = emptyList(),
        methodOverride: String? = null,
    ): JsonObject {
        val builder = MultipartBody.Builder().setType(MultipartBody.FORM)
        fields.forEach { (key, value) -> builder.addFormDataPart(key, value ?: "") }
        if (methodOverride != null) {
            builder.addFormDataPart("_method", methodOverride.uppercase())
        }
        files.forEach { part -> builder.addFormDataPart(part.field, part.fileName, part.body) }
        if (fields.isEmpty() && files.isEmpty() && methodOverride == null) {
            builder.addFormDataPart("_mobile", "1")
        }
        return execute(newRequest(apiUrl(path)).post(builder.build()).build())
    }

    /**
     * Downloads [pathOrUrl] (an absolute portal URL, a portal path like `/storage/x.pdf`, or an
     * API path like `files/entries/5/download`) into [directory].
     */
    suspend fun download(pathOrUrl: String, directory: File, fallbackName: String = "file"): DownloadedFile =
        withContext(Dispatchers.IO) {
            val url = resolveDownloadUrl(pathOrUrl)
            val request = newRequest(url, accept = "*/*").get().build()
            val response = try {
                httpClient.newCall(request).execute()
            } catch (exception: IOException) {
                throw NetworkException(exception)
            }
            response.use { result ->
                if (!result.isSuccessful) {
                    val body = parseJsonObject(result.body?.string().orEmpty())
                    if (result.code == 401 && tokenProvider() != null) onUnauthorized()
                    throw ApiException.from(result.code, body)
                }
                val responseBody = result.body ?: throw IOException("Empty response body")
                val fileName = FileNames.sanitize(
                    FileNames.fromContentDisposition(result.header("Content-Disposition"))
                        ?: url.substringBefore('?').substringAfterLast('/').takeIf { it.contains('.') }
                        ?: fallbackName,
                )
                directory.mkdirs()
                val target = uniqueFile(directory, fileName)
                target.outputStream().use { output ->
                    responseBody.byteStream().use { input -> input.copyTo(output) }
                }
                DownloadedFile(
                    file = target,
                    fileName = fileName,
                    mimeType = responseBody.contentType()?.let { "${it.type}/${it.subtype}" },
                )
            }
        }

    /**
     * Absolute URL for an API path, e.g. `apiUrl("contacts", mapOf("page" to 2))`.
     * Segments that may contain `/`, `%`, `?`, `#` or spaces (QR codes, slugs) must be wrapped in
     * `RouteArgs.encode(value)`; existing `%XX` escapes are kept, other unsafe characters are encoded.
     */
    fun apiUrl(path: String, query: Map<String, Any?> = emptyMap()): String {
        val root = apiRoot.toHttpUrlOrNull() ?: throw NetworkException(IOException("Invalid portal address $baseUrl"))
        val builder: HttpUrl.Builder = root.newBuilder()
        val cleanPath = path.trim().trimStart('/')
        if (cleanPath.isNotEmpty()) {
            builder.addEncodedPathSegments(cleanPath)
        }
        query.forEach { (key, value) -> addQuery(builder, key, value) }
        return builder.build().toString()
    }

    /** Absolute URL for a portal URL or path (`/storage/a.png` → `https://portal/storage/a.png`). */
    fun absoluteUrl(pathOrUrl: String): String {
        val value = pathOrUrl.trim()
        if (value.startsWith("http://", ignoreCase = true) || value.startsWith("https://", ignoreCase = true)) {
            return value
        }
        if (SCHEME_PATTERN.containsMatchIn(value)) {
            // content://, file://, data:, mailto: ... are returned unchanged.
            return value
        }
        if (value.startsWith("//")) {
            val scheme = baseUrl.substringBefore("://", "https")
            return "$scheme:$value"
        }
        return baseUrl.trimEnd('/') + "/" + value.trimStart('/')
    }

    /** `Bearer <token>` for requests made outside of this client (images, web views). */
    fun authorizationHeader(): String? = tokenProvider()?.let { "Bearer $it" }

    /** True when [url] points to this portal (the bearer token may be attached). */
    fun isPortalUrl(url: String): Boolean {
        val host = url.toHttpUrlOrNull()?.host ?: return false
        return baseHost != null && host.equals(baseHost, ignoreCase = true)
    }

    private fun resolveDownloadUrl(pathOrUrl: String): String {
        val value = pathOrUrl.trim()
        return when {
            value.startsWith("http://", ignoreCase = true) || value.startsWith("https://", ignoreCase = true) -> value
            value.startsWith("/") -> absoluteUrl(value)
            else -> apiUrl(value)
        }
    }

    private fun newRequest(url: String, accept: String = "application/json"): Request.Builder {
        val builder = Request.Builder()
            .url(url)
            .header("Accept", accept)
            .header("Accept-Language", languageProvider())
            .header("User-Agent", USER_AGENT)
            .header("X-Requested-With", "XMLHttpRequest")
        val token = tokenProvider()
        if (token != null && isPortalUrl(url)) {
            builder.header("Authorization", "Bearer $token")
        }
        return builder
    }

    private fun jsonBody(body: JsonObject?): RequestBody =
        (body ?: EmptyJsonObject).toString().toRequestBody(JSON_MEDIA_TYPE)

    private suspend fun execute(request: Request): JsonObject = withContext(Dispatchers.IO) {
        val response: Response = try {
            httpClient.newCall(request).execute()
        } catch (exception: IOException) {
            throw NetworkException(exception)
        }
        response.use { result ->
            val text = try {
                result.body?.string().orEmpty()
            } catch (exception: IOException) {
                throw NetworkException(exception)
            }
            val json = parseJsonObject(text)
            if (!result.isSuccessful) {
                if (result.code == 401 && request.header("Authorization") != null) {
                    onUnauthorized()
                }
                throw ApiException.from(result.code, json)
            }
            json
        }
    }

    private fun addQuery(builder: HttpUrl.Builder, key: String, value: Any?) {
        when (value) {
            null -> Unit
            is Boolean -> builder.addQueryParameter(key, if (value) "1" else "0")
            is Iterable<*> -> value.forEach { item -> if (item != null) addQuery(builder, "$key[]", item) }
            is Array<*> -> value.forEach { item -> if (item != null) addQuery(builder, "$key[]", item) }
            else -> {
                val text = value.toString()
                if (text.isNotEmpty()) builder.addQueryParameter(key, text)
            }
        }
    }

    companion object {
        private val JSON_MEDIA_TYPE = "application/json; charset=utf-8".toMediaType()

        private val SCHEME_PATTERN = Regex("^[a-zA-Z][a-zA-Z0-9+.-]*:")

        val USER_AGENT: String = "CRM369Android/${BuildConfig.VERSION_NAME}"

        /** A client that is not bound to an account; every request fails with HTTP 401. */
        fun detached(httpClient: OkHttpClient): ApiClient = ApiClient(
            httpClient = httpClient,
            baseUrl = "https://invalid.invalid",
            tokenProvider = { null },
            languageProvider = { "ru" },
        )

        private fun uniqueFile(directory: File, fileName: String): File {
            var candidate = File(directory, fileName)
            if (!candidate.exists()) return candidate
            val base = fileName.substringBeforeLast('.', fileName)
            val extension = fileName.substringAfterLast('.', "")
            var index = 1
            while (candidate.exists()) {
                val suffixed = if (extension.isEmpty()) "$base ($index)" else "$base ($index).$extension"
                candidate = File(directory, suffixed)
                index++
            }
            return candidate
        }
    }
}
