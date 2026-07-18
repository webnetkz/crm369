package com.appswebnetkz.crm369

import java.net.URI

object DomainUrlNormalizer {
    fun normalize(rawValue: String, runningOnEmulator: Boolean = false): String? {
        val trimmedValue = rawValue.trim()

        if (trimmedValue.isEmpty()) {
            return null
        }

        val provisionalUri = runCatching {
            URI(
                if ("://" in trimmedValue) {
                    trimmedValue
                } else {
                    "https://$trimmedValue"
                },
            )
        }.getOrNull() ?: return null

        val defaultScheme = provisionalUri.host
            ?.lowercase()
            ?.let { host ->
                if (LocalDevelopmentHost.prefersHttp(host)) {
                    "http"
                } else {
                    "https"
                }
            } ?: "https"

        val valueWithScheme = if ("://" in trimmedValue) {
            trimmedValue
        } else {
            "$defaultScheme://$trimmedValue"
        }

        val uri = runCatching { URI(valueWithScheme) }.getOrNull() ?: return null
        val scheme = uri.scheme?.lowercase() ?: return null
        val host = uri.host?.lowercase()
            ?.let { resolvedHost ->
                LocalDevelopmentHost.resolveForDevice(resolvedHost, runningOnEmulator)
            } ?: return null

        if (scheme !in setOf("http", "https")) {
            return null
        }

        val port = if (uri.port == -1) {
            ""
        } else {
            ":${uri.port}"
        }

        return "$scheme://$host$port"
    }
}
