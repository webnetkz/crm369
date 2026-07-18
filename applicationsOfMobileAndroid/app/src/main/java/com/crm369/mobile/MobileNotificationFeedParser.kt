package com.appswebnetkz.crm369

import org.json.JSONArray
import org.json.JSONObject

object MobileNotificationFeedParser {
    fun parse(json: String): MobileNotificationFeed {
        val payload = JSONObject(json)
        val meta = payload.optJSONObject("meta") ?: JSONObject()
        val data = payload.optJSONObject("data") ?: JSONObject()
        val entries = mutableListOf<MobileNotificationEntry>()

        appendEntries(data.optJSONArray("notifications"), entries)
        appendEntries(data.optJSONArray("chats"), entries)

        return MobileNotificationFeed(
            userId = meta.optInt("user_id", 0),
            notificationsUnreadCount = meta.optInt("notifications_unread_count", 0),
            chatUnreadCount = meta.optInt("chat_unread_count", 0),
            entries = entries,
        )
    }

    private fun appendEntries(
        source: JSONArray?,
        destination: MutableList<MobileNotificationEntry>,
    ) {
        if (source == null) {
            return
        }

        for (index in 0 until source.length()) {
            val item = source.optJSONObject(index) ?: continue

            destination += MobileNotificationEntry(
                key = item.optString("key"),
                title = item.optString("title"),
                message = item.optString("message"),
                actionPath = item.optString("action_path").takeIf { it.isNotBlank() },
                createdAt = item.optString("created_at").takeIf { it.isNotBlank() },
            )
        }
    }
}
