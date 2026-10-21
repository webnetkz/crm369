package com.appswebnetkz.crm369.ui.main

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.links.DeepLinkRouter
import com.appswebnetkz.crm369.core.links.DeepLinkTarget
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.PortalLogo
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.navigation.AppRoutes
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.ModuleRegistry
import com.appswebnetkz.crm369.ui.navigation.rememberActiveAccount
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.launch

/** "Menu" tab: every module the user can open, knowledge bases, custom links and administration. */
@Composable
fun MenuScreen() {
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val scope = rememberCoroutineScope()
    val bootstrap = rememberBootstrap()
    val account = rememberActiveAccount()
    var confirmLogout by remember { mutableStateOf(false) }

    val modules = ModuleRegistry.visibleModules(bootstrap)
    val admin = ModuleRegistry.visibleAdmin(bootstrap)

    CrmScaffold(
        title = stringResource(R.string.menu_title),
        actions = {
            IconButton(onClick = { navigator.open(AppRoutes.SETTINGS) }) {
                Icon(AppIcons.Settings, contentDescription = stringResource(R.string.settings_title))
            }
        },
    ) { padding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            item(key = "account") {
                InfoCard(onClick = { navigator.open(AppRoutes.PROFILE) }) {
                    ListRow(
                        title = bootstrap?.user?.fullName ?: account?.userName.orEmpty(),
                        subtitle = listOfNotNull(
                            bootstrap?.user?.position,
                            account?.email,
                        ).filter { it.isNotBlank() }.joinToString(" · "),
                        leading = {
                            Avatar(
                                name = bootstrap?.user?.fullName ?: account?.userName,
                                imageUrl = bootstrap?.user?.avatarUrl ?: account?.avatarUrl,
                                size = 48.dp,
                            )
                        },
                        trailing = { Icon(AppIcons.ChevronRight, contentDescription = null) },
                    )
                }
            }
            item(key = "portal") {
                InfoCard(onClick = { navigator.open(AppRoutes.ACCOUNTS) }) {
                    ListRow(
                        title = bootstrap?.portalName ?: account?.portalName.orEmpty(),
                        subtitle = account?.host,
                        leading = {
                            PortalLogo(
                                name = bootstrap?.portalName ?: account?.portalName.orEmpty(),
                                logoUrl = bootstrap?.portalLogoUrl ?: account?.logoUrl,
                                size = 40.dp,
                            )
                        },
                        trailing = { Icon(AppIcons.Sync, contentDescription = stringResource(R.string.menu_switch_portal)) },
                    )
                }
            }

            if (modules.isNotEmpty()) {
                item(key = "modules-title") { SectionHeader(title = stringResource(R.string.menu_modules)) }
                modules.chunked(4).forEachIndexed { index, row ->
                    item(key = "modules-$index") {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 8.dp),
                            horizontalArrangement = Arrangement.SpaceEvenly,
                        ) {
                            row.forEach { entry ->
                                ModuleTile(
                                    icon = entry.icon,
                                    title = stringResource(entry.titleRes),
                                    onClick = {
                                        openModuleEntry(navigator, entry) { tab -> navigator.resetToHome(tab) }
                                    },
                                    modifier = Modifier.weight(1f),
                                )
                            }
                            repeat(4 - row.size) { Spacer(Modifier.weight(1f)) }
                        }
                    }
                }
            }

            val knowledgeBases = bootstrap?.menu?.knowledgeBases.orEmpty()
            if (knowledgeBases.isNotEmpty()) {
                item(key = "kb-title") { SectionHeader(title = stringResource(R.string.module_knowledge_bases)) }
                knowledgeBases.forEach { base ->
                    item(key = "kb-${base.id}") {
                        ListRow(
                            title = base.title,
                            leading = { Icon(AppIcons.KnowledgeBases, contentDescription = null) },
                            onClick = {
                                if (!navigator.open("knowledge/${base.id}")) {
                                    navigator.openWeb("/knowledge-bases/${base.id}", base.title)
                                }
                            },
                        )
                    }
                }
            }

            val customItems = bootstrap?.menu?.customItems.orEmpty()
            if (customItems.isNotEmpty()) {
                item(key = "custom-title") { SectionHeader(title = stringResource(R.string.menu_links)) }
                customItems.forEachIndexed { index, link ->
                    item(key = "custom-$index-${link.id}") {
                        ListRow(
                            title = link.title,
                            subtitle = link.url,
                            maxSubtitleLines = 1,
                            leading = { Icon(AppIcons.Link, contentDescription = null) },
                            onClick = {
                                when (val target = DeepLinkRouter.resolve(link.url, account?.baseUrl)) {
                                    is DeepLinkTarget.External -> navigator.openUrl(target.url)
                                    else -> navigator.openDeepLink(link.url, link.title)
                                }
                            },
                        )
                    }
                }
            }

            if (admin.isNotEmpty()) {
                item(key = "admin-title") { SectionHeader(title = stringResource(R.string.menu_admin)) }
                admin.forEach { entry ->
                    item(key = "admin-${entry.key}") {
                        val title = stringResource(entry.titleRes)
                        ListRow(
                            title = title,
                            leading = { Icon(entry.icon, contentDescription = null) },
                            trailing = { Icon(AppIcons.Public, contentDescription = null) },
                            onClick = { navigator.openWeb(entry.webPath, title) },
                        )
                    }
                }
            }

            item(key = "settings-title") { SectionHeader(title = stringResource(R.string.menu_application)) }
            item(key = "notifications") {
                ListRow(
                    title = stringResource(R.string.notifications_title),
                    leading = { Icon(AppIcons.Notifications, contentDescription = null) },
                    onClick = { navigator.open(AppRoutes.NOTIFICATIONS) },
                )
            }
            item(key = "settings") {
                ListRow(
                    title = stringResource(R.string.settings_title),
                    leading = { Icon(AppIcons.Settings, contentDescription = null) },
                    onClick = { navigator.open(AppRoutes.SETTINGS) },
                )
            }
            item(key = "web") {
                ListRow(
                    title = stringResource(R.string.menu_full_version),
                    subtitle = stringResource(R.string.menu_full_version_hint),
                    leading = { Icon(AppIcons.Public, contentDescription = null) },
                    onClick = { navigator.openWeb("/dashboard", null) },
                )
            }
            item(key = "logout") {
                RowDivider()
                ListRow(
                    title = stringResource(R.string.profile_logout),
                    leading = { Icon(AppIcons.Logout, contentDescription = null) },
                    onClick = { confirmLogout = true },
                )
                Spacer(Modifier.height(24.dp))
            }
        }
    }

    if (confirmLogout) {
        ConfirmDialog(
            title = stringResource(R.string.profile_logout_title),
            text = stringResource(R.string.profile_logout_text),
            confirmLabel = stringResource(R.string.profile_logout),
            destructive = true,
            onDismiss = { confirmLogout = false },
            onConfirm = {
                scope.launch {
                    container.session.logout()
                    if (container.session.activeAccount.value == null) {
                        navigator.resetToPortal()
                    } else {
                        navigator.resetToHome()
                    }
                }
            },
        )
    }
}
