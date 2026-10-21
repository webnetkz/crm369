package com.appswebnetkz.crm369.feature.company

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
 * the public API: [CompanyRoutes], the route constants and `fun NavGraphBuilder.companyGraph()`.
 */

object CompanyRoutes {
    const val ROOT = "company"
    const val USER = "company/users/{userId}"

    fun user(userId: Long): String = "company/users/$userId"
}

fun NavGraphBuilder.companyGraph() {
    composable(CompanyRoutes.ROOT) {
        WebPortalScreen(path = "/company-structure", title = stringResource(R.string.module_company_structure))
    }
    composable(
        route = CompanyRoutes.USER,
        arguments = listOf(
            navArgument("userId") { type = NavType.LongType },
        ),
    ) {
        WebPortalScreen(path = "/company-structure", title = stringResource(R.string.module_company_structure))
    }
}
