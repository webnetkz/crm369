package com.appswebnetkz.crm369

import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.BitmapFactory
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import java.net.URL

class MobileNotificationNotifier(
    private val context: Context,
) {
    fun show(
        baseUrl: String,
        entry: MobileNotificationEntry,
    ) {
        val targetUrl = resolveTargetUrl(baseUrl, entry.actionPath)
        val pendingIntent = PendingIntent.getActivity(
            context,
            entry.key.hashCode(),
            WebViewActivity.newIntent(context, baseUrl, targetUrl).apply {
                flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            },
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val contentText = entry.message.ifBlank { entry.title }
        val appLogoBitmap = BitmapFactory.decodeResource(
            context.resources,
            MobileNotificationStyle.largeIconResId(),
        )
        val notification = NotificationCompat.Builder(context, NotificationChannels.GENERAL_CHANNEL_ID)
            .setSmallIcon(MobileNotificationStyle.smallIconResId())
            .setLargeIcon(appLogoBitmap)
            .setColor(ContextCompat.getColor(context, MobileNotificationStyle.accentColorResId()))
            .setContentTitle(entry.title)
            .setContentText(contentText)
            .setStyle(NotificationCompat.BigTextStyle().bigText(contentText))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .setCategory(
                if (entry.key.startsWith("chat:")) {
                    NotificationCompat.CATEGORY_MESSAGE
                } else {
                    NotificationCompat.CATEGORY_STATUS
                },
            )
            .build()

        NotificationManagerCompat.from(context).notify(entry.key.hashCode(), notification)
    }

    private fun resolveTargetUrl(baseUrl: String, actionPath: String?): String {
        if (actionPath.isNullOrBlank()) {
            return baseUrl
        }

        return URL(URL(ensureTrailingSlash(baseUrl)), actionPath).toString()
    }

    private fun ensureTrailingSlash(baseUrl: String): String {
        return if (baseUrl.endsWith("/")) {
            baseUrl
        } else {
            "$baseUrl/"
        }
    }
}
