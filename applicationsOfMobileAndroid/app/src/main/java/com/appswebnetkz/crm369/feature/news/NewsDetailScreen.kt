package com.appswebnetkz.crm369.feature.news

import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.HtmlArticleView
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** News article (`GET news/{slug}` → `activeNews`) with edit/delete for managers. */
@Composable
fun NewsDetailScreen(slug: String) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { NewsRepository(api) }
    val runner = rememberActionRunner()
    val page = rememberLoadable(api, slug) { repository.detail(slug) }
    var confirmDelete by remember { mutableStateOf(false) }
    val deletedMessage = stringResource(R.string.news_deleted)
    val detail = page.data?.active
    val canManage = page.data?.canManage == true

    CrmScaffold(
        title = detail?.item?.title ?: stringResource(R.string.module_news),
        actions = {
            if (detail != null && canManage) {
                IconButton(onClick = { navigator.open(NewsRoutes.edit(detail.item.slug.ifBlank { slug })) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = page,
            modifier = Modifier.padding(padding),
            isEmpty = { data -> data.active == null },
            empty = { EmptyState(title = stringResource(R.string.news_not_found), icon = AppIcons.News) },
        ) { data ->
            val active = data.active
            if (active != null) {
                val meta = listOfNotNull(
                    active.authorName?.let { name -> stringResource(R.string.news_author, name) },
                    Formatters.dateTime(active.item.publishedAt ?: active.item.updatedAt).ifBlank { null },
                    if (active.item.isPublished) null else stringResource(R.string.news_draft),
                ).joinToString(" · ")
                val imageUrl = active.item.imageUrl?.let { url -> api.absoluteUrl(url) }
                val html = remember(active, imageUrl, meta) { articleHtml(active, imageUrl, meta) }
                HtmlArticleView(html = html, baseUrl = api.baseUrl)
            }
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.news_delete_title),
            text = stringResource(R.string.news_delete_text),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                val target = detail?.item?.slug?.ifBlank { null } ?: slug
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.delete(target)
                }
            },
        )
    }
}

/** Image, title, meta line, excerpt and the content converted to HTML. */
private fun articleHtml(detail: NewsDetail, imageUrl: String?, meta: String): String = buildString {
    if (imageUrl != null) {
        append("<p><img src=\"")
        append(NewsContent.escape(imageUrl))
        append("\" alt=\"\"></p>")
    }
    append("<h1>")
    append(NewsContent.escape(detail.item.title))
    append("</h1>")
    if (meta.isNotBlank()) {
        append("<div class=\"meta\">")
        append(NewsContent.escape(meta))
        append("</div>")
    }
    val excerpt = detail.item.excerpt
    if (excerpt != null) {
        append("<p><strong>")
        append(NewsContent.escape(excerpt))
        append("</strong></p>")
    }
    append(NewsContent.toHtml(detail.content))
}
