package com.appswebnetkz.crm369.feature.tasks

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
 * Create ([projectId] = null) or edit a project: name, description, archive flag and members.
 * The update replaces the whole project, so the loaded slug is sent back unchanged.
 */
@Composable
fun ProjectFormScreen(projectId: Long?) {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { TasksRepository(api) }
    val state = remember(projectId) { FormState(mapOf("is_archived" to false, "member_user_ids" to emptyList<String>())) }
    var loaded by remember(projectId) { mutableStateOf(false) }
    var users by remember(projectId) { mutableStateOf<List<TaskUser>>(emptyList()) }
    var slug by remember(projectId) { mutableStateOf<String?>(null) }

    LaunchedEffect(projectId) {
        try {
            if (projectId == null) {
                users = repository.projects().availableUsers
            } else {
                val page = repository.project(projectId)
                val project = page.activeProject
                users = (page.availableUsers + project?.members.orEmpty()).distinctBy { it.id }
                if (project != null) {
                    slug = project.slug
                    state.setAll(
                        mapOf(
                            "name" to project.name,
                            "description" to project.description,
                            "is_archived" to project.isArchived,
                            "member_user_ids" to project.members.map { it.id.toString() },
                        ),
                    )
                }
            }
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            messenger.showError(exception)
        } finally {
            loaded = true
        }
    }

    val userOptions = users.map { user -> Option(user.id.toString(), user.name, user.email) }
    val specs = listOf(
        FieldSpec.Text("name", stringResource(R.string.tasks_project_name), required = true, maxLength = 255),
        FieldSpec.Multiline("description", stringResource(R.string.tasks_description)),
        FieldSpec.UserPicker(
            "member_user_ids",
            stringResource(R.string.tasks_members),
            users = userOptions,
            multiple = true,
            hint = stringResource(R.string.tasks_members_hint),
        ),
        FieldSpec.Switch(
            "is_archived",
            stringResource(R.string.tasks_archive_project),
            hint = stringResource(R.string.tasks_archive_hint),
        ),
    )
    val savedMessage = stringResource(if (projectId == null) R.string.tasks_project_created else R.string.tasks_project_updated)

    FormScreen(
        title = stringResource(if (projectId == null) R.string.tasks_new_project else R.string.tasks_edit_project),
        specs = specs,
        state = state,
        isLoading = !loaded,
        successMessage = savedMessage,
        onSubmit = {
            val currentSlug = slug
            val extra: Map<String, Any?> = if (currentSlug != null) mapOf("slug" to currentSlug) else emptyMap()
            val body = state.toJson(specs, extra = extra)
            if (projectId == null) {
                val createdId = repository.createProject(body)
                navigator.replace(TasksRoutes.project(createdId))
            } else {
                repository.updateProject(projectId, body)
                navigator.back()
            }
        },
        onSuccess = {},
    )
}
