package com.appswebnetkz.crm369

import org.junit.Assert.assertEquals
import org.junit.Test

class WebViewToolbarChromeTest {
    @Test
    fun `it adds the status bar inset to the existing toolbar top padding`() {
        assertEquals(32, WebViewToolbarChrome.topPadding(basePaddingTop = 8, statusBarInsetTop = 24))
    }

    @Test
    fun `it keeps the original padding when there is no status bar inset`() {
        assertEquals(8, WebViewToolbarChrome.topPadding(basePaddingTop = 8, statusBarInsetTop = 0))
    }

    @Test
    fun `it adds the navigation bar inset to the existing webview bottom padding`() {
        assertEquals(28, WebViewToolbarChrome.bottomPadding(basePaddingBottom = 8, navigationBarInsetBottom = 20))
    }

    @Test
    fun `it keeps the original bottom padding when there is no navigation bar inset`() {
        assertEquals(8, WebViewToolbarChrome.bottomPadding(basePaddingBottom = 8, navigationBarInsetBottom = 0))
    }
}
