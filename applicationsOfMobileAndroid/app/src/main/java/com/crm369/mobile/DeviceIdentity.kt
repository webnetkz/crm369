package com.appswebnetkz.crm369

import android.content.Context
import java.util.UUID

class DeviceIdentity(context: Context) {
    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    fun id(): String {
        preferences.getString(KEY_DEVICE_ID, null)?.takeIf { it.isNotBlank() }?.let { return it }

        val deviceId = UUID.randomUUID().toString()
        preferences.edit().putString(KEY_DEVICE_ID, deviceId).commit()

        return deviceId
    }

    companion object {
        private const val PREFERENCES_NAME = "crm369_device_identity"
        private const val KEY_DEVICE_ID = "installation_id"
    }
}
