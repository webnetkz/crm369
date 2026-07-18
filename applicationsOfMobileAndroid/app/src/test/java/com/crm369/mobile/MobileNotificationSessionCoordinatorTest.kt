package com.appswebnetkz.crm369

import org.junit.Assert.assertEquals
import org.junit.Test

class MobileNotificationSessionCoordinatorTest {
    @Test
    fun `it enables sync, persists cookies, and schedules work on authenticated pages`() {
        val stateStore = FakeStateStore()
        val scheduler = FakeScheduler()
        val cookiePersister = FakeCookiePersister()
        val coordinator = MobileNotificationSessionCoordinator(stateStore, scheduler, cookiePersister)

        coordinator.handlePageFinished("https://crm369.test", "https://crm369.test/dashboard")

        assertEquals(setOf("https://crm369.test"), stateStore.enabledBaseUrls)
        assertEquals(1, cookiePersister.flushCalls)
        assertEquals(1, scheduler.immediateCalls)
        assertEquals(1, scheduler.periodicCalls)
        assertEquals(0, scheduler.cancelCalls)
    }

    @Test
    fun `it disables sync and cancels work on authentication pages`() {
        val stateStore = FakeStateStore()
        val scheduler = FakeScheduler()
        val cookiePersister = FakeCookiePersister()
        val coordinator = MobileNotificationSessionCoordinator(stateStore, scheduler, cookiePersister)

        coordinator.handlePageFinished("https://crm369.test", "https://crm369.test/login")

        assertEquals(listOf("https://crm369.test"), stateStore.disabledBaseUrls)
        assertEquals(0, cookiePersister.flushCalls)
        assertEquals(0, scheduler.immediateCalls)
        assertEquals(0, scheduler.periodicCalls)
        assertEquals(1, scheduler.cancelCalls)
    }

    @Test
    fun `it persists cookies when the app is backgrounded with enabled sync`() {
        val stateStore = FakeStateStore(enabledBaseUrls = mutableSetOf("https://crm369.test"))
        val scheduler = FakeScheduler()
        val cookiePersister = FakeCookiePersister()
        val coordinator = MobileNotificationSessionCoordinator(stateStore, scheduler, cookiePersister)

        coordinator.handleAppBackgrounded("https://crm369.test")

        assertEquals(1, cookiePersister.flushCalls)
    }

    @Test
    fun `it skips cookie persistence when sync is disabled or url is missing`() {
        val stateStore = FakeStateStore()
        val scheduler = FakeScheduler()
        val cookiePersister = FakeCookiePersister()
        val coordinator = MobileNotificationSessionCoordinator(stateStore, scheduler, cookiePersister)

        coordinator.handleAppBackgrounded("https://crm369.test")
        coordinator.handleAppBackgrounded(null)
        coordinator.handleAppBackgrounded("")

        assertEquals(0, cookiePersister.flushCalls)
    }

    private class FakeStateStore(
        enabledBaseUrls: MutableSet<String> = mutableSetOf(),
    ) : MobileNotificationSessionCoordinator.NotificationSyncStateHandler {
        val enabledBaseUrls = enabledBaseUrls
        val disabledBaseUrls = mutableListOf<String>()

        override fun isEnabled(baseUrl: String): Boolean {
            return enabledBaseUrls.contains(baseUrl)
        }

        override fun enable(baseUrl: String) {
            enabledBaseUrls.add(baseUrl)
        }

        override fun disable(baseUrl: String) {
            enabledBaseUrls.remove(baseUrl)
            disabledBaseUrls.add(baseUrl)
        }
    }

    private class FakeScheduler : MobileNotificationSessionCoordinator.NotificationScheduler {
        var immediateCalls = 0
        var periodicCalls = 0
        var cancelCalls = 0

        override fun scheduleImmediate() {
            immediateCalls += 1
        }

        override fun schedulePeriodic() {
            periodicCalls += 1
        }

        override fun cancelAll() {
            cancelCalls += 1
        }
    }

    private class FakeCookiePersister : MobileNotificationSessionCoordinator.CookiePersister {
        var flushCalls = 0

        override fun flush() {
            flushCalls += 1
        }
    }
}
