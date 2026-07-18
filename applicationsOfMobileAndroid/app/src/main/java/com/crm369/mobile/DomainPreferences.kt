package com.appswebnetkz.crm369

import android.content.Context

class DomainPreferences(context: Context) {
    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    fun getBaseUrl(): String? {
        return preferences.getString(KEY_BASE_URL, null)?.takeIf { it.isNotBlank() }
    }

    fun saveBaseUrl(baseUrl: String) {
        preferences.edit().putString(KEY_BASE_URL, baseUrl).commit()
    }

    companion object {
        private const val PREFERENCES_NAME = "crm369_mobile_preferences"
        private const val KEY_BASE_URL = "base_url"
    }
}
