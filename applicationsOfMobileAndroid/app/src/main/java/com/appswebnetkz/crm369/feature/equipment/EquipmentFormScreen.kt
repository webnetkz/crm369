package com.appswebnetkz.crm369.feature.equipment

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
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import kotlinx.coroutines.CancellationException

private const val KEY_NAME = "name"
private const val KEY_QR_CODE = "qr_code"
private const val KEY_STATUS = "status"
private const val KEY_ISSUED_TO = "issued_to_user_id"
private const val KEY_RESPONSIBLE = "responsible_user_id"

/**
 * Create ([itemId] = null) or edit equipment. Updates replace the whole record, so the current
 * `qr_code` and `responsible_user_id` are always sent; `issued_to_user_id` is required for the
 * "issued" stage and cleared for every other stage.
 */
@Composable
fun EquipmentFormScreen(itemId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val bootstrap = rememberBootstrap()
    val repository = remember(api) { EquipmentRepository(api) }
    val usersLoader = remember(api) { EquipmentUsers(api) }
    val state = remember(itemId) { FormState(mapOf(KEY_STATUS to EquipmentItem.STATUS_ON_BALANCE)) }
    var loaded by remember(itemId) { mutableStateOf(false) }
    var loadError by remember(itemId) { mutableStateOf<Throwable?>(null) }
    var attempt by remember(itemId) { mutableStateOf(0) }
    var statuses by remember { mutableStateOf<List<Option>>(emptyList()) }
    var users by remember { mutableStateOf<List<Option>>(emptyList()) }

    LaunchedEffect(itemId, attempt) {
        loaded = false
        loadError = null
        try {
            val catalog = try {
                repository.list()
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                null
            }
            statuses = catalog?.statuses.orEmpty().map { Option(value = it.value, label = it.label, subtitle = it.description) }
            val item = if (itemId != null) repository.item(itemId) else null
            if (item != null) {
                state.setAll(
                    mapOf(
                        KEY_NAME to item.name,
                        KEY_QR_CODE to item.qrCode,
                        KEY_STATUS to item.status,
                        KEY_ISSUED_TO to item.issuedTo?.id,
                        KEY_RESPONSIBLE to item.responsible?.id,
                    ),
                )
            }
            users = usersLoader.load(bootstrap, listOfNotNull(item?.issuedTo, item?.responsible))
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            loadError = exception
        } finally {
            loaded = true
        }
    }

    val title = stringResource(if (itemId == null) R.string.equipment_new else R.string.equipment_edit)
    val error = loadError
    if (itemId != null && error != null) {
        CrmScaffold(title = title) { padding ->
            ErrorState(error = error, onRetry = { attempt += 1 }, modifier = Modifier.padding(padding))
        }
        return
    }

    val fallbackStatuses = fallbackEquipmentStatuses()
    val statusOptions = statuses.ifEmpty { fallbackStatuses }
    val isIssued = state.string(KEY_STATUS) == EquipmentItem.STATUS_ISSUED
    val specs = buildList<FieldSpec> {
        add(
            FieldSpec.Text(
                KEY_NAME,
                stringResource(R.string.equipment_name),
                required = true,
                hint = stringResource(R.string.equipment_name_placeholder),
                maxLength = 255,
            ),
        )
        add(
            FieldSpec.Text(
                KEY_QR_CODE,
                stringResource(R.string.equipment_qr_code),
                required = itemId != null,
                hint = stringResource(if (itemId == null) R.string.equipment_qr_code_placeholder else R.string.equipment_qr_code_edit_hint),
                maxLength = 64,
            ),
        )
        add(FieldSpec.Select(KEY_STATUS, stringResource(R.string.equipment_status), statusOptions, required = true))
        if (isIssued) {
            add(FieldSpec.UserPicker(KEY_ISSUED_TO, stringResource(R.string.equipment_issued_to), users, required = true))
        }
        add(FieldSpec.UserPicker(KEY_RESPONSIBLE, stringResource(R.string.equipment_responsible), users))
    }
    val savedMessage = stringResource(if (itemId == null) R.string.equipment_created_success else R.string.equipment_updated_success)

    FormScreen(
        title = title,
        specs = specs,
        state = state,
        isLoading = !loaded,
        successMessage = savedMessage,
        onSubmit = {
            val issued = state.string(KEY_STATUS) == EquipmentItem.STATUS_ISSUED
            val extra: Map<String, Any?> = if (issued) emptyMap() else mapOf(KEY_ISSUED_TO to null)
            val body = state.toJson(specs, extra = extra)
            if (itemId == null) {
                val created = repository.create(body)
                navigator.replace(EquipmentRoutes.detail(created.id))
            } else {
                repository.update(itemId, body)
                navigator.back()
            }
        },
        onSuccess = {},
    )
}
