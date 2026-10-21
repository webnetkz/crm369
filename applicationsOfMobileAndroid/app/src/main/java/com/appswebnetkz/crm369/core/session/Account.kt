package com.appswebnetkz.crm369.core.session

import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.portal.DomainNormalizer
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

/**
 * A signed in user on one portal. Several accounts (portals) can be added, like in Bitrix24;
 * one of them is active. The session token is stored separately (encrypted) in [AccountStore].
 */
data class Account(
    val id: String,
    val baseUrl: String,
    val portalName: String,
    val logoUrl: String?,
    val userId: Long,
    val userName: String,
    val email: String,
    val avatarUrl: String?,
) {
    val host: String get() = DomainNormalizer.hostOf(baseUrl)

    fun toJson(): JsonObject = buildJsonObject {
        put("id", id)
        put("base_url", baseUrl)
        put("portal_name", portalName)
        put("logo_url", logoUrl)
        put("user_id", userId)
        put("user_name", userName)
        put("email", email)
        put("avatar_url", avatarUrl)
    }

    companion object {
        fun idFor(baseUrl: String, userId: Long): String = baseUrl.trimEnd('/').lowercase() + "#" + userId

        fun fromJson(json: JsonObject): Account? {
            val baseUrl = json.text("base_url") ?: return null
            val userId = json.long("user_id") ?: return null
            return Account(
                id = json.text("id") ?: idFor(baseUrl, userId),
                baseUrl = baseUrl,
                portalName = json.text("portal_name") ?: DomainNormalizer.hostOf(baseUrl),
                logoUrl = json.text("logo_url"),
                userId = userId,
                userName = json.str("user_name").orEmpty(),
                email = json.str("email").orEmpty(),
                avatarUrl = json.text("avatar_url"),
            )
        }
    }
}

/** A portal that was opened before (shown as a suggestion on the portal address screen). */
data class RecentPortal(
    val baseUrl: String,
    val name: String,
    val logoUrl: String?,
)
