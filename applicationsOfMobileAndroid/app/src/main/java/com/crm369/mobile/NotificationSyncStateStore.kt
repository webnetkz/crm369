package com.appswebnetkz.crm369

import android.content.Context

class NotificationSyncStateStore(context: Context) {
    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    fun isEnabled(baseUrl: String): Boolean {
        return preferences.getBoolean(syncKey(baseUrl), false)
    }

    fun enable(baseUrl: String) {
        preferences.edit().putBoolean(syncKey(baseUrl), true).commit()
    }

    fun disable(baseUrl: String) {
        preferences.edit().remove(syncKey(baseUrl)).commit()
    }

    private fun syncKey(baseUrl: String): String {
        return "$KEY_PREFIX:$baseUrl"
    }

    companion object {
        private const val PREFERENCES_NAME = "crm369_mobile_notifications"
        private const val KEY_PREFIX = "sync_enabled"
    }
}
