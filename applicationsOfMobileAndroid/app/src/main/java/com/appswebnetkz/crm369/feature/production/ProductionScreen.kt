package com.appswebnetkz.crm369.feature.production

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.ChipRow
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/**
 * Production overview: section chips (`sectionItems`) and the content of the active section
 * (hero, metrics, cards, records, focus points, next steps). Switching a chip reloads in place.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProductionScreen(initialSection: String?) {
    val api = rememberApi()
    val repository = remember(api) { ProductionRepository(api) }
    var section by rememberSaveable(initialSection) { mutableStateOf(initialSection) }
    val page = rememberLoadable(api) { repository.page(section) }

    LaunchedEffect(section) {
        if (page.data != null) page.refresh()
    }

    val loaded = page.data
    CrmScaffold(
        title = loaded?.labels?.title ?: stringResource(R.string.module_production),
        subtitle = loaded?.content?.title?.ifBlank { null },
    ) { padding ->
        LoadableContent(loadable = page, modifier = Modifier.padding(padding)) { data ->
            val selected = section ?: data.activeSection
            val content = data.content
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState()),
            ) {
                if (data.sections.size > 1) {
                    ChipRow {
                        data.sections.forEach { item ->
                            FilterChip(
                                selected = item.key == selected,
                                onClick = { if (item.key != selected) section = item.key },
                                label = { Text(item.title) },
                            )
                        }
                    }
                }

                ProductionHero(eyebrow = data.labels.eyebrow, content = content)

                if (content.metrics.isNotEmpty()) {
                    content.metrics.chunked(2).forEach { row ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 16.dp, vertical = 4.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            row.forEach { metric ->
                                ProductionMetricTile(metric = metric, modifier = Modifier.weight(1f))
                            }
                            if (row.size == 1) {
                                Spacer(Modifier.weight(1f))
                            }
                        }
                    }
                }

                if (content.cards.isNotEmpty()) {
                    SectionHeader(title = data.labels.cardsTitle ?: stringResource(R.string.production_cards))
                    content.cards.forEach { card ->
                        InfoCard(title = card.title) {
                            val description = card.description
                            if (description != null) {
                                Text(
                                    text = description,
                                    style = MaterialTheme.typography.bodyMedium,
                                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
                                )
                            }
                        }
                    }
                }

                if (content.records.isNotEmpty()) {
                    SectionHeader(title = content.recordsTitle ?: data.labels.recordsLabel ?: stringResource(R.string.production_records))
                    val recordsDescription = content.recordsDescription
                    if (recordsDescription != null) {
                        Text(
                            text = recordsDescription,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 16.dp),
                        )
                    }
                    InfoCard {
                        content.records.forEach { record ->
                            val badge = record.badge
                            ListRow(
                                title = record.title,
                                subtitle = record.description,
                                maxSubtitleLines = 4,
                                trailing = if (badge != null) {
                                    { StatusChip(text = badge) }
                                } else {
                                    null
                                },
                            )
                        }
                    }
                }

                if (content.focusPoints.isNotEmpty()) {
                    SectionHeader(title = content.focusTitle ?: data.labels.focusLabel ?: stringResource(R.string.production_focus))
                    InfoCard {
                        content.focusPoints.forEach { point ->
                            ProductionBullet(marker = null, text = point)
                        }
                    }
                }

                if (content.nextSteps.isNotEmpty()) {
                    SectionHeader(title = content.nextStepsTitle ?: data.labels.nextStepsLabel ?: stringResource(R.string.production_next_steps))
                    InfoCard {
                        content.nextSteps.forEachIndexed { index, step ->
                            ProductionBullet(marker = (index + 1).toString(), text = step)
                        }
                    }
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }
}

@Composable
private fun ProductionHero(eyebrow: String?, content: ProductionSectionContent) {
    val heroTitle = content.heroTitle ?: content.title
    val description = content.heroDescription ?: content.summary
    Surface(
        color = MaterialTheme.colorScheme.primaryContainer,
        contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
        shape = MaterialTheme.shapes.large,
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            if (eyebrow != null) {
                Text(text = eyebrow, style = MaterialTheme.typography.labelMedium)
                Spacer(Modifier.height(4.dp))
            }
            Text(text = heroTitle, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold)
            if (description != null) {
                Spacer(Modifier.height(8.dp))
                Text(text = description, style = MaterialTheme.typography.bodyMedium)
            }
        }
    }
}

@Composable
private fun ProductionMetricTile(metric: ProductionMetric, modifier: Modifier = Modifier) {
    Surface(
        color = MaterialTheme.colorScheme.surfaceContainerLow,
        shape = MaterialTheme.shapes.medium,
        modifier = modifier,
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Text(
                text = metric.label,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = metric.value,
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.primary,
            )
            val caption = metric.caption
            if (caption != null) {
                Spacer(Modifier.height(4.dp))
                Text(
                    text = caption,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun ProductionBullet(marker: String?, text: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 6.dp),
        verticalAlignment = Alignment.Top,
    ) {
        if (marker != null) {
            Text(
                text = "$marker.",
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
            )
        } else {
            Icon(
                imageVector = AppIcons.Check,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(18.dp),
            )
        }
        Spacer(Modifier.width(10.dp))
        Text(text = text, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
    }
}
