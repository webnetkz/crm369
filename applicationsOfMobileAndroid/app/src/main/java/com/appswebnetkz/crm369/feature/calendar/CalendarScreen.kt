package com.appswebnetkz.crm369.feature.calendar

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.ServerColors
import com.appswebnetkz.crm369.core.util.describeError
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InlineProgress
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import java.time.LocalDate
import java.time.YearMonth
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle
import java.time.format.TextStyle
import java.util.Locale

/**
 * Month calendar (Monday first) of task deadlines and conferences (`GET calendar/events` for the
 * visible weeks) with type filters and the agenda of the selected day. Events open via deep links.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CalendarScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { CalendarRepository(api) }
    val zone = remember { ZoneId.systemDefault() }
    val today = remember { LocalDate.now(zone) }
    var monthText by rememberSaveable { mutableStateOf(YearMonth.from(today).toString()) }
    var selectedText by rememberSaveable { mutableStateOf(today.toString()) }
    var showTasks by rememberSaveable { mutableStateOf(true) }
    var showConferences by rememberSaveable { mutableStateOf(true) }

    val month = parseYearMonthOrNull(monthText) ?: YearMonth.from(today)
    val selectedDay = parseLocalDateOrNull(selectedText) ?: today
    val weeks = remember(month) { calendarWeeks(month) }
    val rangeStart = weeks.first().first()
    val rangeEnd = weeks.last().last()
    val types: List<String> = buildList {
        if (showTasks) add(CalendarEvent.TYPE_TASK)
        if (showConferences) add(CalendarEvent.TYPE_CONFERENCE)
    }
    val events = rememberLoadable(api, rangeStart, rangeEnd, types) {
        if (types.isEmpty()) emptyList<CalendarEvent>() else repository.events(rangeStart, rangeEnd, types)
    }
    val eventList = events.data
    val byDay = remember(eventList, rangeStart, rangeEnd) {
        eventsByDay(eventList.orEmpty(), rangeStart, rangeEnd, zone)
    }
    val dayEvents = byDay[selectedDay].orEmpty()
    val loadError = events.error

    val selectMonth: (YearMonth) -> Unit = { target ->
        monthText = target.toString()
        selectedText = (if (target == YearMonth.from(today)) today else target.atDay(1)).toString()
    }

    CrmScaffold(
        title = stringResource(R.string.module_calendar),
        subtitle = monthTitle(month),
        actions = {
            IconButton(onClick = { selectMonth(month.minusMonths(1)) }) {
                Icon(CalendarIcons.Previous, contentDescription = stringResource(R.string.calendar_previous))
            }
            IconButton(onClick = { selectMonth(YearMonth.from(today)) }) {
                Icon(AppIcons.Today, contentDescription = stringResource(R.string.calendar_today))
            }
            IconButton(onClick = { selectMonth(month.plusMonths(1)) }) {
                Icon(CalendarIcons.Next, contentDescription = stringResource(R.string.calendar_next))
            }
        },
    ) { padding ->
        PullToRefreshBox(
            isRefreshing = events.isRefreshing,
            onRefresh = { events.refresh() },
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            LazyColumn(modifier = Modifier.fillMaxSize()) {
                item(key = "filters") {
                    CalendarFilters(
                        showTasks = showTasks,
                        showConferences = showConferences,
                        onToggleTasks = { showTasks = !showTasks },
                        onToggleConferences = { showConferences = !showConferences },
                    )
                }
                item(key = "grid") {
                    MonthGrid(
                        weeks = weeks,
                        month = month,
                        today = today,
                        selected = selectedDay,
                        byDay = byDay,
                        onSelect = { day ->
                            val dayMonth = YearMonth.from(day)
                            if (dayMonth != month) monthText = dayMonth.toString()
                            selectedText = day.toString()
                        },
                    )
                }
                item(key = "agenda-header") {
                    Column {
                        HorizontalDivider(modifier = Modifier.padding(top = 8.dp))
                        AgendaHeader(day = selectedDay, count = dayEvents.size)
                    }
                }
                when {
                    types.isEmpty() -> item(key = "agenda-message") {
                        AgendaMessage(text = stringResource(R.string.calendar_no_filters))
                    }
                    eventList == null && loadError != null -> item(key = "agenda-error") {
                        AgendaError(error = loadError, onRetry = { events.load() })
                    }
                    eventList == null -> item(key = "agenda-loading") { InlineProgress() }
                    dayEvents.isEmpty() -> item(key = "agenda-message") {
                        AgendaMessage(text = stringResource(R.string.calendar_empty_day))
                    }
                    else -> items(dayEvents, key = { it.id }) { event ->
                        EventRow(
                            event = event,
                            zone = zone,
                            onClick = { navigator.openDeepLink(event.webPath, event.title) },
                        )
                    }
                }
                item(key = "bottom-space") { Spacer(Modifier.height(24.dp)) }
            }
        }
    }
}

/** "Октябрь 2026" */
private fun monthTitle(month: YearMonth): String {
    val locale = Locale.getDefault()
    val name = month.month.getDisplayName(TextStyle.FULL_STANDALONE, locale).replaceFirstChar { it.titlecase(locale) }
    return name + " " + month.year
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CalendarFilters(
    showTasks: Boolean,
    showConferences: Boolean,
    onToggleTasks: () -> Unit,
    onToggleConferences: () -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        FilterChip(
            selected = showTasks,
            onClick = onToggleTasks,
            label = { Text(stringResource(R.string.calendar_filter_tasks)) },
            leadingIcon = { Icon(AppIcons.Tasks, contentDescription = null, modifier = Modifier.size(18.dp)) },
        )
        FilterChip(
            selected = showConferences,
            onClick = onToggleConferences,
            label = { Text(stringResource(R.string.calendar_filter_conferences)) },
            leadingIcon = { Icon(AppIcons.Conferences, contentDescription = null, modifier = Modifier.size(18.dp)) },
        )
    }
}

@Composable
private fun MonthGrid(
    weeks: List<List<LocalDate>>,
    month: YearMonth,
    today: LocalDate,
    selected: LocalDate,
    byDay: Map<LocalDate, List<CalendarEvent>>,
    onSelect: (LocalDate) -> Unit,
) {
    val locale = Locale.getDefault()
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 8.dp),
    ) {
        Row(modifier = Modifier.fillMaxWidth()) {
            weeks.first().forEach { day ->
                Text(
                    text = day.dayOfWeek.getDisplayName(TextStyle.SHORT_STANDALONE, locale).replaceFirstChar { it.titlecase(locale) },
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                    maxLines = 1,
                    modifier = Modifier
                        .weight(1f)
                        .padding(vertical = 6.dp),
                )
            }
        }
        weeks.forEach { week ->
            Row(modifier = Modifier.fillMaxWidth()) {
                week.forEach { day ->
                    DayCell(
                        day = day,
                        inMonth = YearMonth.from(day) == month,
                        isToday = day == today,
                        isSelected = day == selected,
                        events = byDay[day].orEmpty(),
                        onClick = { onSelect(day) },
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        }
    }
}

@Composable
private fun DayCell(
    day: LocalDate,
    inMonth: Boolean,
    isToday: Boolean,
    isSelected: Boolean,
    events: List<CalendarEvent>,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val colors = MaterialTheme.colorScheme
    Column(
        modifier = modifier
            .height(54.dp)
            .padding(2.dp)
            .clip(MaterialTheme.shapes.small)
            .background(if (isSelected) colors.secondaryContainer else Color.Transparent)
            .clickable(onClick = onClick)
            .padding(top = 4.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            modifier = Modifier
                .size(28.dp)
                .clip(CircleShape)
                .background(if (isToday) colors.primary else Color.Transparent),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                text = day.dayOfMonth.toString(),
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = if (isToday || isSelected) FontWeight.Bold else FontWeight.Normal,
                color = when {
                    isToday -> colors.onPrimary
                    isSelected -> colors.onSecondaryContainer
                    inMonth -> colors.onSurface
                    else -> colors.onSurfaceVariant.copy(alpha = 0.5f)
                },
            )
        }
        Spacer(Modifier.height(4.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(3.dp)) {
            events.take(4).forEach { event ->
                Box(
                    modifier = Modifier
                        .size(5.dp)
                        .clip(CircleShape)
                        .background(ServerColors.parse(event.color, colors.primary)),
                )
            }
        }
    }
}

@Composable
private fun AgendaHeader(day: LocalDate, count: Int) {
    val locale = Locale.getDefault()
    val title = day.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.FULL).withLocale(locale))
        .replaceFirstChar { it.titlecase(locale) }
    SectionHeader(
        title = title,
        action = if (count > 0) {
            {
                Text(
                    text = stringResource(R.string.calendar_events_count, count),
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        } else {
            null
        },
    )
}

@Composable
private fun AgendaMessage(text: String) {
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        textAlign = TextAlign.Center,
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 24.dp),
    )
}

@Composable
private fun AgendaError(error: Throwable, onRetry: () -> Unit) {
    val context = LocalContext.current
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Text(
            text = describeError(context, error),
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.error,
            textAlign = TextAlign.Center,
        )
        TextButton(onClick = onRetry) { Text(stringResource(R.string.action_retry)) }
    }
}

@Composable
private fun EventRow(event: CalendarEvent, zone: ZoneId, onClick: () -> Unit) {
    val color = ServerColors.parse(event.color, MaterialTheme.colorScheme.primary)
    val status = statusLabel(event.status)
    val typeLabel = stringResource(if (event.isConference) R.string.calendar_conference else R.string.calendar_task)
    val people = eventPeople(event)
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 10.dp)
            .height(IntrinsicSize.Min),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .width(4.dp)
                .fillMaxHeight()
                .clip(RoundedCornerShape(2.dp))
                .background(color),
        )
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(2.dp),
        ) {
            Text(
                text = eventTimeText(event, zone),
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(
                text = event.title,
                style = MaterialTheme.typography.bodyLarge,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
                textDecoration = if (event.isCompleted) TextDecoration.LineThrough else null,
            )
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
            ) {
                Icon(
                    if (event.isConference) AppIcons.Conferences else AppIcons.Tasks,
                    contentDescription = null,
                    modifier = Modifier.size(14.dp),
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Text(
                    text = typeLabel,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (people.isNotBlank()) {
                Text(
                    text = people,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
        if (status != null) {
            StatusChip(text = status, color = color)
        }
    }
}

/** "14:00 – 15:00", one time for instant events, dates for events spanning several days. */
@Composable
private fun eventTimeText(event: CalendarEvent, zone: ZoneId): String {
    val allDay = stringResource(R.string.calendar_all_day)
    val start = parseEventTime(event.startAt, zone)
    val end = parseEventTime(event.endAt, zone)
    val locale = Locale.getDefault()
    val time = DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT).withLocale(locale)
    val dateTime = DateTimeFormatter.ofLocalizedDateTime(FormatStyle.MEDIUM, FormatStyle.SHORT).withLocale(locale)
    return when {
        event.allDay -> allDay
        start == null -> ""
        end == null || !end.isAfter(start) -> start.format(time)
        end.toLocalDate() == start.toLocalDate() -> stringResource(R.string.calendar_time_range, start.format(time), end.format(time))
        else -> stringResource(R.string.calendar_time_range, start.format(dateTime), end.format(dateTime))
    }
}

/** Project / assignee of a task, organizer of a conference. */
@Composable
private fun eventPeople(event: CalendarEvent): String {
    val parts = ArrayList<String>()
    if (event.isConference) {
        val organizer = event.organizer
        if (organizer != null) parts.add(stringResource(R.string.calendar_organizer_value, organizer.name))
    } else {
        val project = event.projectName
        if (project != null) parts.add(stringResource(R.string.calendar_project_value, project))
        val assignee = event.assignee
        if (assignee != null) parts.add(stringResource(R.string.calendar_assignee_value, assignee.name))
    }
    return parts.joinToString(" · ")
}

@Composable
private fun statusLabel(status: String?): String? = when (status) {
    null, "" -> null
    "todo" -> stringResource(R.string.calendar_status_todo)
    "in_progress" -> stringResource(R.string.calendar_status_in_progress)
    "review" -> stringResource(R.string.calendar_status_review)
    "done" -> stringResource(R.string.calendar_status_done)
    "scheduled" -> stringResource(R.string.calendar_status_scheduled)
    "live" -> stringResource(R.string.calendar_status_live)
    "ended" -> stringResource(R.string.calendar_status_ended)
    else -> status?.replace('_', ' ')?.replaceFirstChar { it.uppercase() }
}
