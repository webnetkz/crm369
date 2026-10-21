package com.appswebnetkz.crm369.feature.funnels

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.AppRoutes
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/**
 * Funnel management (`can.manageFunnels`): funnel settings and custom fields overview, stages
 * (add / edit / delete) and deleting the funnel. Without the ability the screen is read-only.
 */
@Composable
fun FunnelSettingsScreen(funnelId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { FunnelsRepository(api) }
    val runner = rememberActionRunner()
    val page = rememberLoadable(api, funnelId) { repository.page(funnelId) }
    var confirmDeleteFunnel by remember { mutableStateOf(false) }
    var stageToDelete by remember { mutableStateOf<BoardStage?>(null) }
    val funnelDeletedMessage = stringResource(R.string.funnels_funnel_deleted)
    val stageDeletedMessage = stringResource(R.string.funnels_stage_deleted)

    val data = page.data
    val board = data?.activeFunnel
    val canManage = data?.canManage == true && board != null

    CrmScaffold(
        title = stringResource(R.string.funnels_settings),
        subtitle = board?.name,
        actions = {
            if (canManage) {
                IconButton(onClick = { navigator.open(FunnelsRoutes.editFunnel(funnelId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.funnels_edit_funnel))
                }
                IconButton(onClick = { confirmDeleteFunnel = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.funnels_delete_funnel))
                }
            }
        },
        floatingActionButton = {
            if (canManage) {
                ExtendedFloatingActionButton(
                    text = { Text(stringResource(R.string.funnels_create_stage)) },
                    icon = { Icon(AppIcons.Add, contentDescription = null) },
                    onClick = { navigator.open(FunnelsRoutes.newStage(funnelId)) },
                )
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = page,
            modifier = Modifier.padding(padding),
            isEmpty = { loaded -> loaded.activeFunnel == null },
            empty = { EmptyState(title = stringResource(R.string.funnels_no_funnels_title), icon = AppIcons.Funnels) },
        ) { loaded ->
            val activeBoard = loaded.activeFunnel
            if (activeBoard != null) {
                FunnelSettingsContent(
                    board = activeBoard,
                    page = loaded,
                    canManage = loaded.canManage,
                    onEditStage = { stage -> navigator.open(FunnelsRoutes.editStage(funnelId, stage.id)) },
                    onDeleteStage = { stage -> stageToDelete = stage },
                )
            }
        }
    }

    if (confirmDeleteFunnel) {
        ConfirmDialog(
            title = stringResource(R.string.funnels_delete_funnel),
            text = stringResource(R.string.funnels_delete_funnel_text, board?.name.orEmpty()),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDeleteFunnel = false },
            onConfirm = {
                runner.execute(
                    successMessage = funnelDeletedMessage,
                    onSuccess = { _: Unit -> navigator.resetToHome(AppRoutes.TAB_CRM) },
                ) { repository.deleteFunnel(funnelId) }
            },
        )
    }

    val deletingStage = stageToDelete
    if (deletingStage != null) {
        ConfirmDialog(
            title = stringResource(R.string.funnels_delete_stage),
            text = stringResource(R.string.funnels_delete_stage_text, deletingStage.name),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { stageToDelete = null },
            onConfirm = {
                // A stage with deals answers 422; the runner shows the server message (errors.stage).
                runner.execute(successMessage = stageDeletedMessage, onSuccess = { _: Unit -> page.refresh() }) {
                    repository.deleteStage(funnelId, deletingStage.id)
                }
            },
        )
    }
}

@Composable
private fun FunnelSettingsContent(
    board: FunnelBoard,
    page: FunnelPage,
    canManage: Boolean,
    onEditStage: (BoardStage) -> Unit,
    onDeleteStage: (BoardStage) -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
    ) {
        InfoCard(title = stringResource(R.string.funnels_section_funnel)) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                ColorDot(color = board.color, size = 20.dp)
                Column(modifier = Modifier.weight(1f)) {
                    Text(text = board.name, style = MaterialTheme.typography.titleMedium)
                    val slug = board.slug
                    if (slug != null) {
                        Text(
                            text = slug,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                StatusChip(
                    text = stringResource(if (board.isActive) R.string.funnels_active_status else R.string.funnels_inactive),
                    color = if (board.isActive) LocalExtendedColors.current.success else MaterialTheme.colorScheme.outline,
                )
            }
            KeyValueRow(label = stringResource(R.string.funnels_description), value = board.description, icon = AppIcons.Info)
            KeyValueRow(
                label = stringResource(R.string.funnels_access_groups),
                value = board.groups.joinToString(", ") { it.name }.ifBlank { stringResource(R.string.funnels_groups_none) },
                icon = AppIcons.Group,
            )
            KeyValueRow(
                label = stringResource(R.string.funnels_stats_deals),
                value = board.stats.dealsCount.toString(),
                icon = AppIcons.Funnels,
            )
        }

        InfoCard(title = stringResource(R.string.funnels_deal_fields)) {
            if (board.dealFields.isEmpty()) {
                Text(
                    text = stringResource(R.string.funnels_no_custom_fields),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            }
            val requiredLabel = stringResource(R.string.funnels_field_required)
            board.dealFields.forEach { field ->
                ListRow(
                    title = field.label,
                    subtitle = listOfNotNull(
                        fieldTypeLabel(field.type, page.fieldTypes),
                        field.key,
                        if (field.isRequired) requiredLabel else null,
                    ).joinToString(" · "),
                )
            }
        }

        SectionHeader(title = stringResource(R.string.funnels_stages))
        if (board.stages.isEmpty()) {
            Text(
                text = stringResource(R.string.funnels_no_stages),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            )
        }
        board.stages.forEach { stage ->
            ListRow(
                title = stage.name,
                subtitle = stringResource(
                    R.string.funnels_stage_summary,
                    stageTypeLabel(stage.type, page.stageTypes),
                    stage.sortOrder,
                    stage.dealsCount,
                ),
                leading = { ColorDot(color = stage.color, size = 14.dp) },
                trailing = if (canManage) {
                    {
                        Row {
                            IconButton(onClick = { onEditStage(stage) }) {
                                Icon(AppIcons.Edit, contentDescription = stringResource(R.string.funnels_edit_stage))
                            }
                            IconButton(onClick = { onDeleteStage(stage) }) {
                                Icon(AppIcons.Delete, contentDescription = stringResource(R.string.funnels_delete_stage))
                            }
                        }
                    }
                } else {
                    null
                },
                onClick = if (canManage) {
                    { onEditStage(stage) }
                } else {
                    null
                },
            )
            RowDivider()
        }
        Spacer(Modifier.height(96.dp))
    }
}
