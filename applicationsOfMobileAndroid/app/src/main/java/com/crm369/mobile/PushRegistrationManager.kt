package com.appswebnetkz.crm369

import android.content.Context
import android.os.Build
import com.google.firebase.messaging.FirebaseMessaging
import org.json.JSONObject

object PushRegistrationManager {
    fun sync(context: Context, onUnavailable: (() -> Unit)? = null) {
        val applicationContext = context.applicationContext

        if (!FirebaseBootstrap.initialize(applicationContext)) {
            onUnavailable?.invoke()
            return
        }

        if (!SecureSessionStore(applicationContext).hasSession()) {
            return
        }

        FirebaseMessaging.getInstance().token
            .addOnSuccessListener { token -> register(applicationContext, token) }
            .addOnFailureListener { onUnavailable?.invoke() }
    }

    fun register(context: Context, token: String) {
        if (token.isBlank() || !SecureSessionStore(context).hasSession()) {
            return
        }

        NativeApiClient(context).put(
            "/api/mobile/v1/device",
            JSONObject()
                .put("device_id", DeviceIdentity(context).id())
                .put("device_name", "${Build.MANUFACTURER} ${Build.MODEL}".trim())
                .put("app_version", BuildConfig.VERSION_NAME)
                .put("fcm_token", token),
        ) { }
    }
}
