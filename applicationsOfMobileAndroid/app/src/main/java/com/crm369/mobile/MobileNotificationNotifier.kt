package com.appswebnetkz.crm369

import android.Manifest
import android.app.PendingIntent
import android.content.Context
import android.content.pm.PackageManager
import android.graphics.BitmapFactory
import android.os.Build
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.google.firebase.messaging.RemoteMessage

class MobileNotificationNotifier(private val context: Context) {
    fun show(message: RemoteMessage) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ActivityCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        NotificationChannels.ensure(context)

        val title = message.notification?.title
            ?: message.data["title"]
            ?: context.getString(R.string.app_name)
        val body = message.notification?.body
            ?: message.data["body"]
            ?: ""
        val type = message.data[MainActivity.EXTRA_NOTIFICATION_TYPE]
        val entityId = message.data[MainActivity.EXTRA_ENTITY_ID]
        val actionPath = message.data[MainActivity.EXTRA_ACTION_PATH]
        val requestCode = message.messageId?.hashCode() ?: (title + body).hashCode()
        val pendingIntent = PendingIntent.getActivity(
            context,
            requestCode,
            MainActivity.notificationIntent(context, type, entityId, actionPath),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val notification = NotificationCompat.Builder(context, NotificationChannels.GENERAL_CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_notification_small)
            .setLargeIcon(BitmapFactory.decodeResource(context.resources, R.drawable.crm369_logo))
            .setColor(ContextCompat.getColor(context, R.color.seed))
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(if (type == "chat_message") NotificationCompat.CATEGORY_MESSAGE else NotificationCompat.CATEGORY_STATUS)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()

        NotificationManagerCompat.from(context).notify(requestCode, notification)
    }
}
