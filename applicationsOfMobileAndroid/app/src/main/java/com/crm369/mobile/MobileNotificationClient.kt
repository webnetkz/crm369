package com.appswebnetkz.crm369

import android.webkit.CookieManager
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

class MobileNotificationClient {
    @Throws(IOException::class)
    fun fetch(baseUrl: String): MobileNotificationFeed? {
        val cookieHeader = CookieManager.getInstance()
            .getCookie(baseUrl)
            ?.takeIf { it.isNotBlank() }
            ?: return null

        val connection = (URL(URL(ensureTrailingSlash(baseUrl)), FEED_PATH.removePrefix("/"))
            .openConnection() as HttpURLConnection).apply {
            requestMethod = "GET"
            instanceFollowRedirects = false
            connectTimeout = 15_000
            readTimeout = 15_000
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Cookie", cookieHeader)
        }

        try {
            return when (val responseCode = connection.responseCode) {
                HttpURLConnection.HTTP_OK -> {
                    val body = connection.inputStream.bufferedReader().use { it.readText() }
                    MobileNotificationFeedParser.parse(body)
                }
                HttpURLConnection.HTTP_UNAUTHORIZED,
                HttpURLConnection.HTTP_FORBIDDEN,
                HttpURLConnection.HTTP_MOVED_PERM,
                HttpURLConnection.HTTP_MOVED_TEMP,
                HttpURLConnection.HTTP_SEE_OTHER,
                HTTP_TEMPORARY_REDIRECT,
                HTTP_PERMANENT_REDIRECT,
                -> null
                in 500..599 -> throw IOException("Notification feed request failed with status $responseCode.")
                else -> null
            }
        } finally {
            connection.disconnect()
        }
    }

    private fun ensureTrailingSlash(baseUrl: String): String {
        return if (baseUrl.endsWith("/")) {
            baseUrl
        } else {
            "$baseUrl/"
        }
    }

    companion object {
        private const val FEED_PATH = "/mobile/notifications/feed"
        private const val HTTP_TEMPORARY_REDIRECT = 307
        private const val HTTP_PERMANENT_REDIRECT = 308
    }
}
