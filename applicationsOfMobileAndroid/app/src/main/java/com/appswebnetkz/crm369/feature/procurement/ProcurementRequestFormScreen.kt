package com.appswebnetkz.crm369.feature.procurement

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.SectionHeader
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormFields
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors
import kotlinx.serialization.json.JsonObject

private val REQUEST_ITEM_FIELDS = listOf(
    "item_name",
    "sku",
    "unit",
    "quantity",
    "target_unit_price",
    "warehouse_place_id",
    "production_reference",
    "notes",
)
private const val MAX_REQUEST_ITEMS = 50

/** New purchase request with an items editor; it is submitted for budget approval. */
@Composable
fun ProcurementRequestFormScreen() {
    val api = rememberApi()
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api) { repository.load() }
    val title = stringResource(R.string.procurement_new_request)
    ProcurementGate(title = title, loadable = procurement) { data ->
        RequestForm(title = title, places = data.places, repository = repository)
    }
}

@Composable
private fun RequestForm(title: String, places: List<ProcurementPlace>, repository: ProcurementRepository) {
    val navigator = LocalNavigator.current
    val runner = rememberActionRunner()
    val state = remember {
        FormState(
            mapOf(
                "currency" to DEFAULT_CURRENCY,
                "items.0.unit" to DEFAULT_UNIT,
                "items.0.quantity" to "1",
                "items.0.target_unit_price" to "0",
            ),
        )
    }
    var itemCount by remember { mutableIntStateOf(1) }
    val requiredMessage = stringResource(R.string.form_required)
    val createdMessage = stringResource(R.string.procurement_request_created)
    val currency = state.string("currency").ifBlank { DEFAULT_CURRENCY }
    val estimated = (0 until itemCount).sumOf { index ->
        (parseCountInput(state.string("items.$index.quantity")) ?: 0L) * (parseDecimalInput(state.string("items.$index.target_unit_price")) ?: 0.0)
    }
    val budget = parseDecimalInput(state.string("budget_amount"))

    val currencyOptions = PROCUREMENT_CURRENCIES.map { code -> Option(code, code) }
    val placeOptions = places.map { place -> Option(place.id.toString(), place.path, place.name.takeIf { it != place.path }) }
    val mainSpecs = listOf(
        FieldSpec.Text("title", stringResource(R.string.procurement_request_title), required = true, maxLength = 255),
        FieldSpec.Date("needed_at", stringResource(R.string.procurement_needed_at)),
        FieldSpec.Select("currency", stringResource(R.string.procurement_currency), currencyOptions, required = true),
        FieldSpec.Decimal(
            "budget_amount",
            stringResource(R.string.procurement_budget),
            required = true,
            hint = stringResource(R.string.procurement_budget_hint),
        ),
        FieldSpec.Multiline("justification", stringResource(R.string.procurement_justification)),
    )
    val itemNameLabel = stringResource(R.string.procurement_item_name)
    val skuLabel = stringResource(R.string.procurement_sku)
    val unitLabel = stringResource(R.string.procurement_unit)
    val quantityLabel = stringResource(R.string.procurement_quantity)
    val priceLabel = stringResource(R.string.procurement_target_unit_price)
    val placeLabel = stringResource(R.string.procurement_warehouse_place)
    val referenceLabel = stringResource(R.string.procurement_production_reference)
    val notesLabel = stringResource(R.string.procurement_notes)

    fun itemSpecs(index: Int): List<FieldSpec> = buildList {
        add(FieldSpec.Text("items.$index.item_name", itemNameLabel, required = true, maxLength = 255))
        add(FieldSpec.Text("items.$index.sku", skuLabel, maxLength = 255))
        add(FieldSpec.Text("items.$index.unit", unitLabel, required = true, maxLength = 32))
        add(FieldSpec.Number("items.$index.quantity", quantityLabel, required = true))
        add(FieldSpec.Decimal("items.$index.target_unit_price", "$priceLabel, $currency", required = true))
        if (placeOptions.isNotEmpty()) {
            add(FieldSpec.Select("items.$index.warehouse_place_id", placeLabel, placeOptions, numeric = true))
        }
        add(FieldSpec.Text("items.$index.production_reference", referenceLabel, maxLength = 255))
        add(FieldSpec.Multiline("items.$index.notes", notesLabel, minLines = 2))
    }

    fun addItem() {
        state.set("items.$itemCount.unit", DEFAULT_UNIT)
        state.set("items.$itemCount.quantity", "1")
        state.set("items.$itemCount.target_unit_price", "0")
        itemCount += 1
    }

    fun removeItem(index: Int) {
        removeFormRow(state, "items", REQUEST_ITEM_FIELDS, index, itemCount)
        itemCount -= 1
    }

    ProcurementFormScaffold(
        title = title,
        submitLabel = stringResource(R.string.procurement_submit_request),
        isBusy = runner.isBusy,
        generalError = state.generalError,
        onSubmit = {
            val allSpecs = mainSpecs + (0 until itemCount).flatMap { itemSpecs(it) }
            if (state.validate(allSpecs, requiredMessage)) {
                val body = requestBody(state, itemCount)
                runner.execute(
                    successMessage = createdMessage,
                    onError = { error -> state.applyErrors(error) },
                    onSuccess = { createdId: Long? ->
                        if (createdId != null) navigator.replace(ProcurementRoutes.purchaseRequest(createdId)) else navigator.back()
                    },
                ) { repository.createRequest(body) }
            }
        },
    ) {
        FormFields(specs = mainSpecs, state = state, enabled = !runner.isBusy)

        Surface(
            color = MaterialTheme.colorScheme.secondaryContainer,
            contentColor = MaterialTheme.colorScheme.onSecondaryContainer,
            shape = MaterialTheme.shapes.medium,
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
        ) {
            Column(modifier = Modifier.padding(12.dp)) {
                Text(
                    text = stringResource(R.string.procurement_estimated_total_value, formatMoney(estimated, currency)),
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold,
                )
                if (budget != null && budget < estimated) {
                    Text(
                        text = stringResource(R.string.procurement_budget_below_estimate),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.error,
                    )
                } else if (budget != null && estimated > 0) {
                    Text(
                        text = stringResource(R.string.procurement_budget_reserve, formatMoney(budget - estimated, currency)),
                        style = MaterialTheme.typography.bodySmall,
                        color = LocalExtendedColors.current.success,
                    )
                }
                if (estimated > 0 && budget != estimated) {
                    TextButton(onClick = { state.set("budget_amount", plainDecimal(estimated)) }) {
                        Text(stringResource(R.string.procurement_use_estimate))
                    }
                }
            }
        }

        SectionHeader(title = stringResource(R.string.procurement_items_count, itemCount))
        for (index in 0 until itemCount) {
            InfoCard {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(start = 16.dp, end = 4.dp, top = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        text = stringResource(R.string.procurement_item_number, index + 1),
                        style = MaterialTheme.typography.titleSmall,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.weight(1f),
                    )
                    IconButton(onClick = { removeItem(index) }, enabled = itemCount > 1 && !runner.isBusy) {
                        Icon(AppIcons.Delete, contentDescription = stringResource(R.string.procurement_remove_item))
                    }
                }
                FormFields(
                    specs = itemSpecs(index),
                    state = state,
                    modifier = Modifier.padding(bottom = 8.dp),
                    enabled = !runner.isBusy,
                )
            }
        }
        OutlinedButton(
            onClick = { addItem() },
            enabled = itemCount < MAX_REQUEST_ITEMS && !runner.isBusy,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
        ) {
            Icon(AppIcons.Add, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.size(8.dp))
            Text(stringResource(R.string.procurement_add_item))
        }
    }
}

/** Plain decimal text for an input field (no grouping, dot separator, no trailing ".0"). */
private fun plainDecimal(value: Double): String {
    val rounded = Math.round(value * 100.0) / 100.0
    return if (rounded % 1.0 == 0.0) rounded.toLong().toString() else rounded.toString()
}

private fun requestBody(state: FormState, itemCount: Int): JsonObject = jsonObjectOf(
    "title" to state.string("title").trim(),
    "needed_at" to optionalText(state, "needed_at"),
    "budget_amount" to (parseDecimalInput(state.string("budget_amount")) ?: 0.0),
    "currency" to state.string("currency").ifBlank { DEFAULT_CURRENCY },
    "justification" to optionalText(state, "justification"),
    "items" to (0 until itemCount).map { index ->
        mapOf(
            "item_name" to state.string("items.$index.item_name").trim(),
            "sku" to optionalText(state, "items.$index.sku"),
            "unit" to state.string("items.$index.unit").trim().ifBlank { DEFAULT_UNIT },
            "quantity" to (parseCountInput(state.string("items.$index.quantity")) ?: 0L),
            "target_unit_price" to (parseDecimalInput(state.string("items.$index.target_unit_price")) ?: 0.0),
            "warehouse_place_id" to state.string("items.$index.warehouse_place_id").toLongOrNull(),
            "production_reference" to optionalText(state, "items.$index.production_reference"),
            "notes" to optionalText(state, "items.$index.notes"),
        )
    },
)
