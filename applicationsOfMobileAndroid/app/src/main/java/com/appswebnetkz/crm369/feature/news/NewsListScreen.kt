package com.appswebnetkz.crm369.feature.news

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Card
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** News feed (`GET news`): cards with image, title, excerpt and date; managers can create news. */
@Composable
fun NewsListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { NewsRepository(api) }
    val page = rememberLoadable(api) { repository.page() }
    val canManage = page.data?.canManage == true
    val openCreate: () -> Unit = { navigator.open(NewsRoutes.NEW) }

    CrmScaffold(
        title = stringResource(R.string.module_news),
        floatingActionButton = {
            if (canManage) {
                FloatingActionButton(onClick = openCreate) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.news_create))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = page,
            modifier = Modifier.padding(padding),
            isEmpty = { data -> data.items.isEmpty() },
            empty = {
                EmptyState(
                    title = stringResource(R.string.news_empty_title),
                    message = stringResource(if (canManage) R.string.news_empty_manage else R.string.news_empty_message),
                    icon = AppIcons.News,
                    actionLabel = if (canManage) stringResource(R.string.news_create) else null,
                    onAction = if (canManage) openCreate else null,
                )
            },
        ) { data ->
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 88.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                items(data.items, key = { it.id }) { news ->
                    NewsCard(
                        news = news,
                        imageModel = news.imageUrl?.let { url -> api.absoluteUrl(url) },
                        onClick = { navigator.open(NewsRoutes.detail(news.slug)) },
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NewsCard(news: NewsItem, imageModel: String?, onClick: () -> Unit) {
    Card(onClick = onClick, modifier = Modifier.fillMaxWidth()) {
        if (imageModel != null) {
            AsyncImage(
                model = imageModel,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(170.dp),
            )
        }
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            val date = Formatters.date(news.publishedAt ?: news.updatedAt)
            if (date.isNotBlank() || !news.isPublished) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    if (date.isNotBlank()) {
                        Text(
                            text = date,
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    if (!news.isPublished) {
                        StatusChip(text = stringResource(R.string.news_draft), color = LocalExtendedColors.current.warning)
                    }
                }
            }
            Text(
                text = news.title,
                style = MaterialTheme.typography.titleMedium,
                maxLines = 3,
                overflow = TextOverflow.Ellipsis,
            )
            val excerpt = news.excerpt
            if (excerpt != null) {
                Text(
                    text = excerpt,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 3,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            Spacer(Modifier.height(2.dp))
        }
    }
}
