package com.appswebnetkz.crm369.feature.knowledge

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.HtmlText
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.htmlToPlainText
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** An article rendered natively from its blocks (`GET knowledge-bases/{id}/articles/{aid}`). */
@Composable
fun KnowledgeArticleScreen(baseId: Long, articleId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { KnowledgeRepository(api) }
    val runner = rememberActionRunner()
    val page = rememberLoadable(api, baseId, articleId) { repository.article(baseId, articleId) }
    var confirmDelete by remember { mutableStateOf(false) }
    val deletedMessage = stringResource(R.string.knowledge_article_deleted)
    val article = page.data?.activeArticle?.takeIf { it.id == articleId }
    val canManage = page.data?.canManage == true

    CrmScaffold(
        title = article?.title ?: stringResource(R.string.module_knowledge_bases),
        subtitle = page.data?.activeBase?.title,
        actions = {
            if (article != null && canManage) {
                IconButton(onClick = { navigator.open(KnowledgeRoutes.newArticle(baseId, articleId)) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.knowledge_create_child_article))
                }
                IconButton(onClick = { navigator.open(KnowledgeRoutes.editArticle(baseId, articleId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.knowledge_edit_article))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.knowledge_delete_article))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = page,
            modifier = Modifier.padding(padding),
            isEmpty = { data -> data.activeArticle?.id != articleId },
            empty = { EmptyState(title = stringResource(R.string.knowledge_article_not_found), icon = AppIcons.Document) },
        ) { data ->
            val current = data.activeArticle
            if (current != null) {
                val children = data.activeBase
                    ?.let { base -> KnowledgeTree.findNode(base.articles, articleId) }
                    ?.children
                    .orEmpty()
                    .sortedBy { it.sortOrder }
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState()),
                ) {
                    ArticleBody(article = current, api = api)
                    if (children.isNotEmpty()) {
                        SectionHeader(title = stringResource(R.string.knowledge_child_articles))
                        children.forEach { child ->
                            ListRow(
                                title = child.title,
                                subtitle = child.excerpt,
                                leading = {
                                    Icon(AppIcons.Document, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                },
                                trailing = if (!child.isPublished) {
                                    { StatusChip(text = stringResource(R.string.knowledge_unpublished), color = LocalExtendedColors.current.warning) }
                                } else {
                                    null
                                },
                                onClick = { navigator.open(KnowledgeRoutes.article(baseId, child.id)) },
                            )
                        }
                    }
                    Spacer(Modifier.height(32.dp))
                }
            }
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.knowledge_delete_article),
            text = stringResource(R.string.knowledge_delete_article_confirm),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.deleteArticle(baseId, articleId)
                }
            },
        )
    }
}

@Composable
private fun ArticleBody(article: KnowledgeArticle, api: ApiClient) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(text = article.title, style = MaterialTheme.typography.headlineSmall)
        val updated = Formatters.dateTime(article.updatedAt)
        if (updated.isNotBlank() || !article.isPublished) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                if (updated.isNotBlank()) {
                    Text(
                        text = stringResource(R.string.knowledge_updated_at, updated),
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (!article.isPublished) {
                    StatusChip(text = stringResource(R.string.knowledge_unpublished), color = LocalExtendedColors.current.warning)
                }
            }
        }
        val excerpt = article.excerpt
        if (excerpt != null) {
            Text(
                text = excerpt,
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        if (article.blocks.isEmpty()) {
            Text(
                text = stringResource(R.string.knowledge_article_empty),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        article.blocks.forEach { block ->
            ArticleBlockView(block = block, api = api)
        }
    }
}

@Composable
private fun ArticleBlockView(block: KnowledgeBlock, api: ApiClient) {
    when (block.type) {
        KnowledgeBlock.TYPE_HEADING -> {
            val style = when (block.headingLevel) {
                1 -> MaterialTheme.typography.headlineSmall
                3 -> MaterialTheme.typography.titleMedium
                else -> MaterialTheme.typography.titleLarge
            }
            Text(
                text = htmlToPlainText(block.content),
                style = style,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(top = 8.dp),
            )
        }
        KnowledgeBlock.TYPE_LIST -> {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                block.items.forEachIndexed { index, item ->
                    Row(verticalAlignment = Alignment.Top) {
                        Text(
                            text = if (block.ordered) "${index + 1}." else "•",
                            style = MaterialTheme.typography.bodyLarge,
                            modifier = Modifier.width(28.dp),
                        )
                        HtmlText(html = item, modifier = Modifier.weight(1f), textSizeSp = 16f)
                    }
                }
            }
        }
        KnowledgeBlock.TYPE_IMAGE -> {
            val url = block.imageUrl
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                if (url != null) {
                    AsyncImage(
                        model = api.absoluteUrl(url),
                        contentDescription = block.caption,
                        contentScale = ContentScale.FillWidth,
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(MaterialTheme.shapes.medium),
                    )
                }
                val caption = block.caption
                if (caption != null) {
                    Text(
                        text = caption,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
        else -> {
            val content = block.content
            if (content != null) {
                HtmlText(html = content, textSizeSp = 16f)
            }
        }
    }
}
