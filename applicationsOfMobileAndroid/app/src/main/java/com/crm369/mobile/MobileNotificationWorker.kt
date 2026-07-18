package com.appswebnetkz.crm369

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import androidx.work.Worker
import androidx.work.WorkerParameters
import java.io.IOException

class MobileNotificationWorker(
    appContext: Context,
    workerParams: WorkerParameters,
) : Worker(appContext, workerParams) {
    override fun doWork(): Result {
        val baseUrl = DomainPreferences(applicationContext).getBaseUrl() ?: return Result.success()
        val syncStateStore = NotificationSyncStateStore(applicationContext)

        if (!syncStateStore.isEnabled(baseUrl)) {
            return Result.success()
        }

        NotificationChannels.ensure(applicationContext)

        if (!canPostNotifications()) {
            return Result.success()
        }

        return try {
            val feed = MobileNotificationClient().fetch(baseUrl) ?: return Result.success()

            if (feed.entries.isEmpty()) {
                return Result.success()
            }

            val stateStore = MobileNotificationStateStore(applicationContext)
            val newEntries = stateStore.newEntries(baseUrl, feed)

            if (newEntries.isEmpty()) {
                return Result.success()
            }

            val notifier = MobileNotificationNotifier(applicationContext)

            newEntries.forEach { entry ->
                notifier.show(baseUrl, entry)
            }

            stateStore.markDelivered(
                domainKey = baseUrl,
                feed = feed,
                deliveredKeys = newEntries.map(MobileNotificationEntry::key),
            )

            Result.success()
        } catch (_: IOException) {
            Result.retry()
        } catch (_: Exception) {
            Result.success()
        }
    }

    private fun canPostNotifications(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return true
        }

        return ContextCompat.checkSelfPermission(
            applicationContext,
            Manifest.permission.POST_NOTIFICATIONS,
        ) == PackageManager.PERMISSION_GRANTED
    }
}
