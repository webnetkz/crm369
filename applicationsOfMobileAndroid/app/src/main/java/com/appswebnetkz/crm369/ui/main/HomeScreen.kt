package com.appswebnetkz.crm369.ui.main

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.Badge
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.json.arr
import com.appswebnetkz.crm369.core.json.asDouble
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.double
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.strOr
import com.appswebnetkz.crm369.core.json.strings
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.util.ServerColors
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InlineProgress
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.AppNavigator
import com.appswebnetkz.crm369.ui.navigation.AppRoutes
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.ModuleEntry
import com.appswebnetkz.crm369.ui.navigation.ModuleRegistry
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.serialization.json.JsonObject

/** Home tab: greeting, module shortcuts and the portal dashboard (`GET dashboard`). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(onOpenTab: (String) -> Unit) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val bootstrap = rememberBootstrap()
    val dashboard = rememberLoadable(api.baseUrl) { api.get("dashboard").dataObject() }

    CrmScaffold(
        title = bootstrap?.portalName ?: stringResource(R.string.app_name),
        subtitle = bootstrap?.user?.fullName,
        actions = {
            IconButton(onClick = { navigator.open(AppRoutes.NOTIFICATIONS) }) {
                BadgedBox(
                    badge = {
                        val unread = bootstrap?.unreadNotifications ?: 0
                        if (unread > 0) Badge { Text(if (unread > 99) "99+" else unread.toString()) }
                    },
                ) {
                    Icon(AppIcons.Notifications, contentDescription = stringResource(R.string.notifications_title))
                }
            }
            IconButton(onClick = { navigator.open(AppRoutes.PROFILE) }) {
                Avatar(name = bootstrap?.user?.fullName, imageUrl = bootstrap?.user?.avatarUrl, size = 32.dp)
            }
        },
    ) { padding ->
        PullToRefreshBox(
            isRefreshing = dashboard.isRefreshing,
            onRefresh = { dashboard.refresh() },
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            LazyColumn(modifier = Modifier.fillMaxSize()) {
                item {
                    ShortcutsSection(
                        modules = ModuleRegistry.visibleModules(bootstrap).filter { it.route != null && it.key != "notifications" }.take(8),
                        onOpen = { entry -> openModuleEntry(navigator, entry, onOpenTab) },
                    )
                }
                val data = dashboard.data
                val error = dashboard.error
                when {
                    data != null -> dashboardItems(data)
                    error != null -> item {
                        Text(
                            text = stringResource(R.string.home_dashboard_unavailable),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(16.dp),
                        )
                    }
                    else -> item { InlineProgress() }
                }
                item { Spacer(Modifier.height(24.dp)) }
            }
        }
    }
}

/** Opens a module: chats switch to the Chats tab, modules without a native screen open in the web view. */
fun openModuleEntry(navigator: AppNavigator, entry: ModuleEntry, onOpenTab: (String) -> Unit) {
    if (entry.key == "chats") {
        onOpenTab(AppRoutes.TAB_CHATS)
        return
    }
    if (entry.key == "dashboard") {
        navigator.resetToHome()
        return
    }
    val route = entry.route
    if (route == null || !navigator.open(route)) {
        navigator.openWeb(entry.webPath)
    }
}

@Composable
private fun ShortcutsSection(modules: List<ModuleEntry>, onOpen: (ModuleEntry) -> Unit) {
    if (modules.isEmpty()) return
    SectionHeader(title = stringResource(R.string.home_shortcuts))
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        modules.forEach { entry ->
            ModuleTile(icon = entry.icon, title = stringResource(entry.titleRes), onClick = { onOpen(entry) })
        }
    }
}

@Composable
fun ModuleTile(icon: ImageVector, title: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier
            .width(84.dp)
            .clickable(onClick = onClick)
            .padding(vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Surface(
            shape = RoundedCornerShape(16.dp),
            color = MaterialTheme.colorScheme.primaryContainer,
            contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
            modifier = Modifier.size(52.dp),
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(icon, contentDescription = null)
            }
        }
        Spacer(Modifier.height(6.dp))
        Text(
            text = title,
            style = MaterialTheme.typography.labelMedium,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
        )
    }
}

/** Visible widgets of the active dashboard (`dashboardConfiguration`). */
private fun visibleWidgets(configuration: JsonObject?): List<String> {
    val defaults = listOf("highlights", "metrics", "activity", "donuts", "bars", "radar")
    if (configuration == null) return defaults
    val activeId = configuration.str("activeDashboardId")
    val dashboards = configuration.objects("dashboards")
    val active = dashboards.firstOrNull { it.str("id") == activeId } ?: dashboards.firstOrNull() ?: return defaults
    val widgets = active.objects("widgets")
    if (widgets.isEmpty()) return defaults
    return widgets.filter { it.boolOr("visible", true) }.mapNotNull { it.text("id") }
}

private fun androidx.compose.foundation.lazy.LazyListScope.dashboardItems(data: JsonObject) {
    val stats = data.obj("dashboardStats") ?: return
    val widgets = visibleWidgets(data.obj("dashboardConfiguration"))
    widgets.forEach { widget ->
        when (widget) {
            "highlights" -> {
                val highlights = stats.objects("highlights")
                if (highlights.isNotEmpty()) item(key = "highlights") { HighlightsRow(highlights) }
            }
            "metrics" -> {
                val cards = stats.objects("cards")
                if (cards.isNotEmpty()) {
                    item(key = "metrics-title") {
                        SectionHeader(title = stats.text("eyebrow") ?: stringResource(R.string.home_metrics))
                    }
                    cards.chunked(2).forEachIndexed { index, row ->
                        item(key = "metrics-$index") {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp),
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                            ) {
                                row.forEach { card ->
                                    MetricCard(card = card, modifier = Modifier.weight(1f))
                                }
                                if (row.size == 1) Spacer(Modifier.weight(1f))
                            }
                        }
                    }
                }
            }
            "activity" -> {
                val activity = stats.obj("activity")
                if (activity != null && activity.objects("series").isNotEmpty()) {
                    item(key = "activity") { ActivityChartCard(activity) }
                }
            }
            "donuts" -> {
                stats.objects("donuts").forEachIndexed { index, donut ->
                    item(key = "donut-$index") { DonutCard(donut) }
                }
            }
            "bars" -> {
                val bars = stats.obj("bars")
                if (bars != null && bars.objects("items").isNotEmpty()) item(key = "bars") { BarsCard(bars) }
            }
            "radar" -> {
                val radar = stats.obj("radar")
                if (radar != null && radar.objects("items").isNotEmpty()) item(key = "radar") { ProgressListCard(radar) }
            }
        }
    }
}

@Composable
private fun ChartCard(title: String?, subtitle: String?, content: @Composable () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 6.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            if (!title.isNullOrBlank()) {
                Text(title, style = MaterialTheme.typography.titleSmall)
            }
            if (!subtitle.isNullOrBlank()) {
                Text(
                    subtitle,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Spacer(Modifier.height(12.dp))
            content()
        }
    }
}

@Composable
private fun HighlightsRow(highlights: List<JsonObject>) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 12.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        highlights.forEach { highlight ->
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
                modifier = Modifier.width(160.dp),
            ) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text(
                        highlight.strOr("value", "—"),
                        style = MaterialTheme.typography.titleLarge,
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                    )
                    Text(
                        highlight.strOr("label", ""),
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                    )
                    val helper = highlight.text("helper")
                    if (helper != null) {
                        Text(
                            helper,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.75f),
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun MetricCard(card: JsonObject, modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.padding(vertical = 4.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Text(
                card.strOr("title", ""),
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(4.dp))
            Text(card.strOr("value", "—"), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
            val helper = card.text("helper")
            if (helper != null) {
                Text(
                    helper,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

@Composable
private fun ActivityChartCard(activity: JsonObject) {
    val labels = activity.strings("labels")
    val series = activity.objects("series").map { serie ->
        Triple(
            serie.strOr("label", ""),
            serie.arr("values")?.map { it.asDouble() ?: 0.0 } ?: emptyList(),
            ServerColors.parse(serie.str("color")),
        )
    }
    val maxValue = series.flatMap { it.second }.maxOrNull()?.takeIf { it > 0 } ?: 1.0
    val gridColor = MaterialTheme.colorScheme.outlineVariant
    ChartCard(title = activity.text("title"), subtitle = activity.text("subtitle")) {
        Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .height(160.dp),
        ) {
            val steps = 4
            for (step in 0..steps) {
                val y = size.height * step / steps
                drawLine(gridColor, Offset(0f, y), Offset(size.width, y), strokeWidth = 1f)
            }
            series.forEach { (_, values, color) ->
                if (values.size < 2) return@forEach
                val path = Path()
                values.forEachIndexed { index, value ->
                    val x = size.width * index / (values.size - 1)
                    val y = size.height - (value / maxValue * size.height).toFloat()
                    if (index == 0) path.moveTo(x, y) else path.lineTo(x, y)
                }
                drawPath(path, color = color, style = Stroke(width = 3.dp.toPx(), cap = StrokeCap.Round))
            }
        }
        if (labels.isNotEmpty()) {
            Row(modifier = Modifier.fillMaxWidth()) {
                Text(labels.first(), style = MaterialTheme.typography.labelSmall, modifier = Modifier.weight(1f))
                if (labels.size > 2) {
                    Text(
                        labels[labels.size / 2],
                        style = MaterialTheme.typography.labelSmall,
                        modifier = Modifier.weight(1f),
                        textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                    )
                }
                Text(
                    labels.last(),
                    style = MaterialTheme.typography.labelSmall,
                    modifier = Modifier.weight(1f),
                    textAlign = androidx.compose.ui.text.style.TextAlign.End,
                )
            }
        }
        Spacer(Modifier.height(8.dp))
        series.forEach { (label, _, color) -> LegendRow(label = label, color = color) }
    }
}

@Composable
private fun DonutCard(donut: JsonObject) {
    val segments = donut.objects("segments").map { segment ->
        Triple(segment.strOr("label", ""), segment.double("value") ?: 0.0, ServerColors.parse(segment.str("color")))
    }
    val total = segments.sumOf { it.second }.takeIf { it > 0 } ?: 1.0
    val trackColor = MaterialTheme.colorScheme.surfaceVariant
    ChartCard(title = donut.text("title"), subtitle = donut.text("subtitle")) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(120.dp)) {
                Canvas(modifier = Modifier.size(120.dp)) {
                    val stroke = 18.dp.toPx()
                    val arcSize = Size(size.width - stroke, size.height - stroke)
                    val topLeft = Offset(stroke / 2, stroke / 2)
                    drawArc(trackColor, 0f, 360f, false, topLeft, arcSize, style = Stroke(width = stroke))
                    var start = -90f
                    segments.forEach { (_, value, color) ->
                        val sweep = (value / total * 360.0).toFloat()
                        if (sweep > 0f) {
                            drawArc(color, start, sweep, false, topLeft, arcSize, style = Stroke(width = stroke))
                        }
                        start += sweep
                    }
                }
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(donut.strOr("total", "0"), style = MaterialTheme.typography.titleLarge)
                    Text(
                        donut.strOr("totalLabel", ""),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            Spacer(Modifier.width(16.dp))
            Column(modifier = Modifier.weight(1f)) {
                segments.forEach { (label, value, color) ->
                    LegendRow(label = label, color = color, value = formatChartNumber(value))
                }
            }
        }
    }
}

@Composable
private fun BarsCard(bars: JsonObject) {
    val items = bars.objects("items").map { item ->
        Triple(item.strOr("label", ""), item.double("value") ?: 0.0, ServerColors.parse(item.str("color")))
    }
    val maxValue = items.maxOfOrNull { it.second }?.takeIf { it > 0 } ?: 1.0
    ChartCard(title = bars.text("title"), subtitle = bars.text("subtitle")) {
        items.forEach { (label, value, color) ->
            BarRow(label = label, valueText = formatChartNumber(value), fraction = (value / maxValue).toFloat(), color = color)
        }
    }
}

@Composable
private fun ProgressListCard(radar: JsonObject) {
    val items = radar.objects("items")
    ChartCard(title = radar.text("title"), subtitle = radar.text("subtitle")) {
        items.forEachIndexed { index, item ->
            val value = (item.double("value") ?: 0.0).coerceIn(0.0, 100.0)
            BarRow(
                label = item.strOr("label", ""),
                valueText = formatChartNumber(value) + "%",
                fraction = (value / 100.0).toFloat(),
                color = ServerColors.chartPalette[index % ServerColors.chartPalette.size],
                helper = item.text("helper"),
            )
        }
    }
}

@Composable
private fun BarRow(label: String, valueText: String, fraction: Float, color: Color, helper: String? = null) {
    val track = MaterialTheme.colorScheme.surfaceVariant
    Column(modifier = Modifier.padding(vertical = 6.dp)) {
        Row {
            Text(label, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(valueText, style = MaterialTheme.typography.labelLarge)
        }
        Spacer(Modifier.height(4.dp))
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(8.dp)
                .background(track, RoundedCornerShape(4.dp)),
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth(fraction.coerceIn(0f, 1f))
                    .height(8.dp)
                    .background(color, RoundedCornerShape(4.dp)),
            )
        }
        if (!helper.isNullOrBlank()) {
            Text(helper, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
private fun LegendRow(label: String, color: Color, value: String? = null) {
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(vertical = 2.dp)) {
        Box(
            modifier = Modifier
                .size(10.dp)
                .background(color, CircleShape),
        )
        Spacer(Modifier.width(8.dp))
        Text(label, style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
        if (value != null) {
            Text(value, style = MaterialTheme.typography.labelMedium)
        }
    }
}

private fun formatChartNumber(value: Double): String =
    if (value % 1.0 == 0.0) value.toLong().toString() else String.format("%.1f", value)
