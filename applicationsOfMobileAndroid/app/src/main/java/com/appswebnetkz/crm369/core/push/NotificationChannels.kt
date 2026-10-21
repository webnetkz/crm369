package com.appswebnetkz.crm369.core.push

import android.Manifest
import android.annotation.SuppressLint
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.appswebnetkz.crm369.MainActivity
import com.appswebnetkz.crm369.R

/** Data keys of CRM369 push messages (see FirebaseCloudMessagingClient on the server). */
object PushKeys {
    const val ACTION = "OPEN_CRM369"
    const val TYPE = "type"
    const val ACTION_PATH = "action_path"
    const val ENTITY_ID = "entity_id"
    const val PORTAL_URL = "portal_url"
    const val TITLE = "title"
    const val BODY = "body"
}

object NotificationChannels {
    /** Must match `android.notification.channel_id` sent by the server. */
    const val UPDATES = "crm369_updates"

    fun ensure(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = context.getSystemService(NotificationManager::class.java) ?: return
        val channel = NotificationChannel(
            UPDATES,
            context.getString(R.string.notification_channel_updates),
            NotificationManager.IMPORTANCE_HIGH,
        ).apply {
            description = context.getString(R.string.notification_channel_updates_description)
            enableVibration(true)
        }
        manager.createNotificationChannel(channel)
    }

    fun canPostNotifications(context: Context): Boolean {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            return false
        }
        return NotificationManagerCompat.from(context).areNotificationsEnabled()
    }
}

object NotificationPresenter {

    /** Builds the intent that opens the application at the push target. */
    fun openIntent(context: Context, data: Map<String, String>): Intent {
        val intent = Intent(context, MainActivity::class.java)
        intent.action = PushKeys.ACTION
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
        data.forEach { (key, value) -> intent.putExtra(key, value) }
        return intent
    }

    @SuppressLint("MissingPermission")
    fun show(context: Context, title: String, body: String, data: Map<String, String>) {
        if (!NotificationChannels.canPostNotifications(context)) return
        NotificationChannels.ensure(context)

        val requestCode = (data[PushKeys.ACTION_PATH].orEmpty() + data[PushKeys.PORTAL_URL].orEmpty() + title).hashCode()
        val pendingIntent = PendingIntent.getActivity(
            context,
            requestCode,
            openIntent(context, data),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val notification = NotificationCompat.Builder(context, NotificationChannels.UPDATES)
            .setSmallIcon(R.drawable.ic_notification_small)
            .setColor(ContextCompat.getColor(context, R.color.brand_primary))
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()

        NotificationManagerCompat.from(context).notify(requestCode, notification)
    }
}
