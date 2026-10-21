package com.appswebnetkz.crm369.core.session

import android.content.Context
import android.os.Build
import com.appswebnetkz.crm369.BuildConfig
import java.util.Locale
import java.util.UUID

/** Stable, app-scoped identifier of this installation used for mobile sessions and push. */
class DeviceIdentity(context: Context) {

    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    val deviceId: String by lazy {
        preferences.getString(KEY_DEVICE_ID, null)?.takeIf { DEVICE_ID_PATTERN.matches(it) }
            ?: ("android-" + UUID.randomUUID().toString().replace("-", "")).also { generated ->
                preferences.edit().putString(KEY_DEVICE_ID, generated).apply()
            }
    }

    val deviceName: String by lazy {
        val manufacturer = Build.MANUFACTURER.orEmpty().trim()
        val model = Build.MODEL.orEmpty().trim()
        val name = if (model.startsWith(manufacturer, ignoreCase = true)) model else "$manufacturer $model"
        name.trim()
            .replaceFirstChar { if (it.isLowerCase()) it.titlecase(Locale.ROOT) else it.toString() }
            .ifBlank { "Android" }
            .take(120)
    }

    val appVersion: String = BuildConfig.VERSION_NAME

    val isEmulator: Boolean by lazy {
        val fingerprint = Build.FINGERPRINT.orEmpty()
        val model = Build.MODEL.orEmpty()
        val product = Build.PRODUCT.orEmpty()
        fingerprint.startsWith("generic") ||
            fingerprint.contains("emulator") ||
            fingerprint.contains("sdk_gphone") ||
            model.contains("Emulator") ||
            model.contains("Android SDK built for") ||
            product.contains("sdk_gphone") ||
            product == "google_sdk" ||
            product.startsWith("sdk")
    }

    private companion object {
        const val PREFERENCES_NAME = "crm369_device"
        const val KEY_DEVICE_ID = "device_id"
        val DEVICE_ID_PATTERN = Regex("^[A-Za-z0-9._:-]{8,64}$")
    }
}
