package com.appswebnetkz.crm369

import android.content.Context

class MobileNotificationStateStore(context: Context) {
    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    fun newEntries(
        domainKey: String,
        feed: MobileNotificationFeed,
    ): List<MobileNotificationEntry> {
        if (feed.userId <= 0) {
            return emptyList()
        }

        val identity = identity(domainKey, feed.userId)
        val currentIdentity = preferences.getString(KEY_IDENTITY, null)
        val deliveredKeys = if (currentIdentity == identity) {
            preferences.getStringSet(KEY_DELIVERED_KEYS, emptySet()).orEmpty()
        } else {
            emptySet()
        }

        return feed.entries.filterNot { deliveredKeys.contains(it.key) }
    }

    fun markDelivered(
        domainKey: String,
        feed: MobileNotificationFeed,
        deliveredKeys: List<String>,
    ) {
        if (feed.userId <= 0 || deliveredKeys.isEmpty()) {
            return
        }

        val identity = identity(domainKey, feed.userId)
        val currentIdentity = preferences.getString(KEY_IDENTITY, null)
        val existingKeys = if (currentIdentity == identity) {
            preferences.getStringSet(KEY_DELIVERED_KEYS, emptySet()).orEmpty().toList()
        } else {
            emptyList()
        }

        val mergedKeys = (deliveredKeys + existingKeys)
            .distinct()
            .take(MAX_TRACKED_KEYS)
            .toSet()

        preferences.edit()
            .putString(KEY_IDENTITY, identity)
            .putStringSet(KEY_DELIVERED_KEYS, mergedKeys)
            .apply()
    }

    private fun identity(domainKey: String, userId: Int): String {
        return "$domainKey|$userId"
    }

    companion object {
        private const val PREFERENCES_NAME = "crm369_mobile_notifications"
        private const val KEY_IDENTITY = "identity"
        private const val KEY_DELIVERED_KEYS = "delivered_keys"
        private const val MAX_TRACKED_KEYS = 200
    }
}
