package com.appswebnetkz.crm369

import java.net.URI

object NotificationSyncPolicy {
    fun shouldSchedule(url: String?): Boolean {
        val path = runCatching {
            URI(url ?: return false).path
        }.getOrNull() ?: return false

        return !isAuthenticationPath(path)
    }

    private fun isAuthenticationPath(path: String): Boolean {
        val normalizedPath = path.trimEnd('/').ifBlank { "/" }

        return normalizedPath == "/login" ||
            normalizedPath == "/register" ||
            normalizedPath == "/forgot-password" ||
            normalizedPath.startsWith("/reset-password") ||
            normalizedPath == "/two-factor-challenge" ||
            normalizedPath == "/user/confirm-password" ||
            normalizedPath.startsWith("/email/verify")
    }
}
