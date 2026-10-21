package com.appswebnetkz.crm369.ui.navigation

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.util.Log
import androidx.navigation.NavHostController
import com.appswebnetkz.crm369.core.links.DeepLinkRouter
import com.appswebnetkz.crm369.core.links.DeepLinkTarget

/**
 * Navigation entry point for all screens (`LocalNavigator.current`).
 * Routes are plain strings built by `AppRoutes` / `<Feature>Routes`.
 */
class AppNavigator(
    private val navController: NavHostController,
    private val context: Context,
    private val portalBaseUrl: () -> String?,
) {
    /** Opens [route] on top of the current screen. Returns false when no screen matches the route. */
    fun open(route: String): Boolean = try {
        navController.navigate(route) {
            launchSingleTop = true
        }
        true
    } catch (exception: IllegalArgumentException) {
        Log.w(TAG, "No destination for route $route", exception)
        false
    }

    /** Replaces the current screen with [route] (e.g. after creating an entity open its details). */
    fun replace(route: String) {
        val current = navController.currentDestination?.id
        navController.navigate(route) {
            launchSingleTop = true
            if (current != null) {
                popUpTo(current) { inclusive = true }
            }
        }
    }

    /** True when there is a previous screen (the current one is not the root of the back stack). */
    fun canGoBack(): Boolean = navController.previousBackStackEntry != null

    /**
     * Goes back; returns false (and stays) when the current screen is the root of the back stack.
     * Popping the root would leave an empty, blank NavHost.
     */
    fun back(): Boolean {
        if (!canGoBack()) return false
        return navController.popBackStack()
    }

    /** Opens a portal page in the in-app browser with the web session bridge. */
    fun openWeb(path: String, title: String? = null) {
        this.open(AppRoutes.web(path, title))
    }

    /** Opens an external URL in the system browser (or a mailto:/tel: link). */
    fun openUrl(url: String) {
        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        try {
            context.startActivity(intent)
        } catch (_: ActivityNotFoundException) {
            // No application can open the link.
        }
    }

    fun dial(phone: String) {
        val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + phone.filter { it.isDigit() || it == '+' }))
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        try {
            context.startActivity(intent)
        } catch (_: ActivityNotFoundException) {
            // No dialer on the device.
        }
    }

    fun email(address: String) {
        val intent = Intent(Intent.ACTION_SENDTO, Uri.parse("mailto:" + address.trim()))
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        try {
            context.startActivity(intent)
        } catch (_: ActivityNotFoundException) {
            // No mail client on the device.
        }
    }

    fun whatsApp(phone: String) {
        openUrl("https://wa.me/" + phone.filter { it.isDigit() })
    }

    fun map(address: String) {
        openUrl("geo:0,0?q=" + Uri.encode(address))
    }

    /**
     * Opens a CRM link (notification `action_url`, push `action_path`, chat link) in the
     * matching native screen, the in-app browser, or the system browser.
     */
    fun openDeepLink(pathOrUrl: String, title: String? = null) {
        when (val target = DeepLinkRouter.resolve(pathOrUrl, portalBaseUrl())) {
            is DeepLinkTarget.App -> {
                if (target.route == "main") {
                    resetToHome()
                } else if (!open(target.route)) {
                    openWeb(if (pathOrUrl.startsWith("/")) pathOrUrl else "/" + pathOrUrl.substringAfter("://").substringAfter('/'), title)
                }
            }
            is DeepLinkTarget.Web -> openWeb(target.path, title)
            is DeepLinkTarget.External -> openUrl(target.url)
        }
    }

    /** Clears the back stack and shows the main screen ([tab] = one of `AppRoutes.TAB_*`). */
    fun resetToHome(tab: String? = null) {
        navController.navigate(AppRoutes.main(tab)) {
            popUpTo(navController.graph.id) { inclusive = true }
            launchSingleTop = true
        }
    }

    /** Clears the back stack and shows the portal address screen (no accounts left). */
    fun resetToPortal() {
        navController.navigate(AppRoutes.PORTAL) {
            popUpTo(navController.graph.id) { inclusive = true }
        }
    }

    /** Clears the back stack and shows the sign in screen of a portal. */
    fun resetToLogin(baseUrl: String, email: String? = null) {
        navController.navigate(AppRoutes.login(baseUrl, email)) {
            popUpTo(navController.graph.id) { inclusive = true }
        }
    }

    private companion object {
        const val TAG = "AppNavigator"
    }
}
