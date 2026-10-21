package com.appswebnetkz.crm369.core.network

import java.net.URLDecoder

/** File name helpers for downloads (pure Kotlin, unit tested on the JVM). */
object FileNames {

    /**
     * File name from a `Content-Disposition` header. The RFC 5987 form
     * (`filename*=utf-8''%D0%9E%D1%82...`) wins over the plain ASCII fallback (`filename="..."`).
     */
    fun fromContentDisposition(header: String?): String? {
        if (header.isNullOrBlank()) return null
        val parts = header.split(';').map { it.trim() }
        val extended = parts.firstOrNull { it.startsWith("filename*=", ignoreCase = true) }
        if (extended != null) {
            val raw = extended.substringAfter('=').trim().trim('"')
            val encoded = if (raw.contains("''")) raw.substringAfter("''") else raw
            val decoded = try {
                URLDecoder.decode(encoded.replace("+", "%2B"), "UTF-8")
            } catch (_: Exception) {
                encoded
            }
            if (decoded.isNotBlank()) return decoded
        }
        val plain = parts.firstOrNull { it.startsWith("filename=", ignoreCase = true) }
            ?.substringAfter('=')
            ?.trim()
            ?.trim('"')
        return plain?.takeIf { it.isNotBlank() }
    }

    /** Removes characters that are not allowed in file names; never returns an empty name. */
    fun sanitize(name: String): String {
        val cleaned = name.replace(Regex("[\\\\/:*?\"<>|\\u0000-\\u001F]"), "_").trim().trim('.')
        return cleaned.ifBlank { "file" }.take(150)
    }
}
