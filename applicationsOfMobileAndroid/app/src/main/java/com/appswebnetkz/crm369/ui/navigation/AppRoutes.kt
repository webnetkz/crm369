package com.appswebnetkz.crm369.ui.navigation

import androidx.navigation.NavBackStackEntry
import com.appswebnetkz.crm369.core.links.RouteArgs

/** Routes of the application core. Feature routes live in `feature/<name>/<Name>Routes`. */
object AppRoutes {
    const val PORTAL = "portal"
    const val LOGIN = "login?baseUrl={baseUrl}&email={email}"
    const val ACCOUNTS = "accounts"
    const val MAIN = "main?tab={tab}"
    const val NOTIFICATIONS = "notifications"
    const val PROFILE = "profile"
    const val PROFILE_EDIT = "profile/edit"
    const val PROFILE_PASSWORD = "profile/password"
    const val SETTINGS = "settings"
    const val WEB = "web?path={path}&title={title}"

    /** Main tab keys. */
    const val TAB_HOME = "home"
    const val TAB_CHATS = "chats"
    const val TAB_TASKS = "tasks"
    const val TAB_CRM = "crm"
    const val TAB_MENU = "menu"

    fun login(baseUrl: String, email: String? = null): String =
        "login?baseUrl=${RouteArgs.encode(baseUrl)}" + (email?.let { "&email=${RouteArgs.encode(it)}" } ?: "")

    fun main(tab: String? = null): String = if (tab == null) "main" else "main?tab=${RouteArgs.encode(tab)}"

    fun web(path: String, title: String? = null): String =
        "web?path=${RouteArgs.encode(path)}" + (title?.let { "&title=${RouteArgs.encode(it)}" } ?: "")
}

/** Reads a Long navigation argument (declared with `NavType.LongType`). */
fun NavBackStackEntry.longArg(name: String, default: Long = 0L): Long =
    arguments?.getLong(name, default) ?: default

/** Reads a String navigation argument (declared with `NavType.StringType`). */
fun NavBackStackEntry.stringArg(name: String): String? =
    arguments?.getString(name)?.takeIf { it.isNotEmpty() }

/** Reads an optional Long passed as a String query argument ("" or missing → null). */
fun NavBackStackEntry.optionalLongArg(name: String): Long? =
    arguments?.getString(name)?.toLongOrNull()
