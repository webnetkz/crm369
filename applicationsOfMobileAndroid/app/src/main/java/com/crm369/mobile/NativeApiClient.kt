package com.appswebnetkz.crm369

import android.content.Context
import android.os.Handler
import android.os.Looper
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.Executors

class NativeApiClient(context: Context) {
    private val applicationContext = context.applicationContext
    private val domainPreferences = DomainPreferences(applicationContext)
    private val sessionStore = SecureSessionStore(applicationContext)

    fun get(path: String, authenticated: Boolean = true, callback: ApiCallback) {
        request("GET", path, null, authenticated, callback)
    }

    fun post(path: String, body: JSONObject, authenticated: Boolean = true, callback: ApiCallback) {
        request("POST", path, body, authenticated, callback)
    }

    fun put(path: String, body: JSONObject, authenticated: Boolean = true, callback: ApiCallback) {
        request("PUT", path, body, authenticated, callback)
    }

    fun patch(path: String, body: JSONObject = JSONObject(), authenticated: Boolean = true, callback: ApiCallback) {
        request("PATCH", path, body, authenticated, callback)
    }

    fun delete(path: String, authenticated: Boolean = true, callback: ApiCallback) {
        request("DELETE", path, null, authenticated, callback)
    }

    private fun request(
        method: String,
        path: String,
        body: JSONObject?,
        authenticated: Boolean,
        callback: ApiCallback,
    ) {
        EXECUTOR.execute {
            val result = runCatching { execute(method, path, body, authenticated) }
            MAIN_HANDLER.post { callback(result) }
        }
    }

    private fun execute(method: String, path: String, body: JSONObject?, authenticated: Boolean): JSONObject {
        val baseUrl = domainPreferences.getBaseUrl()
            ?: throw IOException("CRM369 server is not configured")
        val connection = (URL(baseUrl.trimEnd('/') + normalizePath(path)).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 15_000
            readTimeout = 20_000
            instanceFollowRedirects = false
            setRequestProperty("Accept", "application/json")
            setRequestProperty("User-Agent", "CRM369Android/${BuildConfig.VERSION_NAME}")

            if (authenticated) {
                val token = sessionStore.token() ?: throw ApiException(401, getString(R.string.session_expired))
                setRequestProperty("Authorization", "Bearer $token")
            }

            if (body != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json; charset=utf-8")
            }
        }

        try {
            if (body != null) {
                connection.outputStream.bufferedWriter(Charsets.UTF_8).use { writer ->
                    writer.write(body.toString())
                }
            }

            val statusCode = connection.responseCode
            val stream = if (statusCode in 200..299) connection.inputStream else connection.errorStream
            val responseText = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
            val responseJson = responseText.takeIf { it.isNotBlank() }
                ?.let { runCatching { JSONObject(it) }.getOrNull() }
                ?: JSONObject()

            if (statusCode !in 200..299) {
                throw ApiException(statusCode, responseMessage(responseJson, statusCode), responseJson)
            }

            return responseJson
        } finally {
            connection.disconnect()
        }
    }

    private fun responseMessage(response: JSONObject, statusCode: Int): String {
        response.optString("message").takeIf { it.isNotBlank() }?.let { return it }

        val errors = response.optJSONObject("errors")
        if (errors != null) {
            errors.keys().forEach { key ->
                val messages = errors.optJSONArray(key)
                messages?.optString(0)?.takeIf { it.isNotBlank() }?.let { return it }
            }
        }

        return when (statusCode) {
            401 -> applicationContext.getString(R.string.session_expired)
            403 -> "Недостаточно прав для этого действия"
            404 -> "Данные не найдены"
            422 -> "Проверьте введённые данные"
            else -> "Ошибка сервера: $statusCode"
        }
    }

    private fun normalizePath(path: String): String = if (path.startsWith('/')) path else "/$path"

    private fun getString(resourceId: Int): String = applicationContext.getString(resourceId)

    companion object {
        private val EXECUTOR = Executors.newFixedThreadPool(4)
        private val MAIN_HANDLER = Handler(Looper.getMainLooper())
    }
}

typealias ApiCallback = (Result<JSONObject>) -> Unit

class ApiException(
    val statusCode: Int,
    override val message: String,
    val payload: JSONObject? = null,
) : IOException(message)

fun JSONObject.optObjectArray(path: String): JSONArray? {
    var current: Any = this

    for (segment in path.split('.')) {
        current = (current as? JSONObject)?.opt(segment) ?: return null
    }

    return current as? JSONArray
}
