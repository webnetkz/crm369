package com.appswebnetkz.crm369

data class ResolvedWebViewNavigation(
    val baseUrl: String,
    val initialUrl: String,
    val shouldResetSession: Boolean,
)

object WebViewNavigationResolver {
    fun resolve(
        currentBaseUrl: String,
        intentBaseUrl: String?,
        intentInitialUrl: String?,
        shouldResetSession: Boolean,
    ): ResolvedWebViewNavigation {
        val resolvedBaseUrl = intentBaseUrl ?: currentBaseUrl
        val resolvedInitialUrl = intentInitialUrl ?: resolvedBaseUrl

        return ResolvedWebViewNavigation(
            baseUrl = resolvedBaseUrl,
            initialUrl = resolvedInitialUrl,
            shouldResetSession = shouldResetSession && currentBaseUrl != resolvedBaseUrl,
        )
    }
}
