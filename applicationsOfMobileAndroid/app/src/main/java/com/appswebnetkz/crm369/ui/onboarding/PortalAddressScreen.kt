package com.appswebnetkz.crm369.ui.onboarding

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.portal.DomainNormalizer
import com.appswebnetkz.crm369.core.session.RecentPortal
import com.appswebnetkz.crm369.core.util.describeError
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.PortalLogo
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.navigation.AppRoutes
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

/**
 * First screen: the user enters the address of the CRM369 portal (like the Bitrix24 portal
 * address). The address is validated with `GET /api/mobile/v1/portal`.
 */
@Composable
fun PortalAddressScreen() {
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val context = LocalContext.current
    val focusManager = LocalFocusManager.current
    val scope = rememberCoroutineScope()
    val accounts by container.session.accounts.collectAsStateWithLifecycle()

    var address by rememberSaveable { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    var recent by remember { mutableStateOf(container.session.recentPortals()) }

    fun connect(value: String) {
        if (busy) return
        if (DomainNormalizer.normalize(value) == null) {
            error = context.getString(R.string.portal_error_invalid_address)
            return
        }
        focusManager.clearFocus()
        busy = true
        error = null
        scope.launch {
            try {
                val portal = container.session.discover(value)
                val email = container.session.emailFor(portal.baseUrl)
                navigator.open(AppRoutes.login(portal.baseUrl, email))
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                error = describeError(context, exception)
            } finally {
                busy = false
            }
        }
    }

    Scaffold { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .imePadding()
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            if (accounts.isNotEmpty() && navigator.canGoBack()) {
                Column(modifier = Modifier.fillMaxWidth()) {
                    IconButton(onClick = { navigator.back() }) {
                        Icon(AppIcons.Back, contentDescription = stringResource(R.string.action_back))
                    }
                }
            } else {
                Spacer(Modifier.height(48.dp))
            }
            Column(
                modifier = Modifier
                    .widthIn(max = 480.dp)
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                Image(
                    painter = painterResource(R.drawable.crm369_logo),
                    contentDescription = null,
                    modifier = Modifier.size(120.dp),
                )
                Text(
                    text = stringResource(R.string.portal_title),
                    style = MaterialTheme.typography.headlineSmall,
                    textAlign = TextAlign.Center,
                )
                Text(
                    text = stringResource(R.string.portal_subtitle),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                )
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(
                    value = address,
                    onValueChange = {
                        address = it
                        error = null
                    },
                    label = { Text(stringResource(R.string.portal_address_label)) },
                    placeholder = { Text(stringResource(R.string.portal_address_placeholder)) },
                    leadingIcon = { Icon(AppIcons.Public, contentDescription = null) },
                    singleLine = true,
                    enabled = !busy,
                    isError = error != null,
                    supportingText = {
                        Text(error ?: stringResource(R.string.portal_address_hint))
                    },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri, imeAction = ImeAction.Go),
                    keyboardActions = KeyboardActions(onGo = { connect(address) }),
                    modifier = Modifier.fillMaxWidth(),
                )
                Button(
                    onClick = { connect(address) },
                    enabled = !busy && address.isNotBlank(),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp),
                ) {
                    if (busy) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(20.dp),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onPrimary,
                        )
                    } else {
                        Text(stringResource(R.string.portal_connect))
                    }
                }
            }

            if (recent.isNotEmpty()) {
                Column(
                    modifier = Modifier
                        .widthIn(max = 480.dp)
                        .fillMaxWidth()
                        .padding(top = 16.dp),
                ) {
                    SectionHeader(title = stringResource(R.string.portal_recent))
                    recent.forEach { portal ->
                        RecentPortalRow(
                            portal = portal,
                            enabled = !busy,
                            onClick = {
                                address = portal.baseUrl
                                connect(portal.baseUrl)
                            },
                            onForget = {
                                container.session.forgetPortal(portal.baseUrl)
                                recent = container.session.recentPortals()
                            },
                        )
                    }
                }
            }

            if (accounts.isNotEmpty()) {
                TextButton(
                    onClick = { navigator.open(AppRoutes.ACCOUNTS) },
                    modifier = Modifier.padding(16.dp),
                ) {
                    Text(stringResource(R.string.accounts_title))
                }
            }
            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun RecentPortalRow(
    portal: RecentPortal,
    enabled: Boolean,
    onClick: () -> Unit,
    onForget: () -> Unit,
) {
    ListRow(
        title = portal.name,
        subtitle = DomainNormalizer.hostOf(portal.baseUrl),
        leading = { PortalLogo(name = portal.name, logoUrl = portal.logoUrl, size = 40.dp) },
        trailing = {
            IconButton(onClick = onForget, enabled = enabled) {
                Icon(AppIcons.Close, contentDescription = stringResource(R.string.portal_forget))
            }
        },
        onClick = if (enabled) onClick else null,
    )
}
