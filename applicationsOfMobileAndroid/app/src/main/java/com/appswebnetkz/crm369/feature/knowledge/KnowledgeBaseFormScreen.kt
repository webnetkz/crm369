package com.appswebnetkz.crm369.feature.knowledge

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import kotlinx.coroutines.CancellationException

/**
 * Create ([baseId] = null) or edit a knowledge base: title, slug, description, publication and
 * visible user groups. The update replaces the whole base, so the current slug is always sent.
 */
@Composable
fun KnowledgeBaseFormScreen(baseId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { KnowledgeRepository(api) }
    val state = remember(baseId) { FormState(mapOf("is_published" to true)) }
    var loaded by remember(baseId) { mutableStateOf(false) }
    var groups by remember(baseId) { mutableStateOf<List<KnowledgeGroup>>(emptyList()) }
    var originalSlug by remember(baseId) { mutableStateOf<String?>(null) }

    LaunchedEffect(baseId) {
        try {
            val page = if (baseId == null) repository.index() else repository.base(baseId)
            groups = page.groups
            val base = page.activeBase
            if (baseId != null && base != null && base.id == baseId) {
                state.setAll(
                    mapOf(
                        "title" to base.title,
                        "slug" to base.slug,
                        "description" to base.description,
                        "is_published" to base.isPublished,
                        "user_group_ids" to base.groups.map { it.id.toString() },
                    ),
                )
                originalSlug = base.slug
            }
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            messenger.showError(exception)
        } finally {
            loaded = true
        }
    }

    val specs = listOf(
        FieldSpec.Text("title", stringResource(R.string.knowledge_base_title), required = true, maxLength = 255),
        FieldSpec.Text("slug", stringResource(R.string.knowledge_slug), hint = stringResource(R.string.knowledge_slug_hint), maxLength = 255),
        FieldSpec.Multiline("description", stringResource(R.string.knowledge_base_description), maxLength = 5000),
        FieldSpec.Switch("is_published", stringResource(R.string.knowledge_published)),
        FieldSpec.MultiSelect(
            "user_group_ids",
            stringResource(R.string.knowledge_visible_groups),
            options = groups.map { group -> Option(value = group.id.toString(), label = group.name) },
            hint = stringResource(R.string.knowledge_visible_groups_help),
            numeric = true,
        ),
    )
    val savedMessage = stringResource(if (baseId == null) R.string.knowledge_base_created else R.string.knowledge_base_updated)

    FormScreen(
        title = stringResource(if (baseId == null) R.string.knowledge_create_base else R.string.knowledge_edit_base),
        specs = specs,
        state = state,
        isLoading = !loaded,
        successMessage = savedMessage,
        onSubmit = {
            val slug = state.string("slug").trim().ifBlank { originalSlug.orEmpty() }.ifBlank { null }
            val body = state.toJson(specs, extra = mapOf("slug" to slug))
            if (baseId == null) {
                val createdId = repository.createBase(body)
                if (createdId > 0L) {
                    navigator.replace(KnowledgeRoutes.base(createdId))
                } else {
                    navigator.back()
                }
            } else {
                repository.updateBase(baseId, body)
                navigator.back()
            }
        },
        onSuccess = {},
    )
}
