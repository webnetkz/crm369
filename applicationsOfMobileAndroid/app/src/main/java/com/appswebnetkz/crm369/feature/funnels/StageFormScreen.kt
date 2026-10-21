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
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import kotlinx.coroutines.CancellationException

private const val DEFAULT_STAGE_COLOR = "#64748B"

/**
 * Create ([stageId] = null) or edit a funnel stage: name, color, type and sort order. The sort
 * order is always sent (the update endpoint resets a missing value to 0).
 */
@Composable
fun StageFormScreen(funnelId: Long, stageId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val repository = remember(api) { FunnelsRepository(api) }
    val state = remember(funnelId, stageId) {
        FormState(mapOf("type" to StageRef.TYPE_OPEN, "color" to DEFAULT_STAGE_COLOR, "sort_order" to 0))
    }
    var page by remember(funnelId, stageId) { mutableStateOf<FunnelPage?>(null) }
    var loadError by remember(funnelId, stageId) { mutableStateOf<Throwable?>(null) }
    var attempt by remember { mutableStateOf(0) }

    LaunchedEffect(funnelId, stageId, attempt) {
        loadError = null
        try {
            val loaded = repository.page(funnelId)
            val stages = loaded.activeFunnel?.stages.orEmpty()
            if (stageId == null) {
                state.setAll(mapOf("sort_order" to (stages.maxOfOrNull { it.sortOrder }?.plus(1) ?: 0)))
            } else {
                val stage = stages.firstOrNull { it.id == stageId } ?: throw ApiException(status = 404, message = "Not Found")
                state.setAll(
                    mapOf(
                        "name" to stage.name,
                        "color" to stage.color,
                        "type" to stage.type,
                        "sort_order" to stage.sortOrder,
                    ),
                )
            }
            page = loaded
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            loadError = exception
        }
    }

    val title = stringResource(if (stageId == null) R.string.funnels_create_stage else R.string.funnels_edit_stage)
    val loaded = page
    val error = loadError
    if (loaded == null && error != null) {
        CrmScaffold(title = title) { padding ->
            ErrorState(error = error, onRetry = { attempt += 1 }, modifier = Modifier.padding(padding))
        }
    } else {
        val serverTypes = loaded?.stageTypes.orEmpty()
        val typeOptions = StageRef.TYPES.map { type -> Option(value = type, label = stageTypeLabel(type, serverTypes)) }
        val specs: List<FieldSpec> = listOf(
            FieldSpec.Text("name", stringResource(R.string.funnels_stage_name), required = true, maxLength = 255),
            FieldSpec.Color("color", stringResource(R.string.funnels_stage_color)),
            FieldSpec.Select("type", stringResource(R.string.funnels_stage_type), typeOptions, required = true),
            FieldSpec.Number(
                "sort_order",
                stringResource(R.string.funnels_stage_sort_order),
                required = true,
                hint = stringResource(R.string.funnels_sort_order_hint),
            ),
        )
        FormScreen(
            title = title,
            specs = specs,
            state = state,
            isLoading = loaded == null,
            successMessage = stringResource(if (stageId == null) R.string.funnels_stage_created else R.string.funnels_stage_updated),
            onSubmit = {
                val body = state.toJson(specs)
                if (stageId == null) {
                    repository.createStage(funnelId, body)
                } else {
                    repository.updateStage(funnelId, stageId, body)
                }
                navigator.back()
            },
            onSuccess = {},
        )
    }
}
