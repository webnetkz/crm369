package com.appswebnetkz.crm369

import android.content.Context
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions

object FirebaseBootstrap {
    fun initialize(context: Context): Boolean {
        if (FirebaseApp.getApps(context).isNotEmpty()) {
            return true
        }

        if (!isConfigured()) {
            return false
        }

        val options = FirebaseOptions.Builder()
            .setApiKey(BuildConfig.FIREBASE_API_KEY)
            .setApplicationId(BuildConfig.FIREBASE_APP_ID)
            .setProjectId(BuildConfig.FIREBASE_PROJECT_ID)
            .setGcmSenderId(BuildConfig.FIREBASE_SENDER_ID)
            .build()

        FirebaseApp.initializeApp(context, options)

        return true
    }

    fun isConfigured(): Boolean {
        return BuildConfig.FIREBASE_API_KEY.isNotBlank()
            && BuildConfig.FIREBASE_APP_ID.isNotBlank()
            && BuildConfig.FIREBASE_PROJECT_ID.isNotBlank()
            && BuildConfig.FIREBASE_SENDER_ID.isNotBlank()
    }
}
