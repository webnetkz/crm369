package com.appswebnetkz.crm369.feature.funnels

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AssistChip
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.ChipRow
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Deal card: stage switcher, amount, counterparty, contacts, custom fields, history; edit / delete. */
@Composable
fun DealDetailScreen(funnelId: Long, dealId: Long) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { FunnelsRepository(api) }
    val runner = rememberActionRunner()
    val page = rememberLoadable(api, funnelId, dealId) { repository.deal(funnelId, dealId) }
    var confirmDelete by remember { mutableStateOf(false) }
    val deletedMessage = stringResource(R.string.funnels_deal_deleted)
    val movedMessage = stringResource(R.string.funnels_deal_moved)
    val data = page.data

    CrmScaffold(
        title = data?.deal?.title ?: stringResource(R.string.funnels_deal),
        subtitle = data?.funnel?.name,
        actions = {
            if (data != null) {
                IconButton(onClick = { navigator.open(FunnelsRoutes.editDeal(funnelId, dealId)) }) {
                    Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
                }
                IconButton(onClick = { confirmDelete = true }, enabled = !runner.isBusy) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.action_delete))
                }
            }
        },
    ) { padding ->
        LoadableContent(loadable = page, modifier = Modifier.padding(padding)) { loaded ->
            DealDetailContent(
                data = loaded,
                busy = runner.isBusy,
                onMove = { stage ->
                    runner.execute(
                        successMessage = movedMessage,
                        onSuccess = { updated: Deal -> page.update { current -> current.copy(deal = updated) } },
                    ) { repository.moveDeal(funnelId, dealId, stage.id) }
                },
            )
        }
    }

    if (confirmDelete) {
        ConfirmDialog(
            title = stringResource(R.string.funnels_delete_deal),
            text = stringResource(R.string.funnels_delete_deal_text, data?.deal?.title.orEmpty()),
            confirmLabel = stringResource(R.string.action_delete),
            destructive = true,
            onDismiss = { confirmDelete = false },
            onConfirm = {
                runner.execute(successMessage = deletedMessage, onSuccess = { _: Unit -> navigator.back() }) {
                    repository.deleteDeal(funnelId, dealId)
                }
            },
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DealDetailContent(data: DealPage, busy: Boolean, onMove: (StageRef) -> Unit) {
    val navigator = LocalNavigator.current
    val deal = data.deal
    val currentStage = data.stages.firstOrNull { it.id == deal.stageId } ?: deal.stage

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 16.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            Text(text = deal.title, style = MaterialTheme.typography.headlineSmall)
            val amount = deal.amount
            if (amount != null) {
                Text(
                    text = Formatters.money(amount, deal.currency),
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
            if (currentStage != null) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    StatusChip(text = currentStage.name, color = serverColor(currentStage.color))
                    StatusChip(text = stageTypeLabel(currentStage.type), color = stageTypeColor(currentStage.type))
                }
            }
        }

        if (data.stages.isNotEmpty()) {
            SectionHeader(title = stringResource(R.string.funnels_stage))
            ChipRow {
                data.stages.forEach { stage ->
                    FilterChip(
                        selected = stage.id == deal.stageId,
                        onClick = { if (stage.id != deal.stageId && !busy) onMove(stage) },
                        label = { Text(stage.name) },
                        leadingIcon = { ColorDot(color = stage.color) },
                    )
                }
            }
        }

        DealQuickActions(deal = deal)

        InfoCard(title = stringResource(R.string.funnels_section_main)) {
            KeyValueRow(
                label = stringResource(R.string.funnels_amount),
                value = deal.amount?.let { Formatters.money(it, deal.currency) },
                icon = AppIcons.Money,
            )
            KeyValueRow(label = stringResource(R.string.funnels_company_name), value = deal.companyName, icon = AppIcons.Company)
            KeyValueRow(
                label = stringResource(R.string.funnels_responsible_user),
                value = deal.responsible?.fullName ?: stringResource(R.string.funnels_no_responsible_user),
                icon = AppIcons.Person,
            )
            KeyValueRow(
                label = stringResource(R.string.funnels_expected_close_at),
                value = Formatters.date(deal.expectedCloseAt).ifBlank { null },
                icon = AppIcons.Calendar,
            )
        }

        if (deal.contactName != null || deal.contactPhone != null || deal.contactEmail != null) {
            InfoCard(title = stringResource(R.string.funnels_section_contact)) {
                KeyValueRow(label = stringResource(R.string.funnels_contact_name), value = deal.contactName, icon = AppIcons.Person)
                KeyValueRow(
                    label = stringResource(R.string.funnels_contact_phone),
                    value = deal.contactPhone,
                    icon = AppIcons.Phone,
                    onClick = deal.contactPhone?.let { phone -> { navigator.dial(phone) } },
                )
                KeyValueRow(
                    label = stringResource(R.string.funnels_contact_email),
                    value = deal.contactEmail,
                    icon = AppIcons.Email,
                    onClick = deal.contactEmail?.let { email -> { navigator.email(email) } },
                )
            }
        }

        val description = deal.description
        if (description != null) {
            InfoCard(title = stringResource(R.string.funnels_deal_description)) {
                Text(
                    text = description,
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            }
        }

        val customValues = data.dealFields.mapNotNull { field ->
            customFieldText(field, deal.customFields[field.key])?.let { value -> field to value }
        }
        if (customValues.isNotEmpty()) {
            InfoCard(title = stringResource(R.string.funnels_custom_fields)) {
                customValues.forEach { (field, value) ->
                    KeyValueRow(label = field.label, value = value)
                }
            }
        }

        InfoCard {
            KeyValueRow(
                label = stringResource(R.string.funnels_created),
                value = listOfNotNull(Formatters.dateTime(deal.createdAt).ifBlank { null }, deal.creator?.fullName)
                    .joinToString(" · "),
                icon = AppIcons.Time,
            )
            KeyValueRow(
                label = stringResource(R.string.funnels_updated),
                value = listOfNotNull(Formatters.dateTime(deal.updatedAt).ifBlank { null }, deal.updater?.fullName)
                    .joinToString(" · "),
                icon = AppIcons.History,
            )
        }
        Spacer(Modifier.height(24.dp))
    }
}

/** Call / WhatsApp / e-mail shortcuts for the deal contact. */
@Composable
private fun DealQuickActions(deal: Deal) {
    val navigator = LocalNavigator.current
    val phone = deal.contactPhone
    val email = deal.contactEmail
    if (phone == null && email == null) return
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 16.dp, vertical = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        if (phone != null) {
            AssistChip(
                onClick = { navigator.dial(phone) },
                label = { Text(stringResource(R.string.funnels_call)) },
                leadingIcon = { Icon(AppIcons.Call, contentDescription = null, modifier = Modifier.size(18.dp)) },
            )
            AssistChip(
                onClick = { navigator.whatsApp(phone) },
                label = { Text(stringResource(R.string.funnels_whatsapp)) },
                leadingIcon = { Icon(AppIcons.Chat, contentDescription = null, modifier = Modifier.size(18.dp)) },
            )
        }
        if (email != null) {
            AssistChip(
                onClick = { navigator.email(email) },
                label = { Text(stringResource(R.string.funnels_write_email)) },
                leadingIcon = { Icon(AppIcons.Email, contentDescription = null, modifier = Modifier.size(18.dp)) },
            )
        }
    }
}

/** Display text of a custom field value (dates and numbers formatted), null when empty. */
private fun customFieldText(field: DealField, value: String?): String? {
    val text = value?.trim()?.ifBlank { null } ?: return null
    return when (field.type) {
        DealField.TYPE_DATE -> Formatters.date(text).ifBlank { text }
        DealField.TYPE_NUMBER -> text.toDoubleOrNull()?.let { Formatters.number(it, maxFractionDigits = 6) } ?: text
        else -> text
    }
}
