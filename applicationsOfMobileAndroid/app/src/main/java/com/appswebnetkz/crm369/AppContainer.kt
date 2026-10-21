package com.appswebnetkz.crm369

import android.app.Application
import com.appswebnetkz.crm369.core.files.FileTransfer
import com.appswebnetkz.crm369.core.network.ApiClientFactory
import com.appswebnetkz.crm369.core.portal.PortalDiscovery
import com.appswebnetkz.crm369.core.push.PushKeys
import com.appswebnetkz.crm369.core.push.PushRegistrar
import com.appswebnetkz.crm369.core.session.AccountStore
import com.appswebnetkz.crm369.core.session.DeviceIdentity
import com.appswebnetkz.crm369.core.session.SecureStorage
import com.appswebnetkz.crm369.core.session.SessionManager
import com.appswebnetkz.crm369.core.settings.AppLanguage
import com.appswebnetkz.crm369.core.settings.AppSettings
import com.appswebnetkz.crm369.core.util.AppMessenger
import java.util.concurrent.TimeUnit
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor

/** Manual dependency container created once by [Crm369Application]. */
class AppContainer(val application: Application) {

    val appScope: CoroutineScope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)

    val settings = AppSettings(application)

    val messenger = AppMessenger(application)

    val deviceIdentity = DeviceIdentity(application)

    val accountStore = AccountStore(application, SecureStorage())

    val httpClient: OkHttpClient = OkHttpClient.Builder()
        .connectTimeout(20, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
        .writeTimeout(180, TimeUnit.SECONDS)
        .retryOnConnectionFailure(true)
        .apply {
            if (BuildConfig.DEBUG) {
                addInterceptor(
                    HttpLoggingInterceptor().apply {
                        level = HttpLoggingInterceptor.Level.BASIC
                        redactHeader("Authorization")
                    },
                )
            }
        }
        .build()

    val apiFactory = ApiClientFactory(
        httpClient = httpClient,
        accountStore = accountStore,
        languageProvider = { AppLanguage.currentTag() },
        onUnauthorized = { accountId -> session.handleUnauthorized(accountId) },
    )

    val pushRegistrar = PushRegistrar(
        context = application,
        accountStore = accountStore,
        apiFactory = apiFactory,
        deviceIdentity = deviceIdentity,
        scope = appScope,
    )

    val session: SessionManager by lazy {
        SessionManager(
            accountStore = accountStore,
            deviceIdentity = deviceIdentity,
            apiFactory = apiFactory,
            discovery = PortalDiscovery(httpClient) { AppLanguage.currentTag() },
            pushRegistrar = pushRegistrar,
            scope = appScope,
        )
    }

    val fileTransfer = FileTransfer(application)

    /** A push message arrived while the application is running: refresh the unread counters. */
    fun onPushReceived(data: Map<String, String>) {
        val portalUrl = data[PushKeys.PORTAL_URL]
        val active = session.activeAccount.value ?: return
        if (portalUrl.isNullOrBlank() || session.accountForPortal(portalUrl)?.id == active.id) {
            appScope.launch { session.refreshBootstrapQuietly() }
        }
    }
}
