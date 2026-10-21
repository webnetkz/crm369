package com.appswebnetkz.crm369.core.portal

import com.appswebnetkz.crm369.core.json.text
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

/** Firebase client options a portal publishes for the Android application (push delivery). */
data class FirebaseClientOptions(
    val projectId: String,
    val applicationId: String,
    val apiKey: String,
    val senderId: String,
) {
    fun toJson(): JsonObject = buildJsonObject {
        put("project_id", projectId)
        put("application_id", applicationId)
        put("api_key", apiKey)
        put("sender_id", senderId)
    }

    companion object {
        fun fromJson(json: JsonObject?): FirebaseClientOptions? {
            if (json == null) return null
            val projectId = json.text("project_id") ?: return null
            val applicationId = json.text("application_id") ?: return null
            val apiKey = json.text("api_key") ?: return null
            val senderId = json.text("sender_id") ?: return null
            return FirebaseClientOptions(projectId, applicationId, apiKey, senderId)
        }
    }
}

/** Public information about a CRM369 portal returned by `GET /api/mobile/v1/portal`. */
data class PortalInfo(
    val baseUrl: String,
    val name: String,
    val logoUrl: String?,
    val defaultLanguage: String,
    val apiVersion: Int,
    val supportsWebSession: Boolean,
    val push: FirebaseClientOptions?,
)

class PortalDiscoveryException(val reason: Reason, cause: Throwable? = null) : Exception(reason.name, cause) {
    enum class Reason {
        /** The text is not a valid domain or URL. */
        InvalidAddress,

        /** The host could not be reached (DNS, TLS, timeout, no internet). */
        Unreachable,

        /** The host answered, but it is not a CRM369 portal (or the mobile API is missing). */
        NotCrm369,

        /** The portal is a CRM369 installation that is too old for this application. */
        UnsupportedVersion,

        /** The portal answered with a server error. */
        ServerError,
    }
}
