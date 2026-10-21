package com.appswebnetkz.crm369.feature.warehouses

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/** Routes of the warehouses module (address storage, hierarchy, items and QR lookup). */
object WarehousesRoutes {
    const val LIST = "warehouses"
    const val DETAIL = "warehouses/{warehouseId}"
    const val NEW = "warehouses/new"
    const val SCAN = "warehouses/scan"
    const val FLOOR = "warehouses/{warehouseId}/floors/{floorId}"

    fun detail(warehouseId: Long): String = "warehouses/$warehouseId"
    fun floor(warehouseId: Long, floorId: Long): String = "warehouses/$warehouseId/floors/$floorId"
}

fun NavGraphBuilder.warehousesGraph() {
    composable(WarehousesRoutes.LIST) { WarehousesListScreen() }
    composable(WarehousesRoutes.NEW) { WarehouseCreateScreen() }
    composable(WarehousesRoutes.SCAN) { WarehouseScanScreen() }
    composable(
        route = WarehousesRoutes.DETAIL,
        arguments = listOf(navArgument("warehouseId") { type = NavType.LongType }),
    ) { entry ->
        WarehouseDetailScreen(warehouseId = entry.longArg("warehouseId"))
    }
    composable(
        route = WarehousesRoutes.FLOOR,
        arguments = listOf(
            navArgument("warehouseId") { type = NavType.LongType },
            navArgument("floorId") { type = NavType.LongType },
        ),
    ) { entry ->
        WarehouseFloorScreen(
            warehouseId = entry.longArg("warehouseId"),
            floorId = entry.longArg("floorId"),
        )
    }
}
