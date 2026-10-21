package com.appswebnetkz.crm369.feature.forms

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
 * the public API: [FormsRoutes], the route constants and `fun NavGraphBuilder.formsGraph()`.
 */

object FormsRoutes {
    const val LIST = "forms"
    const val DETAIL = "forms/{formId}"

    fun detail(formId: Long): String = "forms/$formId"
}

fun NavGraphBuilder.formsGraph() {
    composable(FormsRoutes.LIST) {
        WebPortalScreen(path = "/forms", title = stringResource(R.string.module_forms))
    }
    composable(
        route = FormsRoutes.DETAIL,
        arguments = listOf(
            navArgument("formId") { type = NavType.LongType },
        ),
    ) {
        WebPortalScreen(path = "/forms", title = stringResource(R.string.module_forms))
    }
}
