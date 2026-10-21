package com.appswebnetkz.crm369.feature.edo

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ChipRow
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SearchField
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

private const val FILTER_ALL = "all"

/** EDO documents (`GET edo/documents`) with a status filter and a local search. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EdoListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { EdoRepository(api) }
    val documents = rememberLoadable(api) { repository.documents() }
    var search by rememberSaveable { mutableStateOf("") }
    var status by rememberSaveable { mutableStateOf(FILTER_ALL) }

    CrmScaffold(
        title = stringResource(R.string.module_edo),
        subtitle = documents.data?.let { stringResource(R.string.edo_total, it.size) },
        floatingActionButton = {
            FloatingActionButton(onClick = { navigator.open(EdoRoutes.NEW) }) {
                Icon(AppIcons.Add, contentDescription = stringResource(R.string.edo_new))
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            SearchField(
                value = search,
                onValueChange = { search = it },
                placeholder = stringResource(R.string.edo_search),
            )
            ChipRow {
                FilterChip(
                    selected = status == FILTER_ALL,
                    onClick = { status = FILTER_ALL },
                    label = { Text(stringResource(R.string.edo_filter_all)) },
                )
                EdoDocument.STATUSES.forEach { value ->
                    FilterChip(
                        selected = status == value,
                        onClick = { status = value },
                        label = { Text(edoStatusLabel(value)) },
                    )
                }
            }
            LoadableContent(
                loadable = documents,
                isEmpty = { it.isEmpty() },
                empty = {
                    EmptyState(
                        title = stringResource(R.string.edo_empty),
                        message = stringResource(R.string.edo_empty_message),
                        icon = AppIcons.Edo,
                        actionLabel = stringResource(R.string.edo_new),
                        onAction = { navigator.open(EdoRoutes.NEW) },
                    )
                },
            ) { list ->
                val query = search.trim()
                val filtered = list.filter { document ->
                    (status == FILTER_ALL || document.status == status) && (query.isEmpty() || document.matches(query))
                }
                if (filtered.isEmpty()) {
                    EmptyState(
                        title = stringResource(R.string.edo_empty_filtered),
                        icon = AppIcons.Search,
                    )
                } else {
                    LazyColumn(modifier = Modifier.fillMaxSize()) {
                        items(filtered, key = { it.id }) { document ->
                            EdoDocumentRow(
                                document = document,
                                onClick = { navigator.open(EdoRoutes.document(document.id)) },
                            )
                            RowDivider()
                        }
                    }
                }
            }
        }
    }
}

private fun EdoDocument.matches(query: String): Boolean =
    listOfNotNull(title, externalReference, counterpartyName, counterpartyIdentifier)
        .any { it.contains(query, ignoreCase = true) }

@Composable
private fun EdoDocumentRow(document: EdoDocument, onClick: () -> Unit) {
    val details = listOfNotNull(
        document.counterpartyName.ifBlank { null },
        Formatters.shortStamp(document.updatedAt).ifBlank { null },
    ).joinToString(" · ")
    ListRow(
        title = document.title,
        overline = document.externalReference,
        subtitle = details.ifBlank { null },
        leading = { Icon(AppIcons.Edo, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
        trailing = { StatusChip(text = edoStatusLabel(document.status), color = edoStatusColor(document.status)) },
        onClick = onClick,
    )
}
