package com.appswebnetkz.crm369

import org.json.JSONArray
import org.json.JSONObject

data class NativeListItem(
    val id: String,
    val title: String,
    val subtitle: String,
    val raw: JSONObject,
)

object NativeListParser {
    fun parse(response: JSONObject, module: NativeModule): List<NativeListItem> {
        val array = response.arrayAt(module.arrayPath) ?: return emptyList()

        return (0 until array.length()).mapNotNull { index ->
            array.optJSONObject(index)?.let { item -> parseItem(item, module, index) }
        }
    }

    private fun parseItem(item: JSONObject, module: NativeModule, index: Int): NativeListItem {
        val titleKeys = when (module) {
            NativeModule.Contacts, NativeModule.Equipment, NativeModule.Warehouses, NativeModule.Projects,
            NativeModule.Company -> listOf("name", "title", "label", "email")
            else -> listOf("title", "name", "subject", "label", "email")
        }
        val subtitleKeys = when (module) {
            NativeModule.Notifications -> listOf("message", "created_at")
            NativeModule.Chats -> listOf("excerpt", "subtitle", "lastMessageAt")
            NativeModule.Contacts -> listOf("company_name", "email", "phone", "position")
            NativeModule.Calendar -> listOf("description", "start", "start_at", "event_type")
            NativeModule.Projects -> listOf("description", "status", "updated_at")
            NativeModule.Knowledge -> listOf("description", "article_count", "updated_at")
            NativeModule.Company -> listOf("position", "email")
            NativeModule.Warehouses -> listOf("description", "address", "area_sqm")
            NativeModule.Equipment -> listOf("asset_number", "serial_number", "status", "description")
        }

        val title = firstText(item, titleKeys).ifBlank { "#${index + 1}" }
        val subtitle = subtitleKeys
            .mapNotNull { key -> item.displayValue(key) }
            .distinct()
            .take(3)
            .joinToString(" · ")

        return NativeListItem(item.opt("id")?.toString() ?: index.toString(), title, subtitle, item)
    }

    private fun firstText(item: JSONObject, keys: List<String>): String {
        return keys.firstNotNullOfOrNull { key -> item.displayValue(key) }.orEmpty()
    }

    private fun JSONObject.displayValue(key: String): String? {
        val value = opt(key)
        if (value == null || value == JSONObject.NULL) {
            return null
        }

        return when (value) {
            is JSONObject -> value.optString("name").takeIf(String::isNotBlank)
                ?: value.optString("title").takeIf(String::isNotBlank)
            is JSONArray -> null
            is Boolean -> null
            else -> value.toString().trim().takeIf(String::isNotBlank)
        }
    }

    private fun JSONObject.arrayAt(path: String): JSONArray? {
        var current: Any = this
        for (segment in path.split('.')) {
            current = (current as? JSONObject)?.opt(segment) ?: return null
        }
        return current as? JSONArray
    }
}
