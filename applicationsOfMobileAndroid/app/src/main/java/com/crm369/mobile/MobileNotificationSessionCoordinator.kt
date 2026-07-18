package com.appswebnetkz.crm369

class MobileNotificationSessionCoordinator(
    private val stateStore: NotificationSyncStateHandler,
    private val scheduler: NotificationScheduler,
    private val cookiePersister: CookiePersister,
) {
    fun handlePageFinished(baseUrl: String, url: String?) {
        if (NotificationSyncPolicy.shouldSchedule(url)) {
            stateStore.enable(baseUrl)
            cookiePersister.flush()
            scheduler.scheduleImmediate()
            scheduler.schedulePeriodic()
            return
        }

        stateStore.disable(baseUrl)
        scheduler.cancelAll()
    }

    fun handleAppBackgrounded(baseUrl: String?) {
        if (baseUrl.isNullOrBlank()) {
            return
        }

        if (!stateStore.isEnabled(baseUrl)) {
            return
        }

        cookiePersister.flush()
    }

    interface NotificationSyncStateHandler {
        fun isEnabled(baseUrl: String): Boolean

        fun enable(baseUrl: String)

        fun disable(baseUrl: String)
    }

    interface NotificationScheduler {
        fun scheduleImmediate()

        fun schedulePeriodic()

        fun cancelAll()
    }

    fun interface CookiePersister {
        fun flush()
    }
}
