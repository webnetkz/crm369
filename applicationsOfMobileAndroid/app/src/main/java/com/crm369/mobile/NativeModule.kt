package com.appswebnetkz.crm369

import androidx.annotation.StringRes

enum class NativeModule(
    val key: String,
    @StringRes val titleResource: Int,
    val endpoint: String,
    val arrayPath: String,
) {
    Notifications("notifications", R.string.module_notifications, "/api/mobile/v1/notifications?status=all&per_page=50", "data"),
    Chats("chats", R.string.module_chats, "/api/mobile/v1/chats", "conversations"),
    Projects("projects", R.string.module_projects, "/api/mobile/v1/projects", "data.projects"),
    Contacts("contacts", R.string.module_contacts, "/api/mobile/v1/contacts?per_page=50", "data"),
    Calendar("calendar", R.string.module_calendar, "/api/mobile/v1/calendar/events", "data"),
    Knowledge("knowledge", R.string.module_knowledge, "/api/mobile/v1/knowledge-bases", "data.bases"),
    Company("company", R.string.module_company, "/api/mobile/v1/company-structure", "roots"),
    Warehouses("warehouses", R.string.module_warehouses, "/api/mobile/v1/warehouses", "data"),
    Equipment("equipment", R.string.module_equipment, "/api/mobile/v1/equipment", "data");

    companion object {
        fun fromKey(key: String?): NativeModule? = entries.firstOrNull { it.key == key }
    }
}
