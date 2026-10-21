package com.appswebnetkz.crm369.feature.company

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.DebouncedSearchField
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

private const val MAX_INDENT_LEVEL = 6

/**
 * Company structure (`GET company-structure`): stats, an expandable org tree and a flat search
 * over name, position and email. Tap → employee card.
 */
@Composable
fun CompanyStructureScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { CompanyRepository(api) }
    val structure = rememberLoadable(api) { repository.structure() }
    var query by rememberSaveable { mutableStateOf("") }
    var expandedIds by rememberSaveable { mutableStateOf(listOf<Long>()) }
    var treeInitialized by rememberSaveable { mutableStateOf(false) }

    val loaded = structure.data
    CrmScaffold(
        title = stringResource(R.string.module_company_structure),
        subtitle = if (loaded != null) stringResource(R.string.company_total_value, loaded.stats.totalUsers) else null,
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            DebouncedSearchField(
                onQuery = { value -> query = value },
                initialValue = query,
                placeholder = stringResource(R.string.company_search_placeholder),
            )
            LoadableContent(
                loadable = structure,
                isEmpty = { data -> data.roots.isEmpty() },
                empty = {
                    EmptyState(title = stringResource(R.string.company_empty_structure), icon = AppIcons.CompanyStructure)
                },
            ) { data ->
                LaunchedEffect(data) {
                    if (!treeInitialized) {
                        expandedIds = data.roots.map { it.id }
                        treeInitialized = true
                    }
                }
                val openUser: (Long) -> Unit = { id -> navigator.open(CompanyRoutes.user(id)) }
                if (query.isNotBlank()) {
                    val results = remember(data, query) { data.search(query) }
                    LazyColumn(modifier = Modifier.fillMaxSize()) {
                        item(key = "results-header") {
                            SectionHeader(title = stringResource(R.string.company_results_count, results.size))
                        }
                        if (results.isEmpty()) {
                            item(key = "results-empty") {
                                Text(
                                    text = stringResource(R.string.company_empty),
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                                )
                            }
                        }
                        items(results, key = { "result-${it.id}" }) { node ->
                            CompanyPersonRow(person = node.person, onClick = { openUser(node.id) })
                            RowDivider()
                        }
                    }
                } else {
                    val expandedSet = remember(expandedIds) { expandedIds.toSet() }
                    val rows = remember(data, expandedSet) { data.visibleRows(expandedSet) }
                    LazyColumn(modifier = Modifier.fillMaxSize()) {
                        item(key = "stats") { CompanyStatsHeader(stats = data.stats) }
                        item(key = "tree-header") { SectionHeader(title = stringResource(R.string.company_tree_title)) }
                        items(rows, key = { "node-${it.node.id}" }) { row ->
                            CompanyTreeRowView(
                                row = row,
                                onToggle = {
                                    expandedIds = if (row.isExpanded) expandedIds - row.node.id else expandedIds + row.node.id
                                },
                                onClick = { openUser(row.node.id) },
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun CompanyStatsHeader(stats: CompanyStats) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        CompanyStatTile(value = stats.totalUsers, label = stringResource(R.string.company_total_users), modifier = Modifier.weight(1f))
        CompanyStatTile(value = stats.rootUsers, label = stringResource(R.string.company_root_users), modifier = Modifier.weight(1f))
        CompanyStatTile(value = stats.managers, label = stringResource(R.string.company_managers), modifier = Modifier.weight(1f))
    }
}

@Composable
private fun CompanyStatTile(value: Int, label: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(12.dp),
        color = MaterialTheme.colorScheme.surfaceContainer,
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Text(
                text = value.toString(),
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
            )
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                minLines = 2,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
        }
    }
}

@Composable
private fun CompanyTreeRowView(row: CompanyTreeRow, onToggle: () -> Unit, onClick: () -> Unit) {
    val node = row.node
    val person = node.person
    val indent = (minOf(row.level, MAX_INDENT_LEVEL) * 16).dp
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(start = 4.dp + indent, end = 16.dp, top = 4.dp, bottom = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (node.children.isNotEmpty()) {
            IconButton(onClick = onToggle) {
                Icon(
                    if (row.isExpanded) AppIcons.ExpandLess else AppIcons.ExpandMore,
                    contentDescription = stringResource(if (row.isExpanded) R.string.company_collapse else R.string.company_expand),
                )
            }
        } else {
            Spacer(Modifier.width(48.dp))
        }
        Avatar(name = person.fullName, imageUrl = person.avatarUrl, size = 40.dp)
        Spacer(Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = person.fullName,
                style = MaterialTheme.typography.bodyLarge,
                color = if (person.isActive) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = person.position ?: stringResource(R.string.company_no_position),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        if (node.subordinatesCount > 0) {
            Spacer(Modifier.width(8.dp))
            StatusChip(
                text = stringResource(R.string.company_reports_count, node.subordinatesCount),
                color = MaterialTheme.colorScheme.secondary,
            )
        }
    }
}

/** Person row used by the search results and the employee card (manager, subordinates). */
@Composable
internal fun CompanyPersonRow(person: CompanyPerson, onClick: () -> Unit) {
    ListRow(
        title = person.fullName,
        subtitle = listOfNotNull(person.position, person.email).joinToString(" · ").ifBlank { null },
        leading = { Avatar(name = person.fullName, imageUrl = person.avatarUrl) },
        trailing = { Icon(AppIcons.ChevronRight, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant) },
        onClick = onClick,
    )
}
