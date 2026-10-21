package com.appswebnetkz.crm369

import android.content.Intent
import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.appswebnetkz.crm369.core.push.PushKeys
import com.appswebnetkz.crm369.ui.navigation.Crm369Root
import com.appswebnetkz.crm369.ui.navigation.PendingLink
import com.appswebnetkz.crm369.ui.theme.Crm369Theme

/**
 * Single activity of the application. Extends AppCompatActivity so that the per-app language
 * (AppCompatDelegate.setApplicationLocales) works on every supported Android version.
 */
class MainActivity : AppCompatActivity() {

    private val pendingLink = mutableStateOf<PendingLink?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)

        if (savedInstanceState == null) {
            handleIntent(intent)
        }

        val container = (application as Crm369Application).container

        setContent {
            val themeMode by container.settings.themeMode.collectAsStateWithLifecycle()
            val link by pendingLink
            Crm369Theme(themeMode = themeMode) {
                Crm369Root(
                    container = container,
                    pendingLink = link,
                    onLinkConsumed = { pendingLink.value = null },
                )
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleIntent(intent)
    }

    /** Push notification taps carry `action_path` / `portal_url` extras (see FirebaseCloudMessagingClient). */
    private fun handleIntent(intent: Intent?) {
        if (intent == null) return
        val path = intent.getStringExtra(PushKeys.ACTION_PATH)
        val portalUrl = intent.getStringExtra(PushKeys.PORTAL_URL)
        if (intent.action == PushKeys.ACTION || !path.isNullOrBlank()) {
            pendingLink.value = PendingLink(path = path, portalUrl = portalUrl)
        }
    }
}
