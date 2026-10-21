package com.appswebnetkz.crm369.feature.warehouses

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.navigation.longArg
import com.appswebnetkz.crm369.ui.web.WebPortalScreen

/*
 * STUB: temporary web fallback. The native implementation replaces this file and keeps
 * the public API: [WarehousesRoutes], the route constants and `fun NavGraphBuilder.warehousesGraph()`.
 */

object WarehousesRoutes {
    const val LIST = "warehouses"
    const val DETAIL = "warehouses/{warehouseId}"

    fun detail(warehouseId: Long): String = "warehouses/$warehouseId"
}

fun NavGraphBuilder.warehousesGraph() {
    composable(WarehousesRoutes.LIST) {
        WebPortalScreen(path = "/warehouses", title = stringResource(R.string.module_warehouses))
    }
    composable(
        route = WarehousesRoutes.DETAIL,
        arguments = listOf(
            navArgument("warehouseId") { type = NavType.LongType },
        ),
    ) { entry ->
        WebPortalScreen(path = "/warehouses/" + entry.longArg("warehouseId"), title = stringResource(R.string.module_warehouses))
    }
}
