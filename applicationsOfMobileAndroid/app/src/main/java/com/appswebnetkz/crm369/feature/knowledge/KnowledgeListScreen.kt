package com.appswebnetkz.crm369.feature.knowledge

import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Knowledge bases visible to the user (`GET knowledge-bases` → `bases`). */
@Composable
fun KnowledgeListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { KnowledgeRepository(api) }
    val page = rememberLoadable(api) { repository.index() }
    val canManage = page.data?.canManage == true
    val openCreate: () -> Unit = { navigator.open(KnowledgeRoutes.NEW_BASE) }

    CrmScaffold(
        title = stringResource(R.string.module_knowledge_bases),
        floatingActionButton = {
            if (canManage) {
                FloatingActionButton(onClick = openCreate) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.knowledge_create_base))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = page,
            modifier = Modifier.padding(padding),
            isEmpty = { data -> data.bases.isEmpty() },
            empty = {
                EmptyState(
                    title = stringResource(R.string.knowledge_empty_title),
                    message = stringResource(if (canManage) R.string.knowledge_empty_manage else R.string.knowledge_empty_message),
                    icon = AppIcons.KnowledgeBases,
                    actionLabel = if (canManage) stringResource(R.string.knowledge_create_base) else null,
                    onAction = if (canManage) openCreate else null,
                )
            },
        ) { data ->
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(bottom = 88.dp),
            ) {
                items(data.bases, key = { it.id }) { base ->
                    BaseRow(base = base, onClick = { navigator.open(KnowledgeRoutes.base(base.id)) })
                    RowDivider()
                }
            }
        }
    }
}

@Composable
private fun BaseRow(base: KnowledgeBaseSummary, onClick: () -> Unit) {
    val groups = base.groups.joinToString(", ") { it.name }
    val groupsLine = if (groups.isNotBlank()) stringResource(R.string.knowledge_groups_line, groups) else null
    ListRow(
        title = base.title,
        overline = stringResource(R.string.knowledge_articles_count, base.articleCount),
        subtitle = listOfNotNull(base.description, groupsLine).joinToString("\n").ifBlank { null },
        maxSubtitleLines = 4,
        leading = {
            Icon(AppIcons.KnowledgeBases, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
        },
        trailing = if (!base.isPublished) {
            { StatusChip(text = stringResource(R.string.knowledge_unpublished), color = LocalExtendedColors.current.warning) }
        } else {
            null
        },
        onClick = onClick,
    )
}
