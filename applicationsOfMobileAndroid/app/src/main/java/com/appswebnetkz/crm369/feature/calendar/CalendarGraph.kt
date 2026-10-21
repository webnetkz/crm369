package com.appswebnetkz.crm369.feature.calendar

import androidx.navigation.NavGraphBuilder
import androidx.navigation.compose.composable

/** Routes of the calendar module. */
object CalendarRoutes {
    const val ROOT = "calendar"
}

fun NavGraphBuilder.calendarGraph() {
    composable(CalendarRoutes.ROOT) { CalendarScreen() }
}
