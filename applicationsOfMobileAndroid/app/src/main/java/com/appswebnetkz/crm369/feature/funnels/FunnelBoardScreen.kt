package com.appswebnetkz.crm369.feature.funnels

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ElevatedCard
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.ScrollableTabRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Tab
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.OptionPickerDialog
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors
import kotlinx.coroutines.launch

/**
 * Kanban board of a funnel: stats, stages as tabs + pager with deal cards, funnel switcher and
 * moving deals between stages. [funnelId] = null shows the first visible funnel (CRM tab).
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FunnelBoardScreen(funnelId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { FunnelsRepository(api) }
    val runner = rememberActionRunner()
    var selectedFunnelId by rememberSaveable { mutableStateOf(funnelId) }
    val page = rememberLoadable(api, selectedFunnelId) { repository.page(selectedFunnelId) }
    var showSwitcher by remember { mutableStateOf(false) }
    var movingDeal by remember { mutableStateOf<Deal?>(null) }
    val movedMessage = stringResource(R.string.funnels_deal_moved)

    val data = page.data
    val board = data?.activeFunnel
    val canManage = data?.canManage == true

    CrmScaffold(
        title = board?.name ?: stringResource(R.string.module_funnels),
        subtitle = if (board != null && !board.isActive) stringResource(R.string.funnels_inactive) else null,
        actions = {
            if (data != null && data.funnels.isNotEmpty()) {
                IconButton(onClick = { showSwitcher = true }) {
                    Icon(AppIcons.Funnels, contentDescription = stringResource(R.string.funnels_choose_funnel))
                }
            }
            if (canManage && board != null) {
                IconButton(onClick = { navigator.open(FunnelsRoutes.settings(board.id)) }) {
                    Icon(AppIcons.Settings, contentDescription = stringResource(R.string.funnels_settings))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = page,
            modifier = Modifier.padding(padding),
            isEmpty = { loaded -> loaded.activeFunnel == null },
            empty = {
                EmptyState(
                    title = stringResource(R.string.funnels_no_funnels_title),
                    message = stringResource(if (canManage) R.string.funnels_no_funnels_manage else R.string.funnels_no_funnels_ask),
                    icon = AppIcons.Funnels,
                    actionLabel = if (canManage) stringResource(R.string.funnels_create_funnel) else null,
                    onAction = if (canManage) {
                        { navigator.open(FunnelsRoutes.NEW_FUNNEL) }
                    } else {
                        null
                    },
                )
            },
        ) { loaded ->
            val activeBoard = loaded.activeFunnel
            if (activeBoard != null) {
                FunnelBoardContent(
                    board = activeBoard,
                    canCreateDeals = loaded.canCreateDeals,
                    onOpenDeal = { deal -> navigator.open(FunnelsRoutes.deal(activeBoard.id, deal.id)) },
                    onMoveDeal = { deal -> movingDeal = deal },
                    onCreateDeal = { stageId -> navigator.open(FunnelsRoutes.newDeal(activeBoard.id, stageId)) },
                )
            }
        }
    }

    val moving = movingDeal
    if (moving != null && board != null) {
        OptionPickerDialog(
            title = stringResource(R.string.funnels_move_title),
            options = board.stages.map { stage ->
                Option(value = stage.id.toString(), label = stage.name, subtitle = stageTypeLabel(stage.type, data?.stageTypes.orEmpty()))
            },
            selected = moving.stageId.toString(),
            allowClear = false,
            onDismiss = { movingDeal = null },
            onSelect = { option ->
                val stageId = option?.value?.toLongOrNull()
                if (stageId != null && stageId != moving.stageId) {
                    runner.execute(successMessage = movedMessage, onSuccess = { _: Deal -> page.refresh() }) {
                        repository.moveDeal(board.id, moving.id, stageId)
                    }
                }
            },
        )
    }

    if (showSwitcher && data != null) {
        ModalBottomSheet(onDismissRequest = { showSwitcher = false }) {
            FunnelSwitcher(
                page = data,
                selectedId = board?.id,
                onSelect = { summary ->
                    showSwitcher = false
                    if (summary.id != board?.id) selectedFunnelId = summary.id
                },
                onCreate = {
                    showSwitcher = false
                    navigator.open(FunnelsRoutes.NEW_FUNNEL)
                },
            )
        }
    }
}

/** Funnel list of the switcher sheet: color, name, deals count, amount, inactive badge. */
@Composable
private fun FunnelSwitcher(
    page: FunnelPage,
    selectedId: Long?,
    onSelect: (FunnelSummary) -> Unit,
    onCreate: () -> Unit,
) {
    Text(
        text = stringResource(R.string.funnels_choose_funnel),
        style = MaterialTheme.typography.titleMedium,
        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
    )
    LazyColumn(modifier = Modifier.fillMaxWidth()) {
        items(page.funnels, key = { it.id }) { funnel ->
            ListRow(
                title = funnel.name,
                subtitle = stringResource(
                    R.string.funnels_deals_summary,
                    funnel.dealsCount,
                    Formatters.number(funnel.dealsAmountSum),
                ),
                leading = { ColorDot(color = funnel.color, size = 14.dp) },
                trailing = {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        if (!funnel.isActive) {
                            StatusChip(text = stringResource(R.string.funnels_inactive), color = MaterialTheme.colorScheme.outline)
                        }
                        if (funnel.id == selectedId) {
                            Icon(AppIcons.Check, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        }
                    }
                },
                onClick = { onSelect(funnel) },
            )
        }
        if (page.canManage) {
            item(key = "create-funnel") {
                ListRow(
                    title = stringResource(R.string.funnels_create_funnel),
                    leading = { Icon(AppIcons.Add, contentDescription = null) },
                    onClick = onCreate,
                )
            }
        }
        item(key = "bottom-space") { Spacer(Modifier.height(24.dp)) }
    }
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
private fun FunnelBoardContent(
    board: FunnelBoard,
    canCreateDeals: Boolean,
    onOpenDeal: (Deal) -> Unit,
    onMoveDeal: (Deal) -> Unit,
    onCreateDeal: (Long?) -> Unit,
) {
    val stages = board.stages
    val stageCount by rememberUpdatedState(stages.size)
    val pagerState = key(board.id) {
        rememberPagerState(initialPage = 0, initialPageOffsetFraction = 0f, pageCount = { stageCount })
    }
    val scope = rememberCoroutineScope()

    Box(modifier = Modifier.fillMaxSize()) {
        Column(modifier = Modifier.fillMaxSize()) {
            BoardStats(stats = board.stats)
            if (stages.isEmpty()) {
                EmptyState(
                    title = stringResource(R.string.funnels_no_stages),
                    icon = FunnelsIcons.Stages,
                    modifier = Modifier.weight(1f),
                )
            } else {
                val selectedIndex = pagerState.currentPage.coerceIn(0, stages.lastIndex)
                ScrollableTabRow(selectedTabIndex = selectedIndex, edgePadding = 8.dp) {
                    stages.forEachIndexed { index, stage ->
                        Tab(
                            selected = index == selectedIndex,
                            onClick = { scope.launch { pagerState.animateScrollToPage(index) } },
                            text = { StageTabLabel(stage = stage) },
                        )
                    }
                }
                HorizontalPager(
                    state = pagerState,
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth(),
                    verticalAlignment = Alignment.Top,
                ) { pageIndex ->
                    val stage = stages.getOrNull(pageIndex)
                    if (stage != null) {
                        StageDeals(
                            stage = stage,
                            bottomPadding = if (canCreateDeals) 96.dp else 16.dp,
                            onOpenDeal = onOpenDeal,
                            onMoveDeal = onMoveDeal,
                        )
                    }
                }
            }
        }
        if (canCreateDeals && stages.isNotEmpty()) {
            ExtendedFloatingActionButton(
                text = { Text(stringResource(R.string.funnels_new_deal)) },
                icon = { Icon(AppIcons.Add, contentDescription = null) },
                onClick = { onCreateDeal(stages.getOrNull(pagerState.currentPage)?.id) },
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .padding(16.dp),
            )
        }
    }
}

@Composable
private fun BoardStats(stats: FunnelStats) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 12.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        StatTile(
            label = stringResource(R.string.funnels_stats_deals),
            value = Formatters.number(stats.dealsCount.toLong()),
        )
        StatTile(
            label = stringResource(R.string.funnels_stats_active),
            value = Formatters.number(stats.activeDealsCount.toLong()),
            color = MaterialTheme.colorScheme.primary,
        )
        StatTile(
            label = stringResource(R.string.funnels_stats_won),
            value = Formatters.number(stats.wonDealsCount.toLong()),
            color = LocalExtendedColors.current.success,
        )
        StatTile(
            label = stringResource(R.string.funnels_stats_lost),
            value = Formatters.number(stats.lostDealsCount.toLong()),
            color = MaterialTheme.colorScheme.error,
        )
        StatTile(
            label = stringResource(R.string.funnels_stats_amount),
            value = Formatters.number(stats.amountSum),
        )
    }
}

@Composable
private fun StatTile(label: String, value: String, color: Color = MaterialTheme.colorScheme.onSurface) {
    Surface(shape = MaterialTheme.shapes.medium, color = MaterialTheme.colorScheme.surfaceContainerHigh) {
        Column(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
            Text(
                text = value,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                color = color,
                maxLines = 1,
            )
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
            )
        }
    }
}

@Composable
private fun StageTabLabel(stage: BoardStage) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        ColorDot(color = stage.color)
        Text(text = stage.name, maxLines = 1, overflow = TextOverflow.Ellipsis)
        Text(
            text = stage.dealsCount.toString(),
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun StageDeals(
    stage: BoardStage,
    bottomPadding: Dp,
    onOpenDeal: (Deal) -> Unit,
    onMoveDeal: (Deal) -> Unit,
) {
    if (stage.deals.isEmpty()) {
        EmptyState(title = stringResource(R.string.funnels_no_stage_deals), icon = AppIcons.Inbox)
    } else {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = 12.dp, top = 12.dp, end = 12.dp, bottom = bottomPadding),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            items(stage.deals, key = { it.id }) { deal ->
                DealCard(deal = deal, onClick = { onOpenDeal(deal) }, onMove = { onMoveDeal(deal) })
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DealCard(deal: Deal, onClick: () -> Unit, onMove: () -> Unit) {
    var menuOpen by remember { mutableStateOf(false) }
    ElevatedCard(onClick = onClick, modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(start = 14.dp, top = 4.dp, end = 4.dp, bottom = 12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = deal.title,
                    style = MaterialTheme.typography.titleSmall,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f),
                )
                Box {
                    IconButton(onClick = { menuOpen = true }) {
                        Icon(AppIcons.More, contentDescription = stringResource(R.string.funnels_deal_menu))
                    }
                    DropdownMenu(expanded = menuOpen, onDismissRequest = { menuOpen = false }) {
                        DropdownMenuItem(
                            text = { Text(stringResource(R.string.funnels_open_deal)) },
                            onClick = {
                                menuOpen = false
                                onClick()
                            },
                            leadingIcon = { Icon(AppIcons.Info, contentDescription = null) },
                        )
                        DropdownMenuItem(
                            text = { Text(stringResource(R.string.funnels_move_to_stage)) },
                            onClick = {
                                menuOpen = false
                                onMove()
                            },
                            leadingIcon = { Icon(FunnelsIcons.Move, contentDescription = null) },
                        )
                    }
                }
            }
            val amount = deal.amount
            if (amount != null) {
                Text(
                    text = Formatters.money(amount, deal.currency),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
            val counterparty = deal.counterparty
            if (counterparty != null) {
                CardLine(icon = AppIcons.Company, text = counterparty)
            }
            val responsible = deal.responsible
            val closeDate = Formatters.date(deal.expectedCloseAt)
            if (responsible != null || closeDate.isNotBlank()) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 6.dp, end = 10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    if (closeDate.isNotBlank()) {
                        CardLine(icon = AppIcons.Calendar, text = closeDate, modifier = Modifier.weight(1f))
                    } else {
                        Spacer(Modifier.weight(1f))
                    }
                    if (responsible != null) {
                        Avatar(name = responsible.fullName, imageUrl = null, size = 26.dp)
                    }
                }
            }
        }
    }
}

@Composable
private fun CardLine(icon: ImageVector, text: String, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier.padding(top = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Icon(
            icon,
            contentDescription = null,
            modifier = Modifier.size(16.dp),
            tint = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = text,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}
