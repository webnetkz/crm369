package com.appswebnetkz.crm369.feature.equipment

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
 * the public API: [EquipmentRoutes], the route constants and `fun NavGraphBuilder.equipmentGraph()`.
 */

object EquipmentRoutes {
    const val LIST = "equipment"
    const val NEW = "equipment/new"
    const val DETAIL = "equipment/{itemId}"
    const val EDIT = "equipment/{itemId}/edit"

    fun detail(itemId: Long): String = "equipment/$itemId"
    fun edit(itemId: Long): String = "equipment/$itemId/edit"
}

fun NavGraphBuilder.equipmentGraph() {
    composable(EquipmentRoutes.LIST) {
        WebPortalScreen(path = "/equipment", title = stringResource(R.string.module_equipment))
    }
    composable(EquipmentRoutes.NEW) {
        WebPortalScreen(path = "/equipment", title = stringResource(R.string.module_equipment))
    }
    composable(
        route = EquipmentRoutes.DETAIL,
        arguments = listOf(
            navArgument("itemId") { type = NavType.LongType },
        ),
    ) {
        WebPortalScreen(path = "/equipment", title = stringResource(R.string.module_equipment))
    }
    composable(
        route = EquipmentRoutes.EDIT,
        arguments = listOf(
            navArgument("itemId") { type = NavType.LongType },
        ),
    ) {
        WebPortalScreen(path = "/equipment", title = stringResource(R.string.module_equipment))
    }
}
