package com.appswebnetkz.crm369.feature.tsd

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavGraphBuilder
import androidx.navigation.compose.composable
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.web.WebPortalScreen

/*
 * STUB: temporary web fallback. The native implementation replaces this file and keeps
 * the public API: [TsdRoutes], the route constants and `fun NavGraphBuilder.tsdGraph()`.
 */

object TsdRoutes {
    const val TSD = "tsd"
    const val QR = "qr"
}

fun NavGraphBuilder.tsdGraph() {
    composable(TsdRoutes.TSD) {
        WebPortalScreen(path = "/tsd", title = stringResource(R.string.module_tsd))
    }
    composable(TsdRoutes.QR) {
        WebPortalScreen(path = "/qr", title = stringResource(R.string.module_tsd))
    }
}
