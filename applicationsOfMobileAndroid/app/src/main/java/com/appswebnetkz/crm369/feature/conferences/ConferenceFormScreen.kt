package com.appswebnetkz.crm369.feature.conferences

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import java.time.format.DateTimeParseException

private const val KEY_TITLE = "title"
private const val KEY_DESCRIPTION = "description"
private const val KEY_STARTS_AT = "starts_at"
private const val KEY_GUESTS = "allow_external_guests"
private const val KEY_INVITED = "invited_user_ids"

/**
 * New conference: title, description, start (local date-time converted to UTC; empty = starts
 * now), guest link switch and invited users (`availableUsers` of `GET conferences`).
 */
@Composable
fun ConferenceFormScreen() {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val repository = remember(api) { ConferencesRepository(api) }
    val overview = rememberLoadable(api) { repository.overview() }
    val state = remember { FormState(mapOf(KEY_GUESTS to true)) }
    val users = overview.data?.availableUsers.orEmpty().map { it.toOption() }

    val specs = listOf<FieldSpec>(
        FieldSpec.Text(KEY_TITLE, stringResource(R.string.conferences_field_title), required = true, maxLength = 255),
        FieldSpec.Multiline(KEY_DESCRIPTION, stringResource(R.string.conferences_field_description), maxLength = 10000),
        FieldSpec.DateTime(KEY_STARTS_AT, stringResource(R.string.conferences_field_starts_at), hint = stringResource(R.string.conferences_starts_at_hint)),
        FieldSpec.Switch(KEY_GUESTS, stringResource(R.string.conferences_field_allow_external_guests)),
        FieldSpec.UserPicker(KEY_INVITED, stringResource(R.string.conferences_field_invited_users), users, multiple = true),
    )

    FormScreen(
        title = stringResource(R.string.conferences_create_title),
        specs = specs,
        state = state,
        isLoading = overview.data == null && overview.error == null,
        submitLabel = stringResource(R.string.conferences_create_button),
        successMessage = stringResource(R.string.conferences_created_success),
        onSubmit = {
            val startsAt = try {
                ConferenceTime.toUtcIso(state.string(KEY_STARTS_AT))
            } catch (exception: DateTimeParseException) {
                val message = context.getString(R.string.conferences_starts_at_invalid)
                state.errors[KEY_STARTS_AT] = message
                throw IllegalArgumentException(message)
            }
            val body = state.toJson(specs.filterNot { it.key == KEY_STARTS_AT }, extra = mapOf(KEY_STARTS_AT to startsAt))
            val created = repository.create(body)
            navigator.replace(ConferencesRoutes.detail(created.conference.id))
        },
        onSuccess = {},
    )
}
