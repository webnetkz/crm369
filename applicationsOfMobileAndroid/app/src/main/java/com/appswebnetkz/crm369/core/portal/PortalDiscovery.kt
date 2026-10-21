package com.appswebnetkz.crm369.core.portal

import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.parseJsonObject
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import java.io.IOException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.HttpUrl
import okhttp3.OkHttpClient
import okhttp3.Request

class PortalDiscovery(
    private val httpClient: OkHttpClient,
    private val languageProvider: () -> String,
) {
    suspend fun discover(rawAddress: String, runningOnEmulator: Boolean = false): PortalInfo =
        withContext(Dispatchers.IO) {
            val baseUrl = DomainNormalizer.normalize(rawAddress, runningOnEmulator)
                ?: throw PortalDiscoveryException(PortalDiscoveryException.Reason.InvalidAddress)

            val request = Request.Builder()
                .url("$baseUrl/api/mobile/v1/portal")
                .header("Accept", "application/json")
                .header("Accept-Language", languageProvider())
                .header("User-Agent", ApiClient.USER_AGENT)
                .get()
                .build()

            val response = try {
                httpClient.newCall(request).execute()
            } catch (exception: IOException) {
                throw PortalDiscoveryException(PortalDiscoveryException.Reason.Unreachable, exception)
            } catch (exception: IllegalArgumentException) {
                throw PortalDiscoveryException(PortalDiscoveryException.Reason.InvalidAddress, exception)
            }

            response.use { result ->
                val text = try {
                    result.body?.string().orEmpty()
                } catch (exception: IOException) {
                    throw PortalDiscoveryException(PortalDiscoveryException.Reason.Unreachable, exception)
                }
                when {
                    result.code == 404 -> throw PortalDiscoveryException(PortalDiscoveryException.Reason.NotCrm369)
                    result.code >= 500 -> throw PortalDiscoveryException(PortalDiscoveryException.Reason.ServerError)
                    !result.isSuccessful -> throw PortalDiscoveryException(PortalDiscoveryException.Reason.NotCrm369)
                }

                val data = parseJsonObject(text).obj("data")
                    ?: throw PortalDiscoveryException(PortalDiscoveryException.Reason.NotCrm369)
                if (data.str("product") != "crm369") {
                    throw PortalDiscoveryException(PortalDiscoveryException.Reason.NotCrm369)
                }
                val apiVersion = data.int("api_version") ?: 0
                if (apiVersion < MIN_API_VERSION) {
                    throw PortalDiscoveryException(PortalDiscoveryException.Reason.UnsupportedVersion)
                }

                val finalUrl = result.request.url
                val canonicalBase = canonicalBaseUrl(finalUrl)
                val push = data.obj("push")

                PortalInfo(
                    baseUrl = canonicalBase,
                    name = data.text("name") ?: DomainNormalizer.hostOf(canonicalBase),
                    logoUrl = data.text("logo_url"),
                    defaultLanguage = data.text("default_language") ?: "ru",
                    apiVersion = apiVersion,
                    supportsWebSession = data.boolOr("web_session", false),
                    push = if (push.boolOr("enabled", false)) FirebaseClientOptions.fromJson(push.obj("firebase")) else null,
                )
            }
        }

    private fun canonicalBaseUrl(url: HttpUrl): String {
        val port = if (url.port == HttpUrl.defaultPort(url.scheme)) "" else ":${url.port}"
        val host = if (url.host.contains(':')) "[${url.host}]" else url.host
        return "${url.scheme}://$host$port"
    }

    companion object {
        const val MIN_API_VERSION = 1
    }
}
