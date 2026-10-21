package com.appswebnetkz.crm369.feature.contacts

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException

/** Create ([contactId] = null) or edit a contact. */
@Composable
fun ContactFormScreen(contactId: Long?) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val bootstrap = rememberBootstrap()
    val repository = remember(api) { ContactsRepository(api) }
    val state = remember(contactId) { FormState(mapOf("type" to Contact.TYPE_PERSON, "is_blacklisted" to false)) }
    var loaded by remember(contactId) { mutableStateOf(contactId == null) }
    var avatarUrl by remember { mutableStateOf<String?>(null) }
    var newAvatar by remember { mutableStateOf<Uri?>(null) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) newAvatar = uri
    }

    LaunchedEffect(contactId) {
        if (contactId == null) return@LaunchedEffect
        try {
            val contact = repository.contact(contactId)
            val requisites = contact.requisites
            state.setAll(
                mapOf(
                    "type" to contact.type,
                    "name" to contact.name,
                    "contact_person" to contact.contactPerson,
                    "position" to contact.position,
                    "email" to contact.email,
                    "phone" to contact.phone,
                    "notes" to contact.notes,
                    "is_blacklisted" to contact.isBlacklisted,
                    "company_requisites.iin" to requisites?.iin,
                    "company_requisites.bin" to requisites?.bin,
                    "company_requisites.legal_address" to requisites?.legalAddress,
                    "company_requisites.actual_address" to requisites?.actualAddress,
                    "company_requisites.bank_name" to requisites?.bankName,
                    "company_requisites.bank_bik" to requisites?.bankBik,
                    "company_requisites.iban" to requisites?.iban,
                    "company_requisites.kbe" to requisites?.kbe,
                ),
            )
            avatarUrl = contact.avatarUrl
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            messenger.showError(exception)
        } finally {
            loaded = true
        }
    }

    val typeOptions = buildList {
        if (bootstrap == null || bootstrap.can("canAccessPersonContacts")) {
            add(Option(Contact.TYPE_PERSON, stringResource(R.string.contacts_type_person)))
        }
        if (bootstrap == null || bootstrap.can("canAccessCompanyContacts")) {
            add(Option(Contact.TYPE_COMPANY, stringResource(R.string.contacts_type_company)))
        }
    }
    LaunchedEffect(typeOptions.size) {
        val current = state.string("type")
        if (typeOptions.isNotEmpty() && typeOptions.none { it.value == current }) {
            state.set("type", typeOptions.first().value)
        }
    }

    val isCompany = state.string("type") == Contact.TYPE_COMPANY
    val specs = buildList {
        add(FieldSpec.Select("type", stringResource(R.string.contacts_type), typeOptions, required = true))
        add(
            FieldSpec.Text(
                "name",
                stringResource(if (isCompany) R.string.contacts_company_name else R.string.contacts_full_name),
                required = true,
            ),
        )
        if (isCompany) {
            add(FieldSpec.Text("contact_person", stringResource(R.string.contacts_contact_person)))
        }
        add(FieldSpec.Text("position", stringResource(R.string.contacts_position)))
        add(FieldSpec.Phone("phone", stringResource(R.string.contacts_phone)))
        add(FieldSpec.Email("email", stringResource(R.string.contacts_email)))
        add(FieldSpec.Multiline("notes", stringResource(R.string.contacts_notes)))
        add(FieldSpec.Switch("is_blacklisted", stringResource(R.string.contacts_blacklist_switch), hint = stringResource(R.string.contacts_blacklist_hint)))
        add(FieldSpec.Section(stringResource(R.string.contacts_section_requisites)))
        if (isCompany) {
            add(FieldSpec.Number("company_requisites.bin", stringResource(R.string.contacts_bin), hint = stringResource(R.string.contacts_digits_hint)))
            add(FieldSpec.Text("company_requisites.legal_address", stringResource(R.string.contacts_legal_address)))
            add(FieldSpec.Text("company_requisites.actual_address", stringResource(R.string.contacts_actual_address)))
            add(FieldSpec.Text("company_requisites.bank_name", stringResource(R.string.contacts_bank_name)))
            add(FieldSpec.Text("company_requisites.bank_bik", stringResource(R.string.contacts_bank_bik)))
            add(FieldSpec.Text("company_requisites.iban", stringResource(R.string.contacts_iban)))
            add(FieldSpec.Text("company_requisites.kbe", stringResource(R.string.contacts_kbe)))
        } else {
            add(FieldSpec.Number("company_requisites.iin", stringResource(R.string.contacts_iin), hint = stringResource(R.string.contacts_digits_hint)))
        }
    }

    val savedMessage = stringResource(if (contactId == null) R.string.contacts_created_message else R.string.contacts_saved_message)

    FormScreen(
        title = stringResource(if (contactId == null) R.string.contacts_new else R.string.contacts_edit),
        specs = specs,
        state = state,
        isLoading = !loaded,
        successMessage = savedMessage,
        onSubmit = {
            val body = state.toJson(specs.filterNot { it is FieldSpec.Number }, extra = requisiteDigits(state, isCompany))
            if (contactId == null) {
                val created = repository.create(context, body, newAvatar)
                navigator.replace(ContactsRoutes.detail(created.id))
            } else {
                repository.update(context, contactId, body, newAvatar)
                navigator.back()
            }
        },
        onSuccess = {},
        header = {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                Avatar(
                    name = state.string("name").ifBlank { "?" },
                    imageUrl = newAvatar?.toString() ?: avatarUrl,
                    size = 64.dp,
                )
                OutlinedButton(onClick = { picker.launch("image/*") }) {
                    Icon(AppIcons.Camera, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.size(8.dp))
                    Text(stringResource(R.string.contacts_choose_photo))
                }
            }
        },
    )
}

/** IIN/BIN must be sent as strings of digits (leading zeros are significant). */
private fun requisiteDigits(state: FormState, isCompany: Boolean): Map<String, Any?> {
    fun digits(key: String): String? = state.string(key).filter { it.isDigit() }.ifBlank { null }
    return if (isCompany) {
        mapOf("company_requisites.bin" to digits("company_requisites.bin"))
    } else {
        mapOf("company_requisites.iin" to digits("company_requisites.iin"))
    }
}
