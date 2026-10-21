package com.appswebnetkz.crm369.feature.contacts

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
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
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.DebouncedSearchField
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.InlineProgress
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadMoreEffect
import com.appswebnetkz.crm369.ui.components.PagedContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberPagedLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Contacts as the CRM tab of the main screen (when the user has no access to funnels). */
@Composable
fun ContactsHomeScreen() {
    ContactsListScreen()
}

/** Searchable, filterable and paginated list of contacts (`GET contacts`). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ContactsListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { ContactsRepository(api) }
    val listState = rememberLazyListState()

    var search by rememberSaveable { mutableStateOf("") }
    var type by rememberSaveable { mutableStateOf("all") }
    var blacklist by rememberSaveable { mutableStateOf("all") }
    var meta by remember { mutableStateOf<ContactsListMeta?>(null) }
    var filterMenuOpen by remember { mutableStateOf(false) }

    val paged = rememberPagedLoadable(api, search, type, blacklist, distinctBy = { item: Contact -> item.id }) { page ->
        repository.page(page, search, type, blacklist) { loaded -> meta = loaded }
    }
    LoadMoreEffect(listState = listState, enabled = paged.hasMore) { paged.loadMore() }

    val currentMeta = meta
    val canCreate = currentMeta != null && (currentMeta.canCreatePerson || currentMeta.canCreateCompany)

    CrmScaffold(
        title = stringResource(R.string.contacts_title),
        subtitle = paged.total?.let { stringResource(R.string.contacts_total, it) },
        actions = {
            Box {
                IconButton(onClick = { filterMenuOpen = true }) {
                    Icon(AppIcons.Filter, contentDescription = stringResource(R.string.contacts_filter))
                }
                DropdownMenu(expanded = filterMenuOpen, onDismissRequest = { filterMenuOpen = false }) {
                    listOf(
                        "all" to R.string.contacts_blacklist_all,
                        "not_blacklisted" to R.string.contacts_blacklist_exclude,
                        "blacklisted" to R.string.contacts_blacklist_only,
                    ).forEach { (value, label) ->
                        DropdownMenuItem(
                            text = { Text(stringResource(label)) },
                            onClick = {
                                blacklist = value
                                filterMenuOpen = false
                            },
                            leadingIcon = if (blacklist == value) {
                                { Icon(AppIcons.Check, contentDescription = null) }
                            } else {
                                null
                            },
                        )
                    }
                }
            }
        },
        floatingActionButton = {
            if (canCreate) {
                FloatingActionButton(onClick = { navigator.open(ContactsRoutes.NEW) }) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.contacts_new))
                }
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            DebouncedSearchField(
                onQuery = { query -> search = query },
                initialValue = search,
                placeholder = stringResource(R.string.contacts_search),
            )
            val types = currentMeta?.types.orEmpty()
            if (types.size > 1) {
                Row(
                    modifier = Modifier
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 16.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    FilterChip(
                        selected = type == "all",
                        onClick = { type = "all" },
                        label = { Text(stringResource(R.string.contacts_type_all)) },
                    )
                    types.forEach { option ->
                        FilterChip(
                            selected = type == option.value,
                            onClick = { type = option.value },
                            label = { Text(option.label) },
                        )
                    }
                }
            }
            PagedContent(
                paged = paged,
                empty = {
                    EmptyState(
                        title = stringResource(R.string.contacts_empty),
                        message = if (search.isNotBlank()) stringResource(R.string.contacts_empty_search) else null,
                        icon = AppIcons.Contacts,
                        actionLabel = if (canCreate) stringResource(R.string.contacts_new) else null,
                        onAction = if (canCreate) {
                            { navigator.open(ContactsRoutes.NEW) }
                        } else {
                            null
                        },
                    )
                },
            ) { contacts ->
                LazyColumn(state = listState, modifier = Modifier.fillMaxSize()) {
                    items(contacts, key = { it.id }) { contact ->
                        ContactRow(contact = contact, onClick = { navigator.open(ContactsRoutes.detail(contact.id)) })
                        RowDivider()
                    }
                    if (paged.isLoadingMore) {
                        item { InlineProgress() }
                    }
                }
            }
        }
    }
}

@Composable
private fun ContactRow(contact: Contact, onClick: () -> Unit) {
    val details = listOfNotNull(
        contact.contactPerson,
        contact.position,
        contact.phone ?: contact.email,
    ).joinToString(" · ")
    ListRow(
        title = contact.name,
        subtitle = details.ifBlank { null },
        leading = { Avatar(name = contact.name, imageUrl = contact.avatarUrl) },
        trailing = {
            if (contact.isBlacklisted) {
                StatusChip(text = stringResource(R.string.contacts_blacklisted), color = MaterialTheme.colorScheme.error)
            } else {
                StatusChip(text = contact.typeLabel, color = MaterialTheme.colorScheme.secondary)
            }
        },
        onClick = onClick,
    )
}
