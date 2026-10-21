package com.appswebnetkz.crm369.ui.main

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.StringRes
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Badge
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.saveable.rememberSaveableStateHolder
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.session.Bootstrap
import com.appswebnetkz.crm369.feature.chats.ChatsListScreen
import com.appswebnetkz.crm369.feature.contacts.ContactsHomeScreen
import com.appswebnetkz.crm369.feature.funnels.FunnelsHomeScreen
import com.appswebnetkz.crm369.feature.tasks.TasksHomeScreen
import com.appswebnetkz.crm369.ui.components.LocalInTabHost
import com.appswebnetkz.crm369.ui.navigation.AppRoutes
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.delay

private data class MainTab(
    val key: String,
    @StringRes val label: Int,
    val icon: ImageVector,
    val badge: Int = 0,
)

private fun buildTabs(bootstrap: Bootstrap?): List<MainTab> {
    val tabs = mutableListOf(MainTab(AppRoutes.TAB_HOME, R.string.tab_home, AppIcons.Home))
    if (bootstrap != null) {
        if (bootstrap.can("canAccessChats")) {
            tabs += MainTab(AppRoutes.TAB_CHATS, R.string.tab_chats, AppIcons.Chats, bootstrap.unreadChats)
        }
        if (bootstrap.can("canAccessProjects")) {
            tabs += MainTab(AppRoutes.TAB_TASKS, R.string.tab_tasks, AppIcons.Tasks)
        }
        if (bootstrap.can("canAccessFunnels") || bootstrap.can("canAccessContacts")) {
            tabs += MainTab(AppRoutes.TAB_CRM, R.string.tab_crm, AppIcons.Funnels)
        }
    }
    tabs += MainTab(AppRoutes.TAB_MENU, R.string.tab_menu, AppIcons.Menu)
    return tabs
}

/**
 * Main screen with the bottom navigation (like Bitrix24): Home, Chats, Tasks, CRM, Menu.
 * Tabs depend on the user's abilities from `GET bootstrap`.
 */
@OptIn(ExperimentalMaterial3Api::class) // BadgedBox/Badge were experimental in earlier material3 releases
@Composable
fun MainScreen(initialTab: String?) {
    val container = LocalAppContainer.current
    val bootstrap = rememberBootstrap()
    val tabs = buildTabs(bootstrap)
    var selected by rememberSaveable { mutableStateOf(initialTab ?: AppRoutes.TAB_HOME) }
    val current = if (tabs.any { it.key == selected }) selected else AppRoutes.TAB_HOME
    val stateHolder = rememberSaveableStateHolder()

    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(lifecycleOwner) {
        lifecycleOwner.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            while (true) {
                container.session.refreshBootstrapQuietly()
                delay(BOOTSTRAP_REFRESH_MILLIS)
            }
        }
    }

    NotificationPermissionRequest()

    Scaffold(
        contentWindowInsets = WindowInsets(0.dp, 0.dp, 0.dp, 0.dp),
        bottomBar = {
            NavigationBar {
                tabs.forEach { tab ->
                    NavigationBarItem(
                        selected = current == tab.key,
                        onClick = { selected = tab.key },
                        icon = {
                            BadgedBox(
                                badge = {
                                    if (tab.badge > 0) {
                                        Badge { Text(if (tab.badge > 99) "99+" else tab.badge.toString()) }
                                    }
                                },
                            ) {
                                Icon(tab.icon, contentDescription = null)
                            }
                        },
                        label = { Text(stringResource(tab.label)) },
                    )
                }
            }
        },
    ) { padding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            CompositionLocalProvider(LocalInTabHost provides true) {
                stateHolder.SaveableStateProvider(current) {
                    when (current) {
                        AppRoutes.TAB_CHATS -> ChatsListScreen()
                        AppRoutes.TAB_TASKS -> TasksHomeScreen()
                        AppRoutes.TAB_CRM -> if (bootstrap?.can("canAccessFunnels") == true) {
                            FunnelsHomeScreen()
                        } else {
                            ContactsHomeScreen()
                        }
                        AppRoutes.TAB_MENU -> MenuScreen()
                        else -> HomeScreen(onOpenTab = { tab -> selected = tab })
                    }
                }
            }
        }
    }
}

/** Asks for the Android 13+ notification permission once after sign in. */
@Composable
private fun NotificationPermissionRequest() {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return
    val context = LocalContext.current
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { _ ->
        markNotificationPermissionAsked(context)
    }
    LaunchedEffect(Unit) {
        val granted = ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) ==
            PackageManager.PERMISSION_GRANTED
        if (!granted && !wasNotificationPermissionAsked(context)) {
            markNotificationPermissionAsked(context)
            launcher.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }
}

private fun wasNotificationPermissionAsked(context: Context): Boolean =
    context.getSharedPreferences(UI_PREFERENCES, Context.MODE_PRIVATE).getBoolean(KEY_NOTIFICATIONS_ASKED, false)

private fun markNotificationPermissionAsked(context: Context) {
    context.getSharedPreferences(UI_PREFERENCES, Context.MODE_PRIVATE).edit().putBoolean(KEY_NOTIFICATIONS_ASKED, true).apply()
}

private const val UI_PREFERENCES = "crm369_ui"
private const val KEY_NOTIFICATIONS_ASKED = "notifications_permission_asked"
private const val BOOTSTRAP_REFRESH_MILLIS = 60_000L
