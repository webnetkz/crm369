package com.appswebnetkz.crm369.feature.forms

import androidx.annotation.StringRes
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.navigation.longArg
import com.appswebnetkz.crm369.ui.theme.AppIcons

/** Routes of the portal forms module (`formId` is `LongType`, so `forms/new` is a literal route). */
object FormsRoutes {
    const val LIST = "forms"
    const val DETAIL = "forms/{formId}"
    const val NEW = "forms/new"
    const val EDIT = "forms/{formId}/edit"

    fun detail(formId: Long): String = "forms/$formId"
    fun edit(formId: Long): String = "forms/$formId/edit"
}

fun NavGraphBuilder.formsGraph() {
    composable(FormsRoutes.LIST) { FormsListScreen() }
    composable(FormsRoutes.NEW) { FormEditScreen(formId = null) }
    composable(
        route = FormsRoutes.DETAIL,
        arguments = listOf(navArgument("formId") { type = NavType.LongType }),
    ) { entry ->
        FormDetailScreen(formId = entry.longArg("formId"))
    }
    composable(
        route = FormsRoutes.EDIT,
        arguments = listOf(navArgument("formId") { type = NavType.LongType }),
    ) { entry ->
        FormEditScreen(formId = entry.longArg("formId"))
    }
}

@StringRes
internal fun formModeLabel(mode: String): Int =
    if (mode == FormSummary.MODE_CHAT) R.string.forms_mode_chat else R.string.forms_mode_task

internal fun formModeIcon(mode: String): ImageVector =
    if (mode == FormSummary.MODE_CHAT) AppIcons.Chats else AppIcons.Tasks

@StringRes
internal fun formFieldTypeLabel(type: String): Int = when (type) {
    FormFieldDef.TYPE_TEXTAREA -> R.string.forms_field_type_textarea
    FormFieldDef.TYPE_EMAIL -> R.string.forms_field_type_email
    FormFieldDef.TYPE_NUMBER -> R.string.forms_field_type_number
    else -> R.string.forms_field_type_text
}

@StringRes
internal fun formCompletionLabel(action: String): Int = when (action) {
    FormCompletion.ACTION_REDIRECT -> R.string.forms_completion_redirect
    FormCompletion.ACTION_CLOSE -> R.string.forms_completion_close
    else -> R.string.forms_completion_message
}
