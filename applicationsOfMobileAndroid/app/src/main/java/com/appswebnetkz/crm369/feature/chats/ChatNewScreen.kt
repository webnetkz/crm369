package com.appswebnetkz.crm369.feature.chats

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.DebouncedSearchField
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** "New chat": people search (`GET chats?search=`) → `POST chats/direct` → the conversation. */
@Composable
fun ChatNewScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val bootstrap = rememberBootstrap()
    val repository = remember(api) { ChatsRepository(api) }
    val runner = rememberActionRunner()
    var search by rememberSaveable { mutableStateOf("") }
    val myId = bootstrap?.user?.id

    val index = rememberLoadable(api, search) { repository.index(search) }

    CrmScaffold(title = stringResource(R.string.chats_new)) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            DebouncedSearchField(
                onQuery = { query -> search = query },
                initialValue = search,
                placeholder = stringResource(R.string.chats_search_placeholder),
            )
            if (runner.isBusy) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
            }
            LoadableContent(
                loadable = index,
                isEmpty = { data -> data.contacts.none { it.id != myId } },
                empty = { EmptyState(title = stringResource(R.string.chats_no_contacts), icon = AppIcons.People) },
            ) { data ->
                val people = data.contacts.filter { it.id != myId }
                LazyColumn(modifier = Modifier.fillMaxSize()) {
                    items(people, key = { it.id }) { user ->
                        ListRow(
                            title = user.name,
                            subtitle = user.email,
                            leading = { Avatar(name = user.name, imageUrl = user.avatarUrl) },
                            trailing = {
                                Icon(
                                    AppIcons.Chat,
                                    contentDescription = stringResource(R.string.chats_start_chat),
                                    tint = MaterialTheme.colorScheme.primary,
                                )
                            },
                            onClick = {
                                runner.execute(
                                    onSuccess = { conversationId: Long -> navigator.replace(ChatsRoutes.conversation(conversationId)) },
                                ) { repository.openDirect(user.id) }
                            },
                        )
                        RowDivider()
                    }
                }
            }
        }
    }
}
