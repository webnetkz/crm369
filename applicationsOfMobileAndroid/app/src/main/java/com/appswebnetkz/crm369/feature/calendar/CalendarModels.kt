package com.appswebnetkz.crm369.feature.calendar

import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import java.time.DayOfWeek
import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.OffsetDateTime
import java.time.YearMonth
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeParseException
import java.time.temporal.TemporalAdjusters
import kotlinx.serialization.json.JsonObject

/*
 * Calendar models and date helpers. Pure Kotlin (java.time + kotlinx JSON), unit-testable on the JVM.
 */

/** Person of an event (`meta.assignee`, `meta.organizer`). */
data class CalendarPerson(
    val id: Long?,
    val name: String,
)

/** `CalendarEvent`: a task deadline or a conference. */
data class CalendarEvent(
    val id: String,
    val sourceId: Long,
    val type: String,
    val title: String,
    val description: String?,
    val startAt: String?,
    val endAt: String?,
    val allDay: Boolean,
    val status: String?,
    val color: String?,
    val url: String?,
    val projectName: String?,
    val assignee: CalendarPerson?,
    val organizer: CalendarPerson?,
    val importance: String?,
    val isCompleted: Boolean,
    val isOrganizer: Boolean,
) {
    val isConference: Boolean get() = type == TYPE_CONFERENCE

    /** Web path of the event ("/tasks/12"); the host of [url] is ignored, events always belong to the portal. */
    val webPath: String
        get() {
            val link = url
            return when {
                link != null -> webPathOf(link)
                isConference -> "/conferences/$sourceId"
                else -> "/tasks/$sourceId"
            }
        }

    companion object {
        const val TYPE_TASK = "task"
        const val TYPE_CONFERENCE = "conference"

        fun fromJson(json: JsonObject): CalendarEvent {
            val meta = json.obj("meta")
            val type = json.text("type") ?: TYPE_TASK
            val sourceId = json.long("source_id") ?: 0L
            return CalendarEvent(
                id = json.text("id") ?: "$type:$sourceId",
                sourceId = sourceId,
                type = type,
                title = json.str("title").orEmpty(),
                description = json.text("description"),
                startAt = json.text("start_at"),
                endAt = json.text("end_at"),
                allDay = json.boolOr("all_day", false),
                status = json.text("status"),
                color = json.text("color"),
                url = json.text("url"),
                projectName = meta.obj("project").text("name"),
                assignee = person(meta.obj("assignee")),
                organizer = person(meta.obj("organizer")),
                importance = meta.text("importance"),
                isCompleted = meta.boolOr("is_completed", false),
                isOrganizer = meta.boolOr("is_organizer", false),
            )
        }

        private fun person(json: JsonObject?): CalendarPerson? {
            val name = json.text("name") ?: return null
            return CalendarPerson(id = json.long("id"), name = name)
        }
    }
}

/** "https://crm.kz/tasks/1?x=1" → "/tasks/1?x=1"; a path stays a path. */
fun webPathOf(url: String): String {
    val trimmed = url.trim()
    val schemeIndex = trimmed.indexOf("://")
    if (schemeIndex < 0) return if (trimmed.startsWith("/")) trimmed else "/$trimmed"
    val rest = trimmed.substring(schemeIndex + 3)
    val slash = rest.indexOf('/')
    return if (slash < 0) "/" else rest.substring(slash)
}

private inline fun <T> parseOrNull(block: () -> T): T? = try {
    block()
} catch (exception: DateTimeParseException) {
    null
}

/** Parses an ISO date-time ("2026-07-21T11:30:00.000000Z") or a date into [zone]. */
fun parseEventTime(value: String?, zone: ZoneId): ZonedDateTime? {
    if (value.isNullOrBlank()) return null
    val text = value.trim()
    parseOrNull { OffsetDateTime.parse(text).atZoneSameInstant(zone) }?.let { return it }
    parseOrNull { Instant.parse(text).atZone(zone) }?.let { return it }
    parseOrNull { LocalDateTime.parse(text.replace(' ', 'T')).atZone(zone) }?.let { return it }
    return parseOrNull { LocalDate.parse(text.take(10)).atStartOfDay(zone) }
}

fun parseYearMonthOrNull(text: String?): YearMonth? =
    if (text.isNullOrBlank()) null else parseOrNull { YearMonth.parse(text.trim()) }

fun parseLocalDateOrNull(text: String?): LocalDate? =
    if (text.isNullOrBlank()) null else parseOrNull { LocalDate.parse(text.trim()) }

/** Weeks (Monday first) of the month grid that shows [month] in full weeks. */
fun calendarWeeks(month: YearMonth): List<List<LocalDate>> {
    val start = month.atDay(1).with(TemporalAdjusters.previousOrSame(DayOfWeek.MONDAY))
    val end = month.atEndOfMonth().with(TemporalAdjusters.nextOrSame(DayOfWeek.SUNDAY))
    val weeks = ArrayList<List<LocalDate>>()
    var weekStart = start
    while (!weekStart.isAfter(end)) {
        val first = weekStart
        weeks.add((0L until 7L).map { offset -> first.plusDays(offset) })
        weekStart = weekStart.plusWeeks(1)
    }
    return weeks
}

/**
 * Events of every local day of [from]..[to]. An event spanning several days is listed on each of
 * them; an end exactly at midnight does not occupy the next day. Lists are sorted by start time.
 */
fun eventsByDay(
    events: List<CalendarEvent>,
    from: LocalDate,
    to: LocalDate,
    zone: ZoneId,
): Map<LocalDate, List<CalendarEvent>> {
    val result = LinkedHashMap<LocalDate, MutableList<CalendarEvent>>()
    events.forEach { event ->
        val start = parseEventTime(event.startAt, zone) ?: parseEventTime(event.endAt, zone) ?: return@forEach
        val end = parseEventTime(event.endAt, zone)
        val startDay = start.toLocalDate()
        var endDay = end?.toLocalDate() ?: startDay
        if (end != null && endDay.isAfter(startDay) && end.toLocalTime() == LocalTime.MIDNIGHT) {
            endDay = endDay.minusDays(1)
        }
        if (endDay.isBefore(startDay)) endDay = startDay
        var day = if (startDay.isBefore(from)) from else startDay
        val last = if (endDay.isAfter(to)) to else endDay
        while (!day.isAfter(last)) {
            result.getOrPut(day) { ArrayList() }.add(event)
            day = day.plusDays(1)
        }
    }
    return result.mapValues { (_, dayEvents) ->
        dayEvents
            .distinctBy { it.id }
            .sortedBy { parseEventTime(it.startAt, zone)?.toInstant() }
    }
}
