package com.appswebnetkz.crm369.core.session

import com.appswebnetkz.crm369.core.json.asBool
import com.appswebnetkz.crm369.core.json.bool
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.strings
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.portal.FirebaseClientOptions
import kotlinx.serialization.json.JsonObject

/** The signed in user as returned by the API (`ApiUserResource`). */
data class UserSummary(
    val id: Long,
    val name: String,
    val lastName: String?,
    val middleName: String?,
    val email: String,
    val phone: String?,
    val position: String?,
    val avatarUrl: String?,
    val language: String?,
    val groupName: String?,
    val isSuperAdmin: Boolean,
) {
    val fullName: String
        get() = listOfNotNull(name.takeIf { it.isNotBlank() }, lastName?.takeIf { it.isNotBlank() })
            .joinToString(" ")
            .ifBlank { email }

    companion object {
        fun fromJson(json: JsonObject?): UserSummary = UserSummary(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            lastName = json.text("last_name"),
            middleName = json.text("middle_name"),
            email = json.str("email").orEmpty(),
            phone = json.text("phone"),
            position = json.text("position"),
            avatarUrl = json.text("avatar"),
            language = json.text("language"),
            groupName = json.obj("group")?.let { it.text("display_name") ?: it.text("name") },
            isSuperAdmin = json.boolOr("is_super_admin", false),
        )
    }
}

data class CustomMenuItem(
    val id: Long,
    val title: String,
    val icon: String?,
    val url: String,
    val opensInNewTab: Boolean,
)

data class KnowledgeBaseMenuItem(
    val id: Long,
    val title: String,
)

/** Personal menu configuration shared with the web sidebar. */
data class MenuConfig(
    val hiddenItems: Set<String>,
    val customItems: List<CustomMenuItem>,
    val knowledgeBases: List<KnowledgeBaseMenuItem>,
    val order: List<String>,
) {
    fun isVisible(key: String): Boolean = key !in hiddenItems

    companion object {
        val Empty = MenuConfig(emptySet(), emptyList(), emptyList(), emptyList())

        fun fromJson(json: JsonObject?): MenuConfig {
            if (json == null) return Empty
            return MenuConfig(
                hiddenItems = json.strings("hiddenItems").toSet(),
                customItems = json.objects("customItems").mapNotNull { item ->
                    val url = item.text("url") ?: return@mapNotNull null
                    CustomMenuItem(
                        id = item.long("id") ?: 0L,
                        title = item.text("title") ?: url,
                        icon = item.text("icon"),
                        url = url,
                        opensInNewTab = item.boolOr("opensInNewTab", false),
                    )
                },
                knowledgeBases = json.objects("knowledgeBases").mapNotNull { item ->
                    val id = item.long("id") ?: return@mapNotNull null
                    KnowledgeBaseMenuItem(id = id, title = item.str("title").orEmpty())
                },
                order = json.strings("order"),
            )
        }
    }
}

/**
 * `GET /api/mobile/v1/bootstrap`: everything the application needs to build its navigation
 * for the signed in user (abilities are the same keys the web sidebar uses).
 */
data class Bootstrap(
    val accountId: String,
    val user: UserSummary,
    val abilities: Map<String, Boolean>,
    val portalName: String,
    val portalUrl: String?,
    val portalLogoUrl: String?,
    val defaultLanguage: String?,
    val enabledModules: Set<String>,
    val menu: MenuConfig,
    val unreadNotifications: Int,
    val unreadChats: Int,
    val push: FirebaseClientOptions?,
    val raw: JsonObject,
) {
    /** True when [ability] is null (no restriction) or the user has it. */
    fun can(ability: String?): Boolean = ability == null || abilities[ability] == true

    fun canAny(vararg abilityKeys: String): Boolean = abilityKeys.any { abilities[it] == true }

    val isSuperAdmin: Boolean get() = abilities["isSuperAdmin"] == true || user.isSuperAdmin

    fun isModuleEnabled(module: String): Boolean = module in enabledModules

    companion object {
        fun parse(accountId: String, data: JsonObject): Bootstrap {
            val abilitiesJson = data.obj("abilities")
            val abilities = abilitiesJson?.mapValues { (_, value) -> value.asBool() == true } ?: emptyMap()
            val portal = data.obj("portal")
            val counters = data.obj("counters")
            val push = data.obj("push")
            return Bootstrap(
                accountId = accountId,
                user = UserSummary.fromJson(data.obj("user")),
                abilities = abilities,
                portalName = portal.text("name") ?: "CRM369",
                portalUrl = portal.text("url"),
                portalLogoUrl = portal.text("logo_url"),
                defaultLanguage = portal.text("default_language"),
                enabledModules = portal.strings("enabled_modules").toSet(),
                menu = MenuConfig.fromJson(data.obj("menu")),
                unreadNotifications = counters.int("notifications") ?: 0,
                unreadChats = counters.int("chats") ?: 0,
                push = if (push.bool("enabled") == true) FirebaseClientOptions.fromJson(push.obj("firebase")) else null,
                raw = data,
            )
        }
    }
}
