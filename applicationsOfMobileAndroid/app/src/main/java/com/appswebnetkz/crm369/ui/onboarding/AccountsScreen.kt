package com.appswebnetkz.crm369.ui.onboarding

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.session.Account
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.PortalLogo
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.navigation.AppRoutes
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.launch

/** All portals added on this device (like the portal switcher of Bitrix24). */
@Composable
fun AccountsScreen() {
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val scope = rememberCoroutineScope()
    val accounts by container.session.accounts.collectAsStateWithLifecycle()
    val active by container.session.activeAccount.collectAsStateWithLifecycle()
    var accountToRemove by remember { mutableStateOf<Account?>(null) }

    CrmScaffold(
        title = stringResource(R.string.accounts_title),
        floatingActionButton = {
            ExtendedFloatingActionButton(
                onClick = { navigator.open(AppRoutes.PORTAL) },
                icon = { Icon(AppIcons.Add, contentDescription = null) },
                text = { Text(stringResource(R.string.accounts_add)) },
            )
        },
    ) { padding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            items(accounts, key = { it.id }) { account ->
                val hasSession = container.session.hasSession(account)
                val isActive = account.id == active?.id
                ListRow(
                    title = account.portalName,
                    subtitle = listOf(account.userName.ifBlank { account.email }, account.host)
                        .filter { it.isNotBlank() }
                        .joinToString(" · "),
                    leading = { PortalLogo(name = account.portalName, logoUrl = account.logoUrl, size = 44.dp) },
                    trailing = {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            if (isActive && hasSession) {
                                StatusChip(text = stringResource(R.string.accounts_active))
                            }
                            if (!hasSession) {
                                StatusChip(
                                    text = stringResource(R.string.accounts_signed_out),
                                    color = MaterialTheme.colorScheme.error,
                                )
                            }
                            if (!isActive || !hasSession) {
                                IconButton(onClick = { accountToRemove = account }) {
                                    Icon(AppIcons.Logout, contentDescription = stringResource(R.string.accounts_remove))
                                }
                            }
                        }
                    },
                    onClick = {
                        if (hasSession) {
                            container.session.switchAccount(account.id)
                            navigator.resetToHome()
                        } else {
                            navigator.open(AppRoutes.login(account.baseUrl, account.email))
                        }
                    },
                )
                RowDivider()
            }
        }
    }

    val removing = accountToRemove
    if (removing != null) {
        ConfirmDialog(
            title = stringResource(R.string.accounts_remove_title),
            text = stringResource(R.string.accounts_remove_text, removing.portalName),
            confirmLabel = stringResource(R.string.accounts_remove),
            destructive = true,
            onDismiss = { accountToRemove = null },
            onConfirm = {
                scope.launch {
                    container.session.logout(removing)
                    if (container.session.activeAccount.value == null) {
                        navigator.resetToPortal()
                    }
                }
            },
        )
    }
}
