package com.appswebnetkz.crm369.feature.tsd

import androidx.navigation.NavGraphBuilder
import androidx.navigation.compose.composable

/** Routes of the TSD module: handheld-terminal scanning log and the quick QR lookup. */
object TsdRoutes {
    const val TSD = "tsd"
    const val QR = "qr"
}

fun NavGraphBuilder.tsdGraph() {
    composable(TsdRoutes.TSD) { TsdScreen() }
    composable(TsdRoutes.QR) { QrLookupScreen() }
}
