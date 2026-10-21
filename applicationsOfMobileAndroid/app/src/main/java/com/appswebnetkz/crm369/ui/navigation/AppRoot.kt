package com.appswebnetkz.crm369.ui.navigation

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.navigation.compose.rememberNavController
import com.appswebnetkz.crm369.AppContainer
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.AppSnackbarHost

/** A link the application was opened with (push notification tap). */
data class PendingLink(
    val path: String?,
    val portalUrl: String?,
)

/**
 * Root of the user interface: provides the container, navigator and messenger, hosts the
 * navigation graph and handles session expiry and push notification links.
 */
@Composable
fun Crm369Root(
    container: AppContainer,
    pendingLink: PendingLink?,
    onLinkConsumed: () -> Unit,
) {
    val navController = rememberNavController()
    val context = LocalContext.current
    val navigator = remember(navController) {
        AppNavigator(navController, context.applicationContext) { container.session.activeBaseUrl() }
    }
    val startDestination = remember {
        if (container.session.activeAccount.value == null) AppRoutes.PORTAL else AppRoutes.MAIN
    }

    CompositionLocalProvider(
        LocalAppContainer provides container,
        LocalNavigator provides navigator,
        LocalMessenger provides container.messenger,
    ) {
        Surface(color = MaterialTheme.colorScheme.background, modifier = Modifier.fillMaxSize()) {
            Box(modifier = Modifier.fillMaxSize()) {
                AppNavHost(navController = navController, startDestination = startDestination)
                AppSnackbarHost(messenger = container.messenger, modifier = Modifier.align(Alignment.BottomCenter))
            }
        }
    }

    // A stored account whose session is gone (revoked token) must sign in again.
    LaunchedEffect(Unit) {
        val account = container.session.activeAccount.value
        if (account != null && !container.session.hasSession(account)) {
            navigator.resetToLogin(account.baseUrl, account.email)
        }
    }

    LaunchedEffect(Unit) {
        container.session.sessionExpired.collect { account ->
            if (account.id == container.session.activeAccount.value?.id) {
                container.messenger.show(R.string.error_session_expired)
                navigator.resetToLogin(account.baseUrl, account.email)
            }
        }
    }

    LaunchedEffect(pendingLink) {
        val link = pendingLink ?: return@LaunchedEffect
        val target = container.session.accountForPortal(link.portalUrl)
        if (target != null && target.id != container.session.activeAccount.value?.id) {
            container.session.switchAccount(target.id)
            navigator.resetToHome()
        }
        val active = container.session.activeAccount.value
        val path = link.path
        if (active != null && container.session.hasSession(active) && !path.isNullOrBlank()) {
            navigator.openDeepLink(path)
        }
        onLinkConsumed()
    }
}
