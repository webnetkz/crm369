package com.appswebnetkz.crm369.ui.navigation

import androidx.annotation.StringRes
import androidx.compose.ui.graphics.vector.ImageVector
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.session.Bootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons

/**
 * A module of the portal menu. [key] is the built-in menu key of the web sidebar (used by the
 * personal menu settings: hidden items and order), [ability] the bootstrap ability required.
 */
data class ModuleEntry(
    val key: String,
    @StringRes val titleRes: Int,
    val icon: ImageVector,
    val route: String?,
    val webPath: String,
    val ability: String?,
    val anyOfAbilities: List<String> = emptyList(),
)

/** Administration page of the portal opened in the in-app browser (web session bridge). */
data class AdminEntry(
    val key: String,
    @StringRes val titleRes: Int,
    val icon: ImageVector,
    val webPath: String,
    val isVisible: (Bootstrap) -> Boolean,
)

object ModuleRegistry {

    /** Built-in modules in the default order of the web sidebar. */
    val modules: List<ModuleEntry> = listOf(
        ModuleEntry("news", R.string.module_news, AppIcons.News, "news", "/news", "canAccessNews"),
        ModuleEntry("notifications", R.string.module_notifications, AppIcons.Notifications, AppRoutes.NOTIFICATIONS, "/notifications", null),
        ModuleEntry("dashboard", R.string.module_dashboard, AppIcons.Dashboard, null, "/dashboard", null),
        ModuleEntry("company-structure", R.string.module_company_structure, AppIcons.CompanyStructure, "company", "/company-structure", "canAccessCompanyStructure"),
        ModuleEntry("projects", R.string.module_projects, AppIcons.Projects, "projects", "/projects", "canAccessProjects"),
        ModuleEntry("calendar", R.string.module_calendar, AppIcons.Calendar, "calendar", "/calendar", "canAccessCalendar"),
        ModuleEntry("chats", R.string.module_chats, AppIcons.Chats, "chats", "/chats", "canAccessChats"),
        ModuleEntry("conferences", R.string.module_conferences, AppIcons.Conferences, "conferences", "/conferences", "canAccessConferences"),
        ModuleEntry("knowledge-bases", R.string.module_knowledge_bases, AppIcons.KnowledgeBases, "knowledge", "/knowledge-bases", "canAccessKnowledgeBases"),
        ModuleEntry("funnels", R.string.module_funnels, AppIcons.Funnels, "funnels", "/funnels", "canAccessFunnels"),
        ModuleEntry("forms", R.string.module_forms, AppIcons.Forms, "forms", "/forms", "canAccessForms"),
        ModuleEntry("contacts", R.string.module_contacts, AppIcons.Contacts, "contacts", "/contacts", "canAccessContacts"),
        ModuleEntry("directories", R.string.module_directories, AppIcons.Directories, "directories", "/directories", "canAccessDirectories"),
        ModuleEntry("edo", R.string.module_edo, AppIcons.Edo, "edo", "/edo", "canAccessEdo"),
        ModuleEntry("files", R.string.module_files, AppIcons.Files, "files", "/files", "canAccessFiles"),
        ModuleEntry("production", R.string.module_production, AppIcons.Production, "production", "/production", "canAccessProduction"),
        ModuleEntry("procurement", R.string.module_procurement, AppIcons.Procurement, "procurement", "/procurement", "canAccessProcurement"),
        ModuleEntry("warehouses", R.string.module_warehouses, AppIcons.Warehouses, "warehouses", "/warehouses", "canAccessWarehouses"),
        ModuleEntry("qr", R.string.module_qr, AppIcons.QrScan, "qr", "/qr", "canAccessTsd"),
        ModuleEntry("tsd", R.string.module_tsd, AppIcons.Tsd, "tsd", "/tsd", "canAccessTsd"),
        ModuleEntry("equipment", R.string.module_equipment, AppIcons.Equipment, "equipment", "/equipment", "canAccessEquipment"),
        ModuleEntry(
            key = "documentation",
            titleRes = R.string.module_documentation,
            icon = AppIcons.Documentation,
            route = null,
            webPath = "/documentation",
            ability = null,
            anyOfAbilities = listOf("canManageApiTokens", "canManageWebhooks"),
        ),
    )

    /** Administration pages (shown in the menu when the user has the matching rights). */
    val admin: List<AdminEntry> = listOf(
        AdminEntry("settings.users", R.string.admin_users, AppIcons.Users, "/settings/users") { it.can("canViewUsers") },
        AdminEntry("settings.groups", R.string.admin_groups, AppIcons.Group, "/settings/groups") { it.isSuperAdmin },
        AdminEntry("settings.rights", R.string.admin_rights, AppIcons.Rights, "/settings/rights") { it.isSuperAdmin },
        AdminEntry("settings.portal", R.string.admin_portal, AppIcons.Portal, "/settings/portal") { it.isSuperAdmin },
        AdminEntry("settings.modules", R.string.admin_modules, AppIcons.Modules, "/settings/modules") { it.isSuperAdmin },
        AdminEntry("settings.business-processes", R.string.admin_business_processes, AppIcons.BusinessProcesses, "/settings/business-processes") {
            it.can("canManageBusinessProcesses")
        },
        AdminEntry("settings.integrations", R.string.admin_integrations, AppIcons.Integrations, "/settings/integrations") {
            it.can("canManageMessengerIntegrations")
        },
        AdminEntry("settings.one-c", R.string.admin_one_c, AppIcons.OneC, "/settings/one-c") {
            it.isSuperAdmin && it.isModuleEnabled("one-c")
        },
        AdminEntry("settings.api", R.string.admin_api, AppIcons.Api, "/settings/api") { it.can("canManageApiTokens") },
        AdminEntry("settings.webhooks", R.string.admin_webhooks, AppIcons.Webhooks, "/settings/webhooks") { it.can("canManageWebhooks") },
        AdminEntry("settings.menu", R.string.admin_menu, AppIcons.Menu, "/settings/menu") { true },
        AdminEntry("settings.logs", R.string.admin_logs, AppIcons.Logs, "/settings/logs") { it.isSuperAdmin },
        AdminEntry("settings.system-security", R.string.admin_system_security, AppIcons.SystemSecurity, "/settings/system-security") { it.isSuperAdmin },
        AdminEntry("settings.system-updates", R.string.admin_system_updates, AppIcons.SystemUpdates, "/settings/system-updates") { it.isSuperAdmin },
    )

    /** Modules the user can open, filtered by abilities and the personal menu, in the user's order. */
    fun visibleModules(bootstrap: Bootstrap?): List<ModuleEntry> {
        if (bootstrap == null) return emptyList()
        val visible = modules.filter { entry ->
            val allowed = when {
                entry.anyOfAbilities.isNotEmpty() -> entry.anyOfAbilities.any { bootstrap.can(it) }
                else -> bootstrap.can(entry.ability)
            }
            allowed && bootstrap.menu.isVisible(entry.key)
        }
        val order = bootstrap.menu.order
        if (order.isEmpty()) return visible
        return visible.sortedBy { entry ->
            val index = order.indexOf(entry.key)
            if (index >= 0) index else order.size + modules.indexOf(entry)
        }
    }

    fun visibleAdmin(bootstrap: Bootstrap?): List<AdminEntry> {
        if (bootstrap == null) return emptyList()
        return admin.filter { it.isVisible(bootstrap) && bootstrap.menu.isVisible(it.key) }
    }

    fun find(key: String): ModuleEntry? = modules.firstOrNull { it.key == key }
}
