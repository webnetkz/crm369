package com.appswebnetkz.crm369.core.portal

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class DomainNormalizerTest {

    @Test
    fun bareDomainDefaultsToHttps() {
        assertEquals("https://crm.company.kz", DomainNormalizer.normalize("crm.company.kz"))
    }

    @Test
    fun pathQueryAndCaseAreDropped() {
        assertEquals(
            "https://crm.company.kz",
            DomainNormalizer.normalize("  HTTPS://CRM.Company.KZ/dashboard?tab=1#top  "),
        )
        assertEquals("https://crm.company.kz", DomainNormalizer.normalize("crm.company.kz/"))
    }

    @Test
    fun explicitSchemeIsKept() {
        assertEquals("http://crm.company.kz", DomainNormalizer.normalize("http://crm.company.kz"))
        assertEquals("http://192.168.1.10:8000", DomainNormalizer.normalize("http://192.168.1.10:8000"))
    }

    @Test
    fun defaultPortsAreStrippedAndCustomPortsKept() {
        assertEquals("http://crm.company.kz", DomainNormalizer.normalize("http://crm.company.kz:80"))
        assertEquals("https://crm.company.kz", DomainNormalizer.normalize("https://crm.company.kz:443/"))
        assertEquals("https://crm.company.kz:8443", DomainNormalizer.normalize("crm.company.kz:8443"))
    }

    @Test
    fun trailingDotIsRemoved() {
        assertEquals("https://crm.company.kz", DomainNormalizer.normalize("https://crm.company.kz."))
    }

    @Test
    fun loopbackHostsDefaultToHttp() {
        assertEquals("http://localhost:8000", DomainNormalizer.normalize("localhost:8000"))
        assertEquals("http://127.0.0.1:8000", DomainNormalizer.normalize("127.0.0.1:8000"))
        assertEquals("http://10.0.2.2:8000", DomainNormalizer.normalize("10.0.2.2:8000"))
    }

    @Test
    fun loopbackIsMappedToHostOnEmulator() {
        assertEquals(
            "http://10.0.2.2:8000",
            DomainNormalizer.normalize("localhost:8000", runningOnEmulator = true),
        )
        assertEquals(
            "http://10.0.2.2:8000",
            DomainNormalizer.normalize("http://127.0.0.1:8000", runningOnEmulator = true),
        )
        assertEquals(
            "https://crm.company.kz",
            DomainNormalizer.normalize("crm.company.kz", runningOnEmulator = true),
        )
    }

    @Test
    fun internationalizedDomainIsConvertedToPunycode() {
        assertEquals("https://xn--e1afmkfd.xn--p1ai", DomainNormalizer.normalize("пример.рф"))
        assertEquals("https://xn--e1afmkfd.xn--p1ai", DomainNormalizer.normalize("https://пример.рф/dashboard"))
    }

    @Test
    fun invalidValuesAreRejected() {
        assertNull(DomainNormalizer.normalize(""))
        assertNull(DomainNormalizer.normalize("   "))
        assertNull(DomainNormalizer.normalize("crm company.kz"))
        assertNull(DomainNormalizer.normalize("ftp://crm.company.kz"))
        assertNull(DomainNormalizer.normalize("https://"))
        assertNull(DomainNormalizer.normalize("-crm-.company.kz"))
        assertNull(DomainNormalizer.normalize("crm_company.kz"))
        assertNull(DomainNormalizer.normalize("crm..company.kz"))
    }

    @Test
    fun hostOfReturnsLowercaseHost() {
        assertEquals("crm.company.kz", DomainNormalizer.hostOf("https://CRM.company.kz:8443"))
    }

    @Test
    fun samePortalComparesNormalizedAddresses() {
        assertTrue(DomainNormalizer.samePortal("crm.company.kz", "https://crm.company.kz/dashboard"))
        assertFalse(DomainNormalizer.samePortal("crm.company.kz", "crm.other.kz"))
        assertFalse(DomainNormalizer.samePortal("https://crm.company.kz", "https://crm.company.kz:8443"))
        assertFalse(DomainNormalizer.samePortal(null, "crm.company.kz"))
        assertFalse(DomainNormalizer.samePortal("crm.company.kz", " "))
    }

    @Test
    fun localDevelopmentHosts() {
        assertTrue(DomainNormalizer.isLocalDevelopmentHost("localhost"))
        assertTrue(DomainNormalizer.isLocalDevelopmentHost("192.168.0.5"))
        assertTrue(DomainNormalizer.isLocalDevelopmentHost("172.20.1.1"))
        assertTrue(DomainNormalizer.isLocalDevelopmentHost("crm369.test"))
        assertFalse(DomainNormalizer.isLocalDevelopmentHost("172.32.0.1"))
        assertFalse(DomainNormalizer.isLocalDevelopmentHost("crm.company.kz"))
        assertTrue(DomainNormalizer.prefersHttp("127.0.0.1"))
        assertFalse(DomainNormalizer.prefersHttp("192.168.0.5"))
    }
}
