package com.appswebnetkz.crm369

import android.content.Context
import android.content.Intent

object AppNavigator {
    fun openAuthenticatedArea(context: Context, clearTask: Boolean = true) {
        val destination = if (SecureSessionStore(context).hasSession()) {
            MainActivity::class.java
        } else {
            LoginActivity::class.java
        }

        context.startActivity(Intent(context, destination).apply {
            if (clearTask) {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            }
        })
    }

    fun openLogin(context: Context, sessionExpired: Boolean = false) {
        context.startActivity(Intent(context, LoginActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            putExtra(LoginActivity.EXTRA_SESSION_EXPIRED, sessionExpired)
        })
    }
}
