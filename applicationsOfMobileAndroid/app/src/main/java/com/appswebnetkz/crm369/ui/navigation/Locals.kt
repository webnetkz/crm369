package com.appswebnetkz.crm369.ui.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.appswebnetkz.crm369.AppContainer
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.session.Account
import com.appswebnetkz.crm369.core.session.Bootstrap
import com.appswebnetkz.crm369.core.util.AppMessenger

val LocalAppContainer = staticCompositionLocalOf<AppContainer> {
    error("AppContainer is not provided")
}

val LocalNavigator = staticCompositionLocalOf<AppNavigator> {
    error("AppNavigator is not provided")
}

val LocalMessenger = staticCompositionLocalOf<AppMessenger> {
    error("AppMessenger is not provided")
}

/** API client of the active portal account (recreated when the user switches portals). */
@Composable
fun rememberApi(): ApiClient {
    val container = LocalAppContainer.current
    val account by container.session.activeAccount.collectAsStateWithLifecycle()
    return remember(account?.id, account?.baseUrl) { container.session.api() }
}

/** The active portal account, or null when signed out. */
@Composable
fun rememberActiveAccount(): Account? {
    val container = LocalAppContainer.current
    val account by container.session.activeAccount.collectAsStateWithLifecycle()
    return account
}

/** Bootstrap (abilities, menu, counters) of the active account, or null while loading. */
@Composable
fun rememberBootstrap(): Bootstrap? {
    val container = LocalAppContainer.current
    val bootstrap by container.session.bootstrap.collectAsStateWithLifecycle()
    return bootstrap
}
