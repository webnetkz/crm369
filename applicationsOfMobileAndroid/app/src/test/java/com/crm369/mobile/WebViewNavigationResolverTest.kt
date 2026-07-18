package com.appswebnetkz.crm369

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class WebViewNavigationResolverTest {
    @Test
    fun `it keeps session when opening another chat on the same domain`() {
        val navigation = WebViewNavigationResolver.resolve(
            currentBaseUrl = "https://crm369.test",
            intentBaseUrl = "https://crm369.test",
            intentInitialUrl = "https://crm369.test/chats?conversation=12",
            shouldResetSession = true,
        )

        assertEquals("https://crm369.test", navigation.baseUrl)
        assertEquals(
            "https://crm369.test/chats?conversation=12",
            navigation.initialUrl,
        )
        assertFalse(navigation.shouldResetSession)
    }

    @Test
    fun `it resets session when switching to another domain`() {
        val navigation = WebViewNavigationResolver.resolve(
            currentBaseUrl = "https://crm369.test",
            intentBaseUrl = "https://demo.crm369.test",
            intentInitialUrl = "https://demo.crm369.test/chats?conversation=12",
            shouldResetSession = true,
        )

        assertTrue(navigation.shouldResetSession)
    }
}
