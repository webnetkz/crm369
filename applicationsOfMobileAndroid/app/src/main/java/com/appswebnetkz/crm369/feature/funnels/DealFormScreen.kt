package com.appswebnetkz.crm369.feature.funnels

import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import kotlinx.coroutines.CancellationException

/** Stages, custom fields and users needed by the deal form (+ the deal when editing). */
private data class DealFormContext(
    val stages: List<StageRef>,
    val fields: List<DealField>,
    val users: List<FunnelUser>,
    val deal: Deal?,
)

private const val DEFAULT_CURRENCY = "KZT"
private val CURRENCIES = listOf("KZT", "USD", "EUR", "RUB")
private const val CUSTOM_PREFIX = "custom_fields."

/**
 * Create ([dealId] = null, optional [initialStageId]) or edit a deal. The update endpoint replaces
 * the whole deal, so every field is sent; custom fields go as the `custom_fields` object.
 */
@Composable
fun DealFormScreen(funnelId: Long, dealId: Long?, initialStageId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { FunnelsRepository(api) }
    val state = remember(funnelId, dealId) { FormState(mapOf("currency" to DEFAULT_CURRENCY)) }
    var formContext by remember(funnelId, dealId) { mutableStateOf<DealFormContext?>(null) }
    var loadError by remember(funnelId, dealId) { mutableStateOf<Throwable?>(null) }
    var attempt by remember { mutableStateOf(0) }

    LaunchedEffect(funnelId, dealId, attempt) {
        loadError = null
        try {
            val loaded = if (dealId == null) {
                val page = repository.page(funnelId)
                val board = page.activeFunnel
                DealFormContext(
                    stages = board?.stages.orEmpty().map { it.ref },
                    fields = board?.dealFields.orEmpty(),
                    users = page.availableUsers,
                    deal = null,
                )
            } else {
                val page = repository.deal(funnelId, dealId)
                DealFormContext(
                    stages = page.stages,
                    fields = page.dealFields,
                    users = page.availableUsers,
                    deal = page.deal,
                )
            }
            state.setAll(initialDealValues(loaded, initialStageId))
            formContext = loaded
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            loadError = exception
        }
    }

    val title = stringResource(if (dealId == null) R.string.funnels_new_deal else R.string.funnels_edit_deal)
    val current = formContext
    val error = loadError
    if (current == null && error != null) {
        CrmScaffold(title = title) { padding ->
            ErrorState(error = error, onRetry = { attempt += 1 }, modifier = Modifier.padding(padding))
        }
    } else {
        val specs: List<FieldSpec> = if (current == null) emptyList() else dealSpecs(current, state.string("currency"))
        FormScreen(
            title = title,
            specs = specs,
            state = state,
            isLoading = current == null,
            successMessage = stringResource(if (dealId == null) R.string.funnels_deal_created else R.string.funnels_deal_updated),
            onSubmit = {
                if (current != null) {
                    val body = state.toJson(specs, extra = dealExtra(state, current))
                    if (dealId == null) {
                        val created = repository.createDeal(funnelId, body)
                        navigator.replace(FunnelsRoutes.deal(funnelId, created.id))
                    } else {
                        repository.updateDeal(funnelId, dealId, body)
                        navigator.back()
                    }
                }
            },
            onSuccess = {},
        )
    }
}

@Composable
private fun dealSpecs(context: DealFormContext, currentCurrency: String): List<FieldSpec> {
    val stageOptions = context.stages.map { Option(value = it.id.toString(), label = it.name) }
    val userOptions = context.users.map { Option(value = it.id.toString(), label = it.fullName, subtitle = it.email) }
    return buildList {
        add(
            FieldSpec.Select(
                "crm_funnel_stage_id",
                stringResource(R.string.funnels_stage),
                stageOptions,
                required = true,
                numeric = true,
            ),
        )
        add(FieldSpec.Text("title", stringResource(R.string.funnels_deal_title), required = true, maxLength = 255))
        add(
            FieldSpec.UserPicker(
                "responsible_user_id",
                stringResource(R.string.funnels_responsible_user),
                userOptions,
                hint = stringResource(R.string.funnels_responsible_hint),
            ),
        )
        add(FieldSpec.Text("company_name", stringResource(R.string.funnels_company_name), maxLength = 255))
        add(FieldSpec.Text("contact_name", stringResource(R.string.funnels_contact_name), maxLength = 255))
        add(FieldSpec.Phone("contact_phone", stringResource(R.string.funnels_contact_phone)))
        add(FieldSpec.Email("contact_email", stringResource(R.string.funnels_contact_email)))
        add(FieldSpec.Decimal("amount", stringResource(R.string.funnels_amount)))
        add(FieldSpec.Select("currency", stringResource(R.string.funnels_currency), currencyOptions(currentCurrency)))
        add(FieldSpec.Date("expected_close_at", stringResource(R.string.funnels_expected_close_at)))
        add(FieldSpec.Multiline("description", stringResource(R.string.funnels_deal_description), maxLength = 10000))
        if (context.fields.isNotEmpty()) {
            add(FieldSpec.Section(stringResource(R.string.funnels_custom_fields)))
            context.fields.forEach { field -> add(customFieldSpec(field)) }
        }
    }
}

private fun customFieldSpec(field: DealField): FieldSpec {
    val key = CUSTOM_PREFIX + field.key
    return when (field.type) {
        DealField.TYPE_TEXTAREA -> FieldSpec.Multiline(key, field.label, required = field.isRequired)
        DealField.TYPE_NUMBER -> FieldSpec.Decimal(key, field.label, required = field.isRequired)
        DealField.TYPE_DATE -> FieldSpec.Date(key, field.label, required = field.isRequired)
        else -> FieldSpec.Text(key, field.label, required = field.isRequired)
    }
}

/** KZT / USD / EUR / RUB plus the current currency of the deal when it is another one. */
private fun currencyOptions(current: String): List<Option> {
    val codes = if (current.isNotBlank() && current !in CURRENCIES) CURRENCIES + current else CURRENCIES
    return codes.map { code ->
        val symbol = Formatters.currencySymbol(code)
        Option(value = code, label = if (symbol.isBlank() || symbol == code) code else "$code ($symbol)")
    }
}

private fun initialDealValues(context: DealFormContext, initialStageId: Long?): Map<String, Any?> {
    val values = LinkedHashMap<String, Any?>()
    val deal = context.deal
    if (deal == null) {
        val stageId = initialStageId?.takeIf { id -> context.stages.any { it.id == id } } ?: context.stages.firstOrNull()?.id
        values["crm_funnel_stage_id"] = stageId?.toString()
        values["currency"] = DEFAULT_CURRENCY
        context.fields.forEach { field -> values[CUSTOM_PREFIX + field.key] = null }
    } else {
        values["crm_funnel_stage_id"] = deal.stageId.toString()
        values["title"] = deal.title
        values["responsible_user_id"] = deal.responsible?.id?.toString()
        values["company_name"] = deal.companyName
        values["contact_name"] = deal.contactName
        values["contact_phone"] = deal.contactPhone
        values["contact_email"] = deal.contactEmail
        values["amount"] = decimalInputText(deal.amount)
        values["currency"] = deal.currency
        values["expected_close_at"] = deal.expectedCloseAt?.take(10)
        values["description"] = deal.description
        context.fields.forEach { field -> values[CUSTOM_PREFIX + field.key] = deal.customFields[field.key] }
    }
    return values
}

/**
 * Values sent besides the typed specs: the amount as a normalized string (the backend drops
 * non-string amounts), numeric custom fields as strings, and `custom_fields: {}` when the funnel
 * has no custom fields.
 */
private fun dealExtra(state: FormState, context: DealFormContext): Map<String, Any?> {
    val extra = LinkedHashMap<String, Any?>()
    extra["amount"] = normalizeDecimalInput(state.string("amount"))
    if (context.fields.isEmpty()) {
        extra["custom_fields"] = emptyMap<String, Any?>()
    } else {
        context.fields.filter { it.type == DealField.TYPE_NUMBER }.forEach { field ->
            val key = CUSTOM_PREFIX + field.key
            extra[key] = normalizeDecimalInput(state.string(key))
        }
    }
    return extra
}
