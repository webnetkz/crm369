package com.appswebnetkz.crm369.feature.calendar

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavGraphBuilder
import androidx.navigation.compose.composable
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.web.WebPortalScreen

/*
 * STUB: temporary web fallback. The native implementation replaces this file and keeps
 * the public API: [CalendarRoutes], the route constants and `fun NavGraphBuilder.calendarGraph()`.
 */

object CalendarRoutes {
    const val ROOT = "calendar"
}

fun NavGraphBuilder.calendarGraph() {
    composable(CalendarRoutes.ROOT) {
        WebPortalScreen(path = "/calendar", title = stringResource(R.string.module_calendar))
    }
}
