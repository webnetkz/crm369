package com.appswebnetkz.crm369

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class DomainUrlNormalizerTest {
    @Test
    fun `it adds https to a bare domain`() {
        assertEquals(
            "https://crm.company.com",
            DomainUrlNormalizer.normalize("crm.company.com"),
        )
    }

    @Test
    fun `it defaults bare local ip addresses to http`() {
        assertEquals(
            "http://192.168.8.19:8000",
            DomainUrlNormalizer.normalize("192.168.8.19:8000"),
        )
    }

    @Test
    fun `it keeps an explicit http scheme and port`() {
        assertEquals(
            "http://192.168.1.15:8080",
            DomainUrlNormalizer.normalize("http://192.168.1.15:8080"),
        )
    }

    @Test
    fun `it maps loopback to host machine alias on emulator`() {
        assertEquals(
            "http://10.0.2.2:8000",
            DomainUrlNormalizer.normalize("127.0.0.1:8000", runningOnEmulator = true),
        )
    }

    @Test
    fun `it strips path query and fragment from the saved base url`() {
        assertEquals(
            "https://crm.company.com",
            DomainUrlNormalizer.normalize("https://crm.company.com/login?from=app#start"),
        )
    }

    @Test
    fun `it rejects unsupported schemes`() {
        assertNull(DomainUrlNormalizer.normalize("ftp://crm.company.com"))
    }

    @Test
    fun `it rejects blank values`() {
        assertNull(DomainUrlNormalizer.normalize("   "))
    }
}
