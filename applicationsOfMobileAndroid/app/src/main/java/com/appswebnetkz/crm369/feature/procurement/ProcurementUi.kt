package com.appswebnetkz.crm369.feature.procurement

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.Loadable
import com.appswebnetkz.crm369.ui.components.LoadingState
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors
import java.time.LocalDate

@Composable
internal fun procurementStatusLabel(status: String): String = when (status) {
    "draft" -> stringResource(R.string.procurement_status_draft)
    "pending_approval" -> stringResource(R.string.procurement_status_pending_approval)
    "approved" -> stringResource(R.string.procurement_status_approved)
    "rejected" -> stringResource(R.string.procurement_status_rejected)
    "ordered" -> stringResource(R.string.procurement_status_ordered)
    "sent" -> stringResource(R.string.procurement_status_sent)
    "partially_received" -> stringResource(R.string.procurement_status_partially_received)
    "received" -> stringResource(R.string.procurement_status_received)
    "cancelled" -> stringResource(R.string.procurement_status_cancelled)
    "posted" -> stringResource(R.string.procurement_status_posted)
    else -> status
}

@Composable
internal fun procurementStatusColor(status: String): Color = when (status) {
    "approved", "received", "posted" -> LocalExtendedColors.current.success
    "rejected", "cancelled" -> MaterialTheme.colorScheme.error
    "pending_approval", "partially_received" -> LocalExtendedColors.current.warning
    else -> LocalExtendedColors.current.info
}

internal fun formatMoney(amount: Double, currency: String): String = Formatters.money(amount, currency)

internal fun formatQuantity(quantity: Long, unit: String): String = Formatters.number(quantity) + " " + unit

internal fun formatCurrencyTotals(values: Map<String, Double>): String =
    if (values.isEmpty()) {
        Formatters.money(0.0, DEFAULT_CURRENCY)
    } else {
        values.entries.joinToString(" · ") { entry -> Formatters.money(entry.value, entry.key) }
    }

internal fun todayIso(): String = LocalDate.now().toString()

internal fun parseDecimalInput(text: String): Double? =
    text.filter { !it.isWhitespace() }.replace(',', '.').toDoubleOrNull()

internal fun parseCountInput(text: String): Long? = text.filter { !it.isWhitespace() }.toLongOrNull()

internal fun optionalText(state: FormState, key: String): String? = state.string(key).trim().ifBlank { null }

/**
 * Laravel reports item errors by the index in the sent array (`items.0.quantity`); when only some
 * form rows are sent, move the messages to the keys of the form rows ([sentRows] = row of each sent item).
 */
internal fun remapItemErrors(state: FormState, prefix: String, sentRows: List<Int>) {
    val pattern = Regex("^" + Regex.escape(prefix) + "\\.(\\d+)\\.(.+)$")
    val moved = state.errors.entries.mapNotNull { entry ->
        val match = pattern.matchEntire(entry.key) ?: return@mapNotNull null
        val row = sentRows.getOrNull(match.groupValues[1].toInt()) ?: return@mapNotNull null
        Triple(entry.key, "$prefix.$row.${match.groupValues[2]}", entry.value)
    }
    moved.forEach { (oldKey, _, _) -> state.errors.remove(oldKey) }
    moved.forEach { (_, newKey, message) -> state.errors[newKey] = message }
}

/** Removes row [index] of a repeated group (`prefix.N.field`) by moving the following rows up. */
internal fun removeFormRow(state: FormState, prefix: String, fields: List<String>, index: Int, count: Int) {
    for (row in index until count - 1) {
        fields.forEach { field -> state.set("$prefix.$row.$field", state.value("$prefix.${row + 1}.$field")) }
    }
    fields.forEach { field -> state.set("$prefix.${count - 1}.$field", null) }
}

/** Shows [content] once `GET procurement` is loaded; loading and error states otherwise. */
@Composable
internal fun ProcurementGate(
    title: String,
    loadable: Loadable<ProcurementData>,
    content: @Composable (ProcurementData) -> Unit,
) {
    val data = loadable.data
    val error = loadable.error
    if (data != null) {
        content(data)
    } else {
        CrmScaffold(title = title) { padding ->
            if (error != null) {
                ErrorState(error = error, onRetry = { loadable.load() }, modifier = Modifier.padding(padding))
            } else {
                LoadingState(modifier = Modifier.padding(padding))
            }
        }
    }
}

@Composable
internal fun ProcurementNotFound(modifier: Modifier = Modifier) {
    EmptyState(
        title = stringResource(R.string.procurement_not_found),
        modifier = modifier,
        icon = AppIcons.Warning,
    )
}

@Composable
internal fun ProcurementNotFoundScreen(title: String) {
    CrmScaffold(title = title) { padding ->
        ProcurementNotFound(modifier = Modifier.padding(padding))
    }
}

/** Status chip, number and title at the top of a detail screen. */
@Composable
internal fun ProcurementHeader(status: String, number: String, title: String) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        StatusChip(text = procurementStatusLabel(status), color = procurementStatusColor(status))
        Spacer(Modifier.height(8.dp))
        if (number.isNotBlank()) {
            Text(
                text = number,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Text(text = title, style = MaterialTheme.typography.titleLarge)
    }
}

@Composable
internal fun ProcurementActionButton(
    text: String,
    icon: ImageVector,
    enabled: Boolean,
    outlined: Boolean = false,
    onClick: () -> Unit,
) {
    val modifier = Modifier
        .fillMaxWidth()
        .padding(horizontal = 16.dp, vertical = 4.dp)
    if (outlined) {
        OutlinedButton(onClick = onClick, enabled = enabled, modifier = modifier) {
            Icon(icon, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.size(8.dp))
            Text(text)
        }
    } else {
        Button(onClick = onClick, enabled = enabled, modifier = modifier) {
            Icon(icon, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.size(8.dp))
            Text(text)
        }
    }
}

/** Small explanatory text inside cards and forms. */
@Composable
internal fun ProcurementHint(text: String, color: Color = MaterialTheme.colorScheme.onSurfaceVariant) {
    Text(
        text = text,
        style = MaterialTheme.typography.bodySmall,
        color = color,
        modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp),
    )
}

/** Form layout with a bottom submit button (like `FormScreen`, but with custom content). */
@Composable
internal fun ProcurementFormScaffold(
    title: String,
    submitLabel: String,
    isBusy: Boolean,
    generalError: String?,
    onSubmit: () -> Unit,
    content: @Composable ColumnScope.() -> Unit,
) {
    CrmScaffold(
        title = title,
        bottomBar = {
            Surface(tonalElevation = 3.dp) {
                Button(
                    onClick = onSubmit,
                    enabled = !isBusy,
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .imePadding()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                ) {
                    if (isBusy) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onPrimary,
                        )
                        Spacer(Modifier.size(8.dp))
                    }
                    Text(submitLabel)
                }
            }
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(vertical = 12.dp),
        ) {
            if (!generalError.isNullOrBlank()) {
                Surface(
                    color = MaterialTheme.colorScheme.errorContainer,
                    contentColor = MaterialTheme.colorScheme.onErrorContainer,
                    shape = MaterialTheme.shapes.medium,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                ) {
                    Text(generalError, modifier = Modifier.padding(12.dp))
                }
            }
            content()
            Spacer(Modifier.height(24.dp))
        }
    }
}
