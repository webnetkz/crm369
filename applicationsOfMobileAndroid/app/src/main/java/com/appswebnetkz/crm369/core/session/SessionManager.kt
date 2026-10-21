package com.appswebnetkz.crm369.core.session

import com.appswebnetkz.crm369.core.json.bool
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.ApiClientFactory
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.core.portal.DomainNormalizer
import com.appswebnetkz.crm369.core.portal.PortalDiscovery
import com.appswebnetkz.crm369.core.portal.PortalInfo
import com.appswebnetkz.crm369.core.push.PushRegistrar
import java.util.concurrent.ConcurrentHashMap
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject

sealed class LoginResult {
    data class Success(val account: Account) : LoginResult()

    /** The user has two-factor authentication enabled: ask for a code or a recovery code. */
    data class TwoFactorRequired(val challenge: String, val expiresInSeconds: Int) : LoginResult()
}

/**
 * Owns the portal accounts and the current session: portal discovery, sign in (with two-factor
 * challenge), bootstrap data, account switching, sign out and the web session bridge.
 */
class SessionManager(
    private val accountStore: AccountStore,
    private val deviceIdentity: DeviceIdentity,
    private val apiFactory: ApiClientFactory,
    private val discovery: PortalDiscovery,
    private val pushRegistrar: PushRegistrar,
    private val scope: CoroutineScope,
) {
    val accounts: StateFlow<List<Account>> = accountStore.accounts
    val activeAccount: StateFlow<Account?> = accountStore.activeAccount

    private val bootstrapState = MutableStateFlow<Bootstrap?>(null)

    /** Bootstrap of the active account (null while loading or when signed out). */
    val bootstrap: StateFlow<Bootstrap?> = bootstrapState.asStateFlow()

    private val sessionExpiredEvents = MutableSharedFlow<Account>(extraBufferCapacity = 4)

    /** Emitted when the server rejected the token of an account (revoked or expired session). */
    val sessionExpired: SharedFlow<Account> = sessionExpiredEvents.asSharedFlow()

    private val discoveredPortals = ConcurrentHashMap<String, PortalInfo>()

    /** API client of the active account. Never null: a detached client is returned when signed out. */
    fun api(): ApiClient {
        val account = activeAccount.value ?: return apiFactory.detached()
        return apiFactory.forAccount(account)
    }

    fun apiFor(account: Account): ApiClient = apiFactory.forAccount(account)

    fun hasSession(account: Account): Boolean = accountStore.hasToken(account.id)

    fun recentPortals(): List<RecentPortal> = accountStore.recentPortals()

    fun forgetPortal(baseUrl: String) = accountStore.forgetPortal(baseUrl)

    /** Validates a portal address and returns the portal information. */
    suspend fun discover(address: String): PortalInfo {
        val info = discovery.discover(address, deviceIdentity.isEmulator)
        discoveredPortals[info.baseUrl.lowercase()] = info
        accountStore.rememberPortal(info)
        return info
    }

    fun cachedPortal(baseUrl: String): PortalInfo? = discoveredPortals[baseUrl.trimEnd('/').lowercase()]

    suspend fun login(portal: PortalInfo, email: String, password: String): LoginResult {
        val response = apiFactory.anonymous(portal.baseUrl).post(
            "login",
            jsonObjectOf(
                "email" to email.trim(),
                "password" to password,
                "device_id" to deviceIdentity.deviceId,
                "device_name" to deviceIdentity.deviceName,
                "app_version" to deviceIdentity.appVersion,
            ),
        )
        return handleLoginResponse(portal, response)
    }

    suspend fun completeTwoFactor(
        portal: PortalInfo,
        challenge: String,
        code: String?,
        recoveryCode: String?,
    ): LoginResult {
        val body = if (!recoveryCode.isNullOrBlank()) {
            jsonObjectOf("challenge" to challenge, "recovery_code" to recoveryCode.trim())
        } else {
            jsonObjectOf("challenge" to challenge, "code" to code.orEmpty().filter { it.isDigit() })
        }
        val response = apiFactory.anonymous(portal.baseUrl).post("two-factor-challenge", body)
        return handleLoginResponse(portal, response)
    }

    private fun handleLoginResponse(portal: PortalInfo, response: JsonObject): LoginResult {
        if (response.bool("two_factor_required") == true) {
            val challenge = response.text("challenge") ?: throw ApiException(500, "")
            return LoginResult.TwoFactorRequired(challenge, response.int("expires_in") ?: 300)
        }

        val data = response.obj("data") ?: throw ApiException(500, "")
        val token = data.text("token") ?: throw ApiException(500, "")
        val user = UserSummary.fromJson(data.obj("user"))
        val userId = data.obj("user").long("id") ?: throw ApiException(500, "")

        val account = Account(
            id = Account.idFor(portal.baseUrl, userId),
            baseUrl = portal.baseUrl,
            portalName = portal.name,
            logoUrl = portal.logoUrl,
            userId = userId,
            userName = user.fullName,
            email = user.email,
            avatarUrl = user.avatarUrl,
        )

        apiFactory.forget(account.id)
        accountStore.save(account, token)
        accountStore.savePushOptions(account.id, portal.push)
        accountStore.setActive(account.id)
        bootstrapState.value = null

        scope.launch {
            refreshBootstrapQuietly()
            pushRegistrar.registerQuietly(account)
        }

        return LoginResult.Success(account)
    }

    /** Loads `GET bootstrap` for the active account and updates the stored account data. */
    suspend fun refreshBootstrap(): Bootstrap? {
        val account = activeAccount.value ?: return null
        if (!accountStore.hasToken(account.id)) return null

        val response = apiFor(account).get("bootstrap")
        val bootstrap = Bootstrap.parse(account.id, response.dataObject())

        val updated = account.copy(
            portalName = bootstrap.portalName,
            logoUrl = bootstrap.portalLogoUrl ?: account.logoUrl,
            userName = bootstrap.user.fullName,
            email = bootstrap.user.email.ifBlank { account.email },
            avatarUrl = bootstrap.user.avatarUrl,
        )
        if (updated != account) {
            accountStore.update(updated)
        }

        val previousPush = accountStore.pushOptions(account.id)
        accountStore.savePushOptions(account.id, bootstrap.push)

        if (activeAccount.value?.id == account.id) {
            bootstrapState.value = bootstrap
        }

        if (bootstrap.push != null && (previousPush != bootstrap.push || accountStore.registeredFcmToken(account.id) == null)) {
            scope.launch { pushRegistrar.registerQuietly(updated) }
        }

        return bootstrap
    }

    /** Same as [refreshBootstrap] but never throws (network errors keep the previous data). */
    suspend fun refreshBootstrapQuietly(): Bootstrap? = try {
        refreshBootstrap()
    } catch (exception: CancellationException) {
        throw exception
    } catch (_: Exception) {
        null
    }

    /** Locally adjusts the unread counters (e.g. after a chat was read) until the next refresh. */
    fun updateCounters(notifications: Int? = null, chats: Int? = null) {
        val current = bootstrapState.value ?: return
        bootstrapState.value = current.copy(
            unreadNotifications = notifications ?: current.unreadNotifications,
            unreadChats = chats ?: current.unreadChats,
        )
    }

    fun switchAccount(accountId: String) {
        if (activeAccount.value?.id == accountId) return
        accountStore.setActive(accountId)
        bootstrapState.value = null
        scope.launch { refreshBootstrapQuietly() }
    }

    /** Signs out of [account] (the active one by default) and removes it from the device. */
    suspend fun logout(account: Account? = activeAccount.value) {
        if (account == null) return
        if (accountStore.hasToken(account.id)) {
            pushRegistrar.unregister(account)
            try {
                apiFor(account).delete("logout")
            } catch (exception: CancellationException) {
                throw exception
            } catch (_: Exception) {
                // The session is removed locally even when the server cannot be reached.
            }
        }
        val wasActive = activeAccount.value?.id == account.id
        accountStore.remove(account.id)
        apiFactory.forget(account.id)
        if (wasActive) {
            bootstrapState.value = null
            if (activeAccount.value != null) {
                scope.launch { refreshBootstrapQuietly() }
            }
        }
    }

    /**
     * Returns a single-use URL that opens [path] of the portal in the in-app browser with the
     * current user signed in (`POST web-session`).
     */
    suspend fun openWebSession(path: String): String {
        val response = api().post("web-session", jsonObjectOf("path" to path))
        return response.dataObject().text("url") ?: throw ApiException(500, "")
    }

    /** Portal base URL of the active account, if any. */
    fun activeBaseUrl(): String? = activeAccount.value?.baseUrl

    /** Finds a stored account for a portal URL (used by push notifications of other portals). */
    fun accountForPortal(portalUrl: String?): Account? {
        if (portalUrl.isNullOrBlank()) return null
        val candidates = accounts.value.filter { DomainNormalizer.samePortal(it.baseUrl, portalUrl) }
        return candidates.firstOrNull { it.id == activeAccount.value?.id } ?: candidates.firstOrNull { hasSession(it) }
    }

    /**
     * Ends the session of [accountId] on this device without calling the server and keeps the
     * account in the list (for example after the e-mail was changed: the portal requires the new
     * address to be verified, so the token stops working). [email] updates the stored e-mail used
     * to prefill the sign in form.
     */
    fun clearSession(accountId: String, email: String? = null) {
        val account = accountStore.account(accountId) ?: return
        accountStore.clearToken(accountId)
        apiFactory.forget(accountId)
        if (!email.isNullOrBlank() && email != account.email) {
            accountStore.update(account.copy(email = email))
        }
        if (activeAccount.value?.id == accountId) {
            bootstrapState.value = null
        }
    }

    internal fun handleUnauthorized(accountId: String) {
        val account = accountStore.account(accountId) ?: return
        if (!accountStore.hasToken(accountId)) return
        accountStore.clearToken(accountId)
        apiFactory.forget(accountId)
        if (activeAccount.value?.id == accountId) {
            bootstrapState.value = null
        }
        sessionExpiredEvents.tryEmit(account)
    }

    /** E-mail of the account (used to prefill the sign in form after the session expired). */
    fun emailFor(baseUrl: String): String? =
        accounts.value.firstOrNull { it.baseUrl.equals(baseUrl, ignoreCase = true) }?.email?.takeIf { it.isNotBlank() }

    /** Pretty name of the user for a stored account. */
    fun displayName(account: Account): String = account.userName.ifBlank { account.email }

    /** Last known e-mail typed for a portal before the account existed. */
    fun lastKnownEmail(): String? = accounts.value.lastOrNull()?.email?.takeIf { it.isNotBlank() }

    /** Server message helper for unknown API payloads. */
    fun messageOf(response: JsonObject): String? = response.str("message")
}
