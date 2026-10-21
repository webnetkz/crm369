package com.appswebnetkz.crm369.feature.forms

import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.ui.components.Option
import kotlinx.serialization.json.JsonObject

data class FormUser(val id: Long, val name: String, val email: String?) {
    companion object {
        fun fromJson(json: JsonObject): FormUser = FormUser(
            id = json.long("id") ?: 0L,
            name = json.text("name") ?: json.str("email").orEmpty(),
            email = json.text("email"),
        )
    }
}

/** `Field` of a form. */
data class FormFieldDef(
    val id: Long,
    val key: String,
    val label: String,
    val type: String,
    val placeholder: String?,
    val isRequired: Boolean,
    val sortOrder: Int,
) {
    companion object {
        const val TYPE_TEXT = "text"
        const val TYPE_TEXTAREA = "textarea"
        const val TYPE_EMAIL = "email"
        const val TYPE_NUMBER = "number"

        fun fromJson(json: JsonObject): FormFieldDef = FormFieldDef(
            id = json.long("id") ?: 0L,
            key = json.str("key").orEmpty(),
            label = json.str("label").orEmpty(),
            type = json.text("type") ?: TYPE_TEXT,
            placeholder = json.text("placeholder"),
            isRequired = json.boolOr("is_required", false),
            sortOrder = json.int("sort_order") ?: 0,
        )
    }
}

/** One answer of a submission (`payload` item). */
data class FormAnswer(val label: String, val type: String, val value: String?) {
    companion object {
        fun fromJson(json: JsonObject): FormAnswer = FormAnswer(
            label = json.text("label") ?: json.str("key").orEmpty(),
            type = json.text("type") ?: FormFieldDef.TYPE_TEXT,
            value = json.str("value"),
        )
    }
}

/** `Submission`: delivered as a task or as a chat message. */
data class FormSubmission(
    val id: Long,
    val createdAt: String?,
    val projectTaskId: Long?,
    val chatConversationId: Long?,
    val targetUserName: String?,
    val payload: List<FormAnswer>,
) {
    companion object {
        fun fromJson(json: JsonObject): FormSubmission = FormSubmission(
            id = json.long("id") ?: 0L,
            createdAt = json.text("created_at"),
            projectTaskId = json.long("project_task_id"),
            chatConversationId = json.long("chat_conversation_id"),
            targetUserName = json.text("target_user_name"),
            payload = json.objects("payload").map { FormAnswer.fromJson(it) },
        )
    }
}

/** `Completion`: what the public page does after a submission. */
data class FormCompletion(val action: String, val successMessage: String?, val redirectUrl: String?) {
    companion object {
        const val ACTION_MESSAGE = "message"
        const val ACTION_REDIRECT = "redirect"
        const val ACTION_CLOSE = "close"

        fun fromJson(json: JsonObject?): FormCompletion = FormCompletion(
            action = json.text("action") ?: ACTION_MESSAGE,
            successMessage = json.text("success_message"),
            redirectUrl = json.text("redirect_url"),
        )
    }
}

/** `FormListItem`. */
data class FormSummary(
    val id: Long,
    val name: String,
    val description: String?,
    val submissionMode: String,
    val isActive: Boolean,
    val publicUrl: String?,
    val targetUserName: String?,
    val fieldsCount: Int,
    val submissionsCount: Int,
    val lastSubmissionAt: String?,
) {
    companion object {
        const val MODE_TASK = "task"
        const val MODE_CHAT = "chat"

        fun fromJson(json: JsonObject): FormSummary = FormSummary(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            description = json.text("description"),
            submissionMode = json.text("submission_mode") ?: MODE_TASK,
            isActive = json.boolOr("is_active", true),
            publicUrl = json.text("public_url"),
            targetUserName = json.text("target_user_name"),
            fieldsCount = json.int("fields_count") ?: 0,
            submissionsCount = json.int("submissions_count") ?: 0,
            lastSubmissionAt = json.text("last_submission_at"),
        )
    }
}

/** `FormDetail` (`GET forms/{id}`); `style_settings` is kept raw and sent back unchanged. */
data class FormDetail(
    val summary: FormSummary,
    val targetUser: FormUser?,
    val owner: FormUser?,
    val fields: List<FormFieldDef>,
    val submissions: List<FormSubmission>,
    val completion: FormCompletion,
    val styleSettings: JsonObject?,
    val canManage: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject): FormDetail = FormDetail(
            summary = FormSummary.fromJson(json),
            targetUser = json.obj("target_user")?.let { FormUser.fromJson(it) },
            owner = json.obj("owner")?.let { FormUser.fromJson(it) },
            fields = json.objects("fields").map { FormFieldDef.fromJson(it) }.sortedBy { it.sortOrder },
            submissions = json.objects("submissions").map { FormSubmission.fromJson(it) },
            completion = FormCompletion.fromJson(json.obj("completion_settings")),
            styleSettings = json.obj("style_settings"),
            canManage = json.obj("can").boolOr("manage", false),
        )
    }
}

/** `GET forms`: the list plus everything the editor needs. */
data class FormsIndex(
    val forms: List<FormSummary>,
    val users: List<FormUser>,
    val fieldTypes: List<Option>,
    val completionActions: List<Option>,
    val styleDefaults: JsonObject?,
    val completionDefaults: FormCompletion,
    val canCreate: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject): FormsIndex = FormsIndex(
            forms = json.objects("forms").map { FormSummary.fromJson(it) },
            users = json.objects("availableUsers").map { FormUser.fromJson(it) },
            fieldTypes = options(json.objects("fieldTypes")),
            completionActions = options(json.objects("formCompletionActionOptions")),
            styleDefaults = json.obj("formStyleDefaults"),
            completionDefaults = FormCompletion.fromJson(json.obj("formCompletionDefaults")),
            canCreate = json.obj("can").boolOr("create", false),
        )

        private fun options(items: List<JsonObject>): List<Option> = items.mapNotNull { item ->
            val value = item.text("value") ?: return@mapNotNull null
            Option(value = value, label = item.text("label") ?: value)
        }
    }
}

/** All portal form API calls (`/api/mobile/v1/forms`). */
class FormsRepository(private val api: ApiClient) {

    suspend fun index(): FormsIndex = FormsIndex.fromJson(api.get("forms").dataObject())

    suspend fun form(formId: Long): FormDetail = FormDetail.fromJson(api.get("forms/$formId").dataObject())

    suspend fun create(body: JsonObject): FormDetail = FormDetail.fromJson(api.post("forms", body).dataObject())

    /** Full replacement: fields left out are deleted. */
    suspend fun update(formId: Long, body: JsonObject): FormDetail =
        FormDetail.fromJson(api.patch("forms/$formId", body).dataObject())

    suspend fun delete(formId: Long) {
        api.delete("forms/$formId")
    }
}
