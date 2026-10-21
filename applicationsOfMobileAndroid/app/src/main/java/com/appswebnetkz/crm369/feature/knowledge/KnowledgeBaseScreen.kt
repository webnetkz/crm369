package com.appswebnetkz.crm369.feature.knowledge

import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** A knowledge base: description, groups and the expandable article tree (`GET knowledge-bases/{id}`). */
@Composable
fun KnowledgeBaseScreen(baseId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { KnowledgeRepository(api) }
    val runner = rememberActionRunner()
    val page = rememberLoadable(api, baseId) { repository.base(baseId) }
    val collapsed = remember(baseId) { mutableStateListOf<Long>() }
    var confirmDelete by remember { mutableStateOf(false) }
    val deletedMessage = stringResource(R.string.knowledge_base_deleted)
    val base = page.data?.activeBase?.takeIf { it.id == baseId }
    val canManage = page.data?.canManage == true
    val openNewArticle: () -> Unit = { navigator.open(KnowledgeRoutes.newArticle(baseId)) }

    CrmScaffold(
        title = base?.title ?: stringResource(R.string.module_knowledge_bases),
        subtitle = base?.let { stringResource(R.string.knowledge_articles_count, countArticles(it.articles)) },
        actions = {
            if (base != null && canManage) {
                IconButton(onClick = { navigator.open(KnowledgeRoutes.editBase(baseId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.knowledge_edit_base))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.knowledge_delete_base))
                }
            }
        },
        floatingActionButton = {
            if (base != null && canManage) {
                FloatingActionButton(onClick = openNewArticle) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.knowledge_create_article))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = page,
            modifier = Modifier.padding(padding),
            isEmpty = { data -> data.activeBase?.id != baseId },
            empty = { EmptyState(title = stringResource(R.string.knowledge_base_not_found), icon = AppIcons.KnowledgeBases) },
        ) { data ->
            val current = data.activeBase
            if (current != null) {
                if (current.articles.isEmpty()) {
                    EmptyState(
                        title = stringResource(R.string.knowledge_empty_articles),
                        message = current.description,
                        icon = AppIcons.Document,
                        actionLabel = if (canManage) stringResource(R.string.knowledge_create_article) else null,
                        onAction = if (canManage) openNewArticle else null,
                    )
                } else {
                    val rows = visibleRows(current.articles, collapsed)
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 88.dp),
                    ) {
                        item(key = "header") { BaseHeader(base = current) }
                        items(rows, key = { row -> row.first.id }) { row ->
                            val node = row.first
                            ArticleTreeRow(
                                node = node,
                                depth = row.second,
                                expanded = node.id !in collapsed,
                                onToggle = {
                                    if (node.id in collapsed) collapsed.remove(node.id) else collapsed.add(node.id)
                                },
                                onClick = { navigator.open(KnowledgeRoutes.article(baseId, node.id)) },
                            )
                        }
                    }
                }
            }
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.knowledge_delete_base),
            text = stringResource(R.string.knowledge_delete_base_confirm),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.deleteBase(baseId)
                }
            },
        )
    }
}

@Composable
private fun BaseHeader(base: KnowledgeBaseDetail) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        val description = base.description
        if (description != null) {
            Text(
                text = description,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Row(
            modifier = Modifier.horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            if (!base.isPublished) {
                StatusChip(text = stringResource(R.string.knowledge_unpublished), color = LocalExtendedColors.current.warning)
            }
            if (base.groups.isEmpty()) {
                StatusChip(text = stringResource(R.string.knowledge_visible_to_all), color = MaterialTheme.colorScheme.secondary)
            }
            base.groups.forEach { group ->
                StatusChip(text = group.name, color = MaterialTheme.colorScheme.secondary)
            }
        }
    }
    RowDivider()
}

@Composable
private fun ArticleTreeRow(
    node: KnowledgeArticleNode,
    depth: Int,
    expanded: Boolean,
    onToggle: () -> Unit,
    onClick: () -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(start = (4 + depth * 20).dp, end = 16.dp, top = 2.dp, bottom = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (node.children.isNotEmpty()) {
            IconButton(onClick = onToggle) {
                Icon(
                    imageVector = if (expanded) AppIcons.ExpandLess else AppIcons.ExpandMore,
                    contentDescription = stringResource(if (expanded) R.string.knowledge_collapse else R.string.knowledge_expand),
                )
            }
        } else {
            Box(modifier = Modifier.size(48.dp), contentAlignment = Alignment.Center) {
                Icon(
                    imageVector = AppIcons.Document,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(20.dp),
                )
            }
        }
        Column(
            modifier = Modifier
                .weight(1f)
                .padding(vertical = 10.dp),
        ) {
            Text(text = node.title, style = MaterialTheme.typography.bodyLarge)
            val excerpt = node.excerpt
            if (excerpt != null) {
                Text(
                    text = excerpt,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
        if (!node.isPublished) {
            StatusChip(text = stringResource(R.string.knowledge_unpublished), color = LocalExtendedColors.current.warning)
        }
    }
}

/** Visible rows of the tree (children of collapsed nodes are hidden), ordered by `sort_order`. */
private fun visibleRows(
    nodes: List<KnowledgeArticleNode>,
    collapsed: List<Long>,
    depth: Int = 0,
): List<Pair<KnowledgeArticleNode, Int>> {
    val result = mutableListOf<Pair<KnowledgeArticleNode, Int>>()
    nodes.sortedBy { it.sortOrder }.forEach { node ->
        result.add(node to depth)
        if (node.id !in collapsed) {
            result.addAll(visibleRows(node.children, collapsed, depth + 1))
        }
    }
    return result
}

private fun countArticles(nodes: List<KnowledgeArticleNode>): Int =
    nodes.sumOf { node -> 1 + countArticles(node.children) }
