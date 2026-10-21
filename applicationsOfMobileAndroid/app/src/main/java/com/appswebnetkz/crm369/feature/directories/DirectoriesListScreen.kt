package com.appswebnetkz.crm369.feature.directories

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
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** All reference directories (`GET directories`). */
@Composable
fun DirectoriesListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val bootstrap = rememberBootstrap()
    val canManage = bootstrap?.can("canManageDirectories") == true
    val repository = remember(api) { DirectoriesRepository(api) }
    val directories = rememberLoadable(api) { repository.directories() }

    CrmScaffold(
        title = stringResource(R.string.module_directories),
        subtitle = directories.data?.let { stringResource(R.string.directories_total, it.size) },
        floatingActionButton = {
            if (canManage) {
                FloatingActionButton(onClick = { navigator.open(DirectoriesRoutes.NEW) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.directories_new))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = directories,
            modifier = Modifier.padding(padding),
            isEmpty = { it.isEmpty() },
            empty = {
                EmptyState(
                    title = stringResource(R.string.directories_empty),
                    message = stringResource(R.string.directories_empty_message),
                    icon = AppIcons.Directories,
                    actionLabel = if (canManage) stringResource(R.string.directories_new) else null,
                    onAction = if (canManage) {
                        { navigator.open(DirectoriesRoutes.NEW) }
                    } else {
                        null
                    },
                )
            },
        ) { list ->
            LazyColumn(modifier = Modifier.fillMaxSize()) {
                items(list, key = { it.id }) { directory ->
                    val counts = stringResource(R.string.directories_records_count, directory.recordsCount) + " · " +
                        stringResource(R.string.directories_columns_count, directory.columns.size)
                    ListRow(
                        title = directory.name,
                        overline = counts,
                        subtitle = directory.description,
                        leading = { Icon(AppIcons.Directories, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
                        trailing = if (directory.csvExchangeEnabled) {
                            { StatusChip(text = stringResource(R.string.directories_csv_badge), color = MaterialTheme.colorScheme.secondary) }
                        } else {
                            null
                        },
                        onClick = { navigator.open(DirectoriesRoutes.detail(directory.id)) },
                    )
                    RowDivider()
                }
            }
        }
    }
}
