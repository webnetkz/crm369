package com.appswebnetkz.crm369.feature.procurement

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavGraphBuilder
import androidx.navigation.compose.composable
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.web.WebPortalScreen

/*
 * STUB: temporary web fallback. The native implementation replaces this file and keeps
 * the public API: [ProcurementRoutes], the route constants and `fun NavGraphBuilder.procurementGraph()`.
 */

object ProcurementRoutes {
    const val ROOT = "procurement"
}

fun NavGraphBuilder.procurementGraph() {
    composable(ProcurementRoutes.ROOT) {
        WebPortalScreen(path = "/procurement", title = stringResource(R.string.module_procurement))
    }
}
