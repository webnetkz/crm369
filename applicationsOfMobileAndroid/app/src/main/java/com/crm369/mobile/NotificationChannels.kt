package com.appswebnetkz.crm369

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build

object NotificationChannels {
    const val GENERAL_CHANNEL_ID = "crm369_updates"

    fun ensure(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return
        }

        val notificationManager = context.getSystemService(NotificationManager::class.java)
        val existingChannel = notificationManager?.getNotificationChannel(GENERAL_CHANNEL_ID)

        if (existingChannel != null) {
            return
        }

        val channel = NotificationChannel(
            GENERAL_CHANNEL_ID,
            context.getString(R.string.notification_channel_name),
            NotificationManager.IMPORTANCE_HIGH,
        ).apply {
            description = context.getString(R.string.notification_channel_description)
        }

        notificationManager?.createNotificationChannel(channel)
    }
}
