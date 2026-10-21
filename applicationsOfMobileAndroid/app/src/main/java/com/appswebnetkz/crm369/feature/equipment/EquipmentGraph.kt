package com.appswebnetkz.crm369.feature.equipment

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/** Routes of the equipment module. */
object EquipmentRoutes {
    const val LIST = "equipment"
    const val NEW = "equipment/new"
    const val DETAIL = "equipment/{itemId}"
    const val EDIT = "equipment/{itemId}/edit"

    fun detail(itemId: Long): String = "equipment/$itemId"
    fun edit(itemId: Long): String = "equipment/$itemId/edit"
}

fun NavGraphBuilder.equipmentGraph() {
    composable(EquipmentRoutes.LIST) { EquipmentListScreen() }
    composable(EquipmentRoutes.NEW) { EquipmentFormScreen(itemId = null) }
    composable(
        route = EquipmentRoutes.DETAIL,
        arguments = listOf(navArgument("itemId") { type = NavType.LongType }),
    ) { entry ->
        EquipmentDetailScreen(itemId = entry.longArg("itemId"))
    }
    composable(
        route = EquipmentRoutes.EDIT,
        arguments = listOf(navArgument("itemId") { type = NavType.LongType }),
    ) { entry ->
        EquipmentFormScreen(itemId = entry.longArg("itemId"))
    }
}
