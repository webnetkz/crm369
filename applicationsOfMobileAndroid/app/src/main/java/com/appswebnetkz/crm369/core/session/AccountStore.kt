package com.appswebnetkz.crm369.core.session

import android.content.Context
import android.content.SharedPreferences
import com.appswebnetkz.crm369.core.json.CrmJson
import com.appswebnetkz.crm369.core.json.parseJsonObject
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.portal.FirebaseClientOptions
import com.appswebnetkz.crm369.core.portal.PortalInfo
import java.security.MessageDigest
import java.util.concurrent.ConcurrentHashMap
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

/**
 * Persists the portal accounts. Account metadata is stored as JSON in private shared
 * preferences, session tokens are encrypted with [SecureStorage].
 */
class AccountStore(context: Context, private val secureStorage: SecureStorage) {

    private val preferences: SharedPreferences =
        context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    private val tokenCache = ConcurrentHashMap<String, String>()

    private val accountsState = MutableStateFlow(readAccounts())
    val accounts: StateFlow<List<Account>> = accountsState.asStateFlow()

    private val activeAccountState = MutableStateFlow(resolveActive(preferences.getString(KEY_ACTIVE_ID, null)))
    val activeAccount: StateFlow<Account?> = activeAccountState.asStateFlow()

    @Synchronized
    fun token(accountId: String): String? {
        tokenCache[accountId]?.let { return it }
        val encrypted = preferences.getString(tokenKey(accountId), null) ?: return null
        val token = secureStorage.decrypt(encrypted)
        if (token.isNullOrBlank()) {
            preferences.edit().remove(tokenKey(accountId)).apply()
            return null
        }
        tokenCache[accountId] = token
        return token
    }

    fun hasToken(accountId: String): Boolean = token(accountId) != null

    /** Adds or replaces an account together with its session token. */
    @Synchronized
    fun save(account: Account, token: String) {
        tokenCache[account.id] = token
        preferences.edit().putString(tokenKey(account.id), secureStorage.encrypt(token)).apply()
        upsert(account)
    }

    /** Updates the display data of an existing account (portal name, user name, avatar). */
    @Synchronized
    fun update(account: Account) {
        if (accountsState.value.any { it.id == account.id }) {
            upsert(account)
        }
    }

    @Synchronized
    fun clearToken(accountId: String) {
        tokenCache.remove(accountId)
        preferences.edit()
            .remove(tokenKey(accountId))
            .remove(fcmTokenKey(accountId))
            .apply()
    }

    @Synchronized
    fun remove(accountId: String) {
        tokenCache.remove(accountId)
        val remaining = accountsState.value.filterNot { it.id == accountId }
        preferences.edit()
            .remove(tokenKey(accountId))
            .remove(pushKey(accountId))
            .remove(fcmTokenKey(accountId))
            .apply()
        writeAccounts(remaining)
        if (activeAccountState.value?.id == accountId) {
            setActive(remaining.firstOrNull { hasToken(it.id) }?.id ?: remaining.firstOrNull()?.id)
        }
    }

    @Synchronized
    fun setActive(accountId: String?) {
        preferences.edit().putString(KEY_ACTIVE_ID, accountId).apply()
        activeAccountState.value = resolveActive(accountId)
    }

    fun account(accountId: String): Account? = accountsState.value.firstOrNull { it.id == accountId }

    fun pushOptions(accountId: String): FirebaseClientOptions? {
        val raw = preferences.getString(pushKey(accountId), null) ?: return null
        return FirebaseClientOptions.fromJson(parseJsonObject(raw))
    }

    fun savePushOptions(accountId: String, options: FirebaseClientOptions?) {
        val editor = preferences.edit()
        if (options == null) editor.remove(pushKey(accountId)) else editor.putString(pushKey(accountId), options.toJson().toString())
        editor.apply()
    }

    fun registeredFcmToken(accountId: String): String? = preferences.getString(fcmTokenKey(accountId), null)

    fun saveRegisteredFcmToken(accountId: String, token: String?) {
        val editor = preferences.edit()
        if (token == null) editor.remove(fcmTokenKey(accountId)) else editor.putString(fcmTokenKey(accountId), token)
        editor.apply()
    }

    fun recentPortals(): List<RecentPortal> {
        val raw = preferences.getString(KEY_RECENT_PORTALS, null) ?: return emptyList()
        val array = try {
            CrmJson.parseToJsonElement(raw) as? JsonArray
        } catch (_: Exception) {
            null
        } ?: return emptyList()
        return array.mapNotNull { element ->
            val json = element as? JsonObject ?: return@mapNotNull null
            val baseUrl = json.text("base_url") ?: return@mapNotNull null
            RecentPortal(baseUrl = baseUrl, name = json.text("name") ?: baseUrl, logoUrl = json.text("logo_url"))
        }
    }

    @Synchronized
    fun rememberPortal(info: PortalInfo) {
        val updated = listOf(RecentPortal(info.baseUrl, info.name, info.logoUrl)) +
            recentPortals().filterNot { it.baseUrl.equals(info.baseUrl, ignoreCase = true) }
        val array = JsonArray(
            updated.take(MAX_RECENT_PORTALS).map { portal ->
                buildJsonObject {
                    put("base_url", portal.baseUrl)
                    put("name", portal.name)
                    put("logo_url", portal.logoUrl)
                }
            },
        )
        preferences.edit().putString(KEY_RECENT_PORTALS, array.toString()).apply()
    }

    @Synchronized
    fun forgetPortal(baseUrl: String) {
        val updated = recentPortals().filterNot { it.baseUrl.equals(baseUrl, ignoreCase = true) }
        val array = JsonArray(
            updated.map { portal ->
                buildJsonObject {
                    put("base_url", portal.baseUrl)
                    put("name", portal.name)
                    put("logo_url", portal.logoUrl)
                }
            },
        )
        preferences.edit().putString(KEY_RECENT_PORTALS, array.toString()).apply()
    }

    private fun upsert(account: Account) {
        val current = accountsState.value
        val updated = if (current.any { it.id == account.id }) {
            current.map { if (it.id == account.id) account else it }
        } else {
            current + account
        }
        writeAccounts(updated)
        if (activeAccountState.value?.id == account.id) {
            activeAccountState.value = account
        }
    }

    private fun writeAccounts(accounts: List<Account>) {
        val array = JsonArray(accounts.map { it.toJson() })
        preferences.edit().putString(KEY_ACCOUNTS, array.toString()).apply()
        accountsState.value = accounts
    }

    private fun readAccounts(): List<Account> {
        val raw = preferences.getString(KEY_ACCOUNTS, null) ?: return emptyList()
        val array = try {
            CrmJson.parseToJsonElement(raw) as? JsonArray
        } catch (_: Exception) {
            null
        } ?: return emptyList()
        return array.mapNotNull { element -> (element as? JsonObject)?.let { Account.fromJson(it) } }
    }

    private fun resolveActive(accountId: String?): Account? {
        val accounts = accountsState.value
        return accounts.firstOrNull { it.id == accountId }
    }

    private fun tokenKey(accountId: String): String = "token_" + hash(accountId)

    private fun pushKey(accountId: String): String = "push_" + hash(accountId)

    private fun fcmTokenKey(accountId: String): String = "fcm_" + hash(accountId)

    private fun hash(value: String): String {
        val digest = MessageDigest.getInstance("SHA-256").digest(value.toByteArray(Charsets.UTF_8))
        return digest.joinToString("") { byte -> "%02x".format(byte) }
    }

    private companion object {
        const val PREFERENCES_NAME = "crm369_accounts"
        const val KEY_ACCOUNTS = "accounts"
        const val KEY_ACTIVE_ID = "active_account_id"
        const val KEY_RECENT_PORTALS = "recent_portals"
        const val MAX_RECENT_PORTALS = 8
    }
}
