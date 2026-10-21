package com.appswebnetkz.crm369.feature.procurement

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi

/** Create ([supplierId] = null) or edit a supplier; the update replaces every field. */
@Composable
fun ProcurementSupplierFormScreen(supplierId: Long?) {
    val api = rememberApi()
    val repository = remember(api) { ProcurementRepository(api) }
    val procurement = rememberLoadable(api, supplierId) { repository.load() }
    val title = stringResource(if (supplierId == null) R.string.procurement_new_supplier else R.string.procurement_edit_supplier)
    ProcurementGate(title = title, loadable = procurement) { data ->
        val supplier = if (supplierId != null) data.suppliers.firstOrNull { it.id == supplierId } else null
        if (supplierId != null && supplier == null) {
            ProcurementNotFoundScreen(title = title)
        } else {
            SupplierForm(title = title, supplier = supplier, contacts = data.contacts, repository = repository)
        }
    }
}

@Composable
private fun SupplierForm(
    title: String,
    supplier: ProcurementSupplier?,
    contacts: List<ProcurementContact>,
    repository: ProcurementRepository,
) {
    val navigator = LocalNavigator.current
    val state = remember(supplier?.id) {
        FormState(
            mapOf(
                "name" to supplier?.name,
                "bin" to supplier?.bin,
                "contact_id" to supplier?.contactId?.toString(),
                "contact_person" to supplier?.contactPerson,
                "email" to supplier?.email,
                "phone" to supplier?.phone,
                "currency" to (supplier?.currency ?: DEFAULT_CURRENCY),
                "payment_terms_days" to (supplier?.paymentTermsDays ?: 0).toString(),
                "lead_time_days" to (supplier?.leadTimeDays ?: 0).toString(),
                "rating" to supplier?.rating?.toString(),
                "is_active" to (supplier?.isActive ?: true),
                "notes" to supplier?.notes,
            ),
        )
    }
    val currencyOptions = PROCUREMENT_CURRENCIES.map { code -> Option(code, code) }
    val contactOptions = contacts.map { contact -> Option(contact.id.toString(), contact.name, contact.contactPerson) }
    val specs = buildList {
        add(FieldSpec.Text("name", stringResource(R.string.procurement_supplier_name), required = true, maxLength = 255))
        add(FieldSpec.Text("bin", stringResource(R.string.procurement_bin), hint = stringResource(R.string.procurement_bin_hint)))
        if (contactOptions.isNotEmpty()) {
            add(FieldSpec.Select("contact_id", stringResource(R.string.procurement_contact), contactOptions, numeric = true))
        }
        add(FieldSpec.Text("contact_person", stringResource(R.string.procurement_contact_person), maxLength = 255))
        add(FieldSpec.Phone("phone", stringResource(R.string.procurement_phone)))
        add(FieldSpec.Email("email", stringResource(R.string.procurement_email)))
        add(FieldSpec.Select("currency", stringResource(R.string.procurement_currency), currencyOptions, required = true))
        add(FieldSpec.Number("payment_terms_days", stringResource(R.string.procurement_payment_terms_days), required = true))
        add(FieldSpec.Number("lead_time_days", stringResource(R.string.procurement_lead_time_days), required = true))
        add(FieldSpec.Decimal("rating", stringResource(R.string.procurement_rating), hint = stringResource(R.string.procurement_rating_hint)))
        add(FieldSpec.Switch("is_active", stringResource(R.string.procurement_is_active)))
        add(FieldSpec.Multiline("notes", stringResource(R.string.procurement_notes)))
    }
    val savedMessage = stringResource(if (supplier == null) R.string.procurement_supplier_created else R.string.procurement_supplier_saved)

    FormScreen(
        title = title,
        specs = specs,
        state = state,
        successMessage = savedMessage,
        submitLabel = stringResource(if (supplier == null) R.string.procurement_new_supplier else R.string.action_save),
        onSubmit = {
            val extra = buildMap<String, Any?> {
                put("bin", state.string("bin").filter { it.isDigit() }.ifBlank { null })
                if (contactOptions.isEmpty()) put("contact_id", supplier?.contactId)
            }
            val body = state.toJson(specs, extra = extra)
            if (supplier == null) {
                repository.createSupplier(body)
            } else {
                repository.updateSupplier(supplier.id, body)
            }
        },
        onSuccess = { navigator.back() },
    )
}
