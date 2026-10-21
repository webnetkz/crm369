package com.appswebnetkz.crm369.feature.company

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/** Routes of the company structure module. */
object CompanyRoutes {
    const val ROOT = "company"
    const val USER = "company/users/{userId}"

    fun user(userId: Long): String = "company/users/$userId"
}

fun NavGraphBuilder.companyGraph() {
    composable(CompanyRoutes.ROOT) { CompanyStructureScreen() }
    composable(
        route = CompanyRoutes.USER,
        arguments = listOf(navArgument("userId") { type = NavType.LongType }),
    ) { entry ->
        CompanyUserScreen(userId = entry.longArg("userId"))
    }
}
