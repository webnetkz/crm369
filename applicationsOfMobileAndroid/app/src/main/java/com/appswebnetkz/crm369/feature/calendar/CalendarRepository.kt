package com.appswebnetkz.crm369.feature.calendar

import com.appswebnetkz.crm369.core.json.dataObjects
import com.appswebnetkz.crm369.core.network.ApiClient
import java.time.LocalDate

/** Calendar API (`GET calendar/events`). */
class CalendarRepository(private val api: ApiClient) {

    /** Events of [from]..[to] (≤ 370 days) of the given [types] (`task`, `conference`). */
    suspend fun events(from: LocalDate, to: LocalDate, types: List<String>): List<CalendarEvent> =
        api.get(
            "calendar/events",
            mapOf(
                "from" to from.toString(),
                "to" to to.toString(),
                "types" to types,
            ),
        ).dataObjects().map { CalendarEvent.fromJson(it) }
}
