package com.appswebnetkz.crm369.core.network

import com.appswebnetkz.crm369.core.session.Account
import com.appswebnetkz.crm369.core.session.AccountStore
import java.util.concurrent.ConcurrentHashMap
import okhttp3.OkHttpClient

/** Creates (and caches) one [ApiClient] per portal account. */
class ApiClientFactory(
    val httpClient: OkHttpClient,
    private val accountStore: AccountStore,
    private val languageProvider: () -> String,
    private val onUnauthorized: (accountId: String) -> Unit,
) {
    private val clients = ConcurrentHashMap<String, ApiClient>()

    fun forAccount(account: Account): ApiClient {
        clients[account.id]?.let { existing ->
            if (existing.baseUrl == account.baseUrl) return existing
        }
        val client = ApiClient(
            httpClient = httpClient,
            baseUrl = account.baseUrl,
            tokenProvider = { accountStore.token(account.id) },
            languageProvider = languageProvider,
            onUnauthorized = { onUnauthorized(account.id) },
        )
        clients[account.id] = client
        return client
    }

    /** A client for unauthenticated calls (sign in, two-factor challenge). */
    fun anonymous(baseUrl: String): ApiClient = ApiClient(
        httpClient = httpClient,
        baseUrl = baseUrl,
        tokenProvider = { null },
        languageProvider = languageProvider,
    )

    fun detached(): ApiClient = ApiClient.detached(httpClient)

    fun forget(accountId: String) {
        clients.remove(accountId)
    }
}
