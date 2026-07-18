package com.appswebnetkz.crm369

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class NotificationSyncPolicyTest {
    @Test
    fun `it skips notification sync on authentication pages`() {
        assertFalse(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/login"))
        assertFalse(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/register"))
        assertFalse(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/forgot-password"))
        assertFalse(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/reset-password/token"))
        assertFalse(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/two-factor-challenge"))
        assertFalse(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/user/confirm-password"))
        assertFalse(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/email/verify"))
    }

    @Test
    fun `it allows notification sync on authenticated pages`() {
        assertTrue(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/dashboard"))
        assertTrue(NotificationSyncPolicy.shouldSchedule("http://10.0.2.2:8000/chats"))
    }

    @Test
    fun `it skips notification sync when url is missing or invalid`() {
        assertFalse(NotificationSyncPolicy.shouldSchedule(null))
        assertFalse(NotificationSyncPolicy.shouldSchedule("not a url"))
    }
}
