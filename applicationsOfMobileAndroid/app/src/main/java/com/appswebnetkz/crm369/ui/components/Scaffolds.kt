package com.appswebnetkz.crm369.ui.components

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.ScaffoldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.theme.AppIcons

/**
 * True while a screen is shown as a tab of the main screen (the bottom navigation bar of the
 * main screen already handles the bottom system inset, and there is no back button).
 */
val LocalInTabHost = compositionLocalOf { false }

/** Top app bar used by every screen. [showBack] shows a back arrow that pops the back stack. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CrmTopBar(
    title: String,
    subtitle: String? = null,
    showBack: Boolean = true,
    onBack: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
) {
    val navigator = LocalNavigator.current
    TopAppBar(
        title = {
            Column {
                Text(
                    text = title,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    style = MaterialTheme.typography.titleLarge,
                )
                if (!subtitle.isNullOrBlank()) {
                    Text(
                        text = subtitle,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        },
        navigationIcon = {
            if (showBack && (onBack != null || navigator.canGoBack())) {
                IconButton(onClick = { if (onBack != null) onBack() else navigator.back() }) {
                    Icon(AppIcons.Back, contentDescription = stringResource(R.string.action_back))
                }
            }
        },
        actions = actions,
        colors = TopAppBarDefaults.topAppBarColors(
            containerColor = MaterialTheme.colorScheme.surface,
            scrolledContainerColor = MaterialTheme.colorScheme.surfaceContainer,
        ),
    )
}

/**
 * Standard screen layout: [CrmTopBar] + content. Inside the main screen tabs ([LocalInTabHost])
 * the back button is hidden and the bottom inset is left to the bottom navigation bar.
 */
@Composable
fun CrmScaffold(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    showBack: Boolean = !LocalInTabHost.current,
    onBack: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
    floatingActionButton: @Composable () -> Unit = {},
    bottomBar: @Composable () -> Unit = {},
    content: @Composable (PaddingValues) -> Unit,
) {
    val inTabHost = LocalInTabHost.current
    Scaffold(
        modifier = modifier,
        topBar = {
            CrmTopBar(
                title = title,
                subtitle = subtitle,
                showBack = showBack,
                onBack = onBack,
                actions = actions,
            )
        },
        bottomBar = bottomBar,
        floatingActionButton = floatingActionButton,
        contentWindowInsets = if (inTabHost) WindowInsets(0.dp, 0.dp, 0.dp, 0.dp) else ScaffoldDefaults.contentWindowInsets,
        content = content,
    )
}

/** Shortcut for the common "content padded by the scaffold" pattern. */
fun Modifier.scaffoldPadding(padding: PaddingValues): Modifier = this.padding(padding)
