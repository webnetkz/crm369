package com.appswebnetkz.crm369

import org.junit.Assert.assertEquals
import org.junit.Test

class MobileNotificationStyleTest {
    @Test
    fun `it uses a dedicated notification icon for the system notification icon`() {
        assertEquals(R.drawable.ic_notification_small, MobileNotificationStyle.smallIconResId())
    }

    @Test
    fun `it uses the crm369 logo for the notification card icon`() {
        assertEquals(R.drawable.crm369_logo, MobileNotificationStyle.largeIconResId())
    }

    @Test
    fun `it uses the brand seed color for notification tint`() {
        assertEquals(R.color.seed, MobileNotificationStyle.accentColorResId())
    }
}
