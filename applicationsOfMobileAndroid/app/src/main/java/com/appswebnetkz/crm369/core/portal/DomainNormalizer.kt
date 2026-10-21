package com.appswebnetkz.crm369.core.portal

import java.net.IDN
import java.net.URI

/**
 * Turns what a user types into the portal address field ("crm.company.kz",
 * "https://crm.company.kz/dashboard", "192.168.1.10:8000") into a normalized base URL
 * ("https://crm.company.kz"). Pure Kotlin so it can be unit tested on the JVM.
 */
object DomainNormalizer {
    private const val EMULATOR_HOST_LOOPBACK_ALIAS = "10.0.2.2"

    private val privateNetworkPatterns = listOf(
        Regex("""^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$"""),
        Regex("""^192\.168\.\d{1,3}\.\d{1,3}$"""),
        Regex("""^172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}$"""),
    )

    fun normalize(rawValue: String, runningOnEmulator: Boolean = false): String? {
        val trimmed = rawValue.trim()
        if (trimmed.isEmpty() || trimmed.any { it.isWhitespace() }) {
            return null
        }

        val value = toAsciiAuthority(trimmed) ?: return null

        val provisional = parse(if ("://" in value) value else "https://$value") ?: return null
        val provisionalHost = provisional.host?.lowercase() ?: return null

        val defaultScheme = if (prefersHttp(provisionalHost)) "http" else "https"
        val withScheme = if ("://" in value) value else "$defaultScheme://$value"

        val uri = parse(withScheme) ?: return null
        val scheme = uri.scheme?.lowercase() ?: return null
        if (scheme != "http" && scheme != "https") {
            return null
        }

        val host = uri.host?.lowercase()?.trimEnd('.') ?: return null
        if (!isValidHost(host)) {
            return null
        }

        val resolvedHost = if (runningOnEmulator && isLoopbackHost(host)) EMULATOR_HOST_LOOPBACK_ALIAS else host
        val port = when {
            uri.port == -1 -> ""
            scheme == "https" && uri.port == 443 -> ""
            scheme == "http" && uri.port == 80 -> ""
            else -> ":${uri.port}"
        }

        return "$scheme://$resolvedHost$port"
    }

    /** Host of a normalized base URL, e.g. `crm.company.kz`. */
    fun hostOf(baseUrl: String): String = parse(baseUrl)?.host?.lowercase() ?: baseUrl

    /** True when two base URLs point to the same portal (scheme, host and port). */
    fun samePortal(first: String?, second: String?): Boolean {
        if (first.isNullOrBlank() || second.isNullOrBlank()) return false
        val a = normalize(first) ?: return false
        val b = normalize(second) ?: return false
        return a.equals(b, ignoreCase = true)
    }

    fun prefersHttp(host: String): Boolean = isLoopbackHost(host) || host == EMULATOR_HOST_LOOPBACK_ALIAS

    fun isLocalDevelopmentHost(host: String): Boolean =
        isLoopbackHost(host) ||
            isPrivateIpv4(host) ||
            host.endsWith(".test") ||
            host.endsWith(".local") ||
            host == EMULATOR_HOST_LOOPBACK_ALIAS

    private fun isLoopbackHost(host: String): Boolean =
        host == "localhost" || host == "127.0.0.1" || host == "::1" || host == "[::1]"

    private fun isPrivateIpv4(host: String): Boolean = privateNetworkPatterns.any { it.matches(host) }

    private fun isValidHost(host: String): Boolean {
        if (host.isEmpty() || host.length > 253) return false
        if (host.startsWith("[") && host.endsWith("]")) return true
        return host.split('.').all { label ->
            label.isNotEmpty() &&
                label.length <= 63 &&
                !label.startsWith('-') &&
                !label.endsWith('-') &&
                label.all { it in 'a'..'z' || it in 'A'..'Z' || it in '0'..'9' || it == '-' }
        }
    }

    /** Converts an internationalized host name (e.g. `црм.компания.қаз`) to its ASCII form. */
    private fun toAsciiAuthority(value: String): String? {
        if (value.all { it.code < 128 }) return value
        val schemeSeparator = value.indexOf("://")
        val scheme = if (schemeSeparator >= 0) value.substring(0, schemeSeparator + 3) else ""
        val rest = if (schemeSeparator >= 0) value.substring(schemeSeparator + 3) else value
        val authorityEnd = rest.indexOfFirst { it == '/' || it == '?' || it == '#' }.let { if (it < 0) rest.length else it }
        val authority = rest.substring(0, authorityEnd)
        val tail = rest.substring(authorityEnd)
        val hostPart = authority.substringBefore(':')
        val portPart = if (authority.contains(':')) ":" + authority.substringAfter(':') else ""
        val asciiHost = try {
            IDN.toASCII(hostPart, IDN.ALLOW_UNASSIGNED)
        } catch (_: Exception) {
            return null
        }
        val asciiTail = if (tail.all { it.code < 128 }) tail else ""
        return scheme + asciiHost + portPart + asciiTail
    }

    private fun parse(value: String): URI? = try {
        URI(value)
    } catch (_: Exception) {
        null
    }
}
