package com.appswebnetkz.crm369

import org.junit.Assert.assertEquals
import org.junit.Test

class MobileRouteResolverTest {
    @Test
    fun `it builds the quick qr scanner url for a configured domain`() {
        assertEquals(
            "https://crm369.test/qr",
            MobileRouteResolver.qrScannerUrl("https://crm369.test/"),
        )
    }
}
