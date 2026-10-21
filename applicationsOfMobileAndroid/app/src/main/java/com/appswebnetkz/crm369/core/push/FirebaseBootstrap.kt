package com.appswebnetkz.crm369.core.push

import android.content.Context
import android.util.Log
import com.appswebnetkz.crm369.core.portal.FirebaseClientOptions
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.messaging.FirebaseMessaging

/**
 * Initializes Firebase at runtime with the client options published by the portal
 * (`GET /api/mobile/v1/portal` → `push.firebase`). The application therefore needs no
 * google-services.json and one build works with any CRM369 portal.
 *
 * The first configured Firebase project becomes the default app. Portals that use another
 * Firebase project get a named [FirebaseApp] instance.
 */
object FirebaseBootstrap {
    private const val TAG = "FirebaseBootstrap"

    @Synchronized
    fun appFor(context: Context, options: FirebaseClientOptions): FirebaseApp? = try {
        val firebaseOptions = FirebaseOptions.Builder()
            .setApiKey(options.apiKey)
            .setApplicationId(options.applicationId)
            .setProjectId(options.projectId)
            .setGcmSenderId(options.senderId)
            .build()
        val apps = FirebaseApp.getApps(context)
        val defaultApp = apps.firstOrNull { it.name == FirebaseApp.DEFAULT_APP_NAME }
        when {
            defaultApp == null -> FirebaseApp.initializeApp(context, firebaseOptions)
            defaultApp.options.projectId == options.projectId -> defaultApp
            else -> {
                val name = namedAppName(options.projectId)
                apps.firstOrNull { it.name == name } ?: FirebaseApp.initializeApp(context, firebaseOptions, name)
            }
        }
    } catch (exception: Exception) {
        Log.w(TAG, "Firebase initialization failed", exception)
        null
    }

    fun messagingFor(context: Context, options: FirebaseClientOptions): FirebaseMessaging? {
        val app = appFor(context, options) ?: return null
        return try {
            val messaging = if (app.name == FirebaseApp.DEFAULT_APP_NAME) {
                FirebaseMessaging.getInstance()
            } else {
                app.get(FirebaseMessaging::class.java)
            }
            messaging.isAutoInitEnabled = true
            messaging
        } catch (exception: Exception) {
            Log.w(TAG, "Firebase messaging is not available", exception)
            null
        }
    }

    /** Project id of the default Firebase app, if initialized. */
    fun defaultProjectId(context: Context): String? =
        FirebaseApp.getApps(context).firstOrNull { it.name == FirebaseApp.DEFAULT_APP_NAME }?.options?.projectId

    private fun namedAppName(projectId: String): String = "crm369-" + projectId.lowercase()
}
