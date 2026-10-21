package com.appswebnetkz.crm369.feature.forms

import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.EmptyState
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.RowDivider
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Portal forms of the user (`GET forms`): mode, activity, submissions; creation when `can.create`. */
@Composable
fun FormsListScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { FormsRepository(api) }
    val index = rememberLoadable(api) { repository.index() }
    val canCreate = index.data?.canCreate == true
    val openCreate: () -> Unit = { navigator.open(FormsRoutes.NEW) }

    CrmScaffold(
        title = stringResource(R.string.module_forms),
        floatingActionButton = {
            if (canCreate) {
                FloatingActionButton(onClick = openCreate) {
                    Icon(AppIcons.Add, contentDescription = stringResource(R.string.forms_create))
                }
            }
        },
    ) { padding ->
        LoadableContent(
            loadable = index,
            modifier = Modifier.padding(padding),
            isEmpty = { data -> data.forms.isEmpty() },
            empty = {
                EmptyState(
                    title = stringResource(R.string.forms_empty_title),
                    message = stringResource(R.string.forms_empty_message),
                    icon = AppIcons.Forms,
                    actionLabel = if (canCreate) stringResource(R.string.forms_create) else null,
                    onAction = if (canCreate) openCreate else null,
                )
            },
        ) { data ->
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(bottom = 88.dp),
            ) {
                items(data.forms, key = { it.id }) { form ->
                    FormRow(form = form, onClick = { navigator.open(FormsRoutes.detail(form.id)) })
                    RowDivider()
                }
            }
        }
    }
}

@Composable
private fun FormRow(form: FormSummary, onClick: () -> Unit) {
    val lastSubmission = Formatters.relative(form.lastSubmissionAt)
    val details = listOfNotNull(
        form.targetUserName?.let { name -> stringResource(R.string.forms_target_line, name) },
        stringResource(R.string.forms_submissions_count, form.submissionsCount),
        if (lastSubmission.isNotBlank()) stringResource(R.string.forms_last_submission, lastSubmission) else null,
    ).joinToString(" · ")
    val extended = LocalExtendedColors.current
    ListRow(
        title = form.name,
        overline = stringResource(formModeLabel(form.submissionMode)),
        subtitle = details,
        leading = {
            Icon(formModeIcon(form.submissionMode), contentDescription = null, tint = MaterialTheme.colorScheme.primary)
        },
        trailing = {
            if (form.isActive) {
                StatusChip(text = stringResource(R.string.forms_active), color = extended.success)
            } else {
                StatusChip(text = stringResource(R.string.forms_inactive), color = MaterialTheme.colorScheme.outline)
            }
        },
        onClick = onClick,
    )
}
