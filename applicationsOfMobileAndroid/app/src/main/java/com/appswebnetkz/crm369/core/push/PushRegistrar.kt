package com.appswebnetkz.crm369.core.push

import android.content.Context
import android.util.Log
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.network.ApiClientFactory
import com.appswebnetkz.crm369.core.session.Account
import com.appswebnetkz.crm369.core.session.AccountStore
import com.appswebnetkz.crm369.core.session.DeviceIdentity
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await

/**
 * Registers this device for push notifications on every signed in portal
 * (`PUT /api/mobile/v1/device`) and unregisters it on sign out (`DELETE /api/mobile/v1/device`).
 */
class PushRegistrar(
    private val context: Context,
    private val accountStore: AccountStore,
    private val apiFactory: ApiClientFactory,
    private val deviceIdentity: DeviceIdentity,
    private val scope: CoroutineScope,
) {
    /** Re-initializes Firebase for stored accounts at application start. */
    fun restore() {
        accountStore.accounts.value.forEach { account ->
            val options = accountStore.pushOptions(account.id) ?: return@forEach
            if (accountStore.hasToken(account.id)) {
                FirebaseBootstrap.appFor(context, options)
            }
        }
        scope.launch {
            accountStore.accounts.value
                .filter { accountStore.hasToken(it.id) }
                .forEach { registerQuietly(it) }
        }
    }

    /** Registers the current FCM token of the portal's Firebase project. */
    suspend fun register(account: Account): Boolean {
        val options = accountStore.pushOptions(account.id) ?: return false
        if (!accountStore.hasToken(account.id)) return false
        val messaging = FirebaseBootstrap.messagingFor(context, options) ?: return false
        val fcmToken = messaging.token.await()
        if (fcmToken.isNullOrBlank()) return false
        apiFactory.forAccount(account).put(
            "device",
            jsonObjectOf(
                "device_id" to deviceIdentity.deviceId,
                "device_name" to deviceIdentity.deviceName,
                "app_version" to deviceIdentity.appVersion,
                "fcm_token" to fcmToken,
            ),
        )
        accountStore.saveRegisteredFcmToken(account.id, fcmToken)
        return true
    }

    suspend fun registerQuietly(account: Account): Boolean = try {
        register(account)
    } catch (exception: CancellationException) {
        throw exception
    } catch (exception: Exception) {
        Log.w(TAG, "Push registration failed for ${account.host}", exception)
        false
    }

    /** Disables push notifications of this device on the portal (called before sign out). */
    suspend fun unregister(account: Account) {
        val fcmToken = accountStore.registeredFcmToken(account.id) ?: return
        try {
            apiFactory.forAccount(account).delete(
                "device",
                jsonObjectOf(
                    "device_id" to deviceIdentity.deviceId,
                    "fcm_token" to fcmToken,
                ),
            )
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            Log.w(TAG, "Push unregistration failed for ${account.host}", exception)
        } finally {
            accountStore.saveRegisteredFcmToken(account.id, null)
        }
    }

    /** Firebase rotated the token of the default app: register it again on every portal. */
    fun onNewToken() {
        scope.launch {
            accountStore.accounts.value
                .filter { accountStore.hasToken(it.id) && accountStore.pushOptions(it.id) != null }
                .forEach { registerQuietly(it) }
        }
    }

    private companion object {
        const val TAG = "PushRegistrar"
    }
}
