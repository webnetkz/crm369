package com.appswebnetkz.crm369.feature.tasks

import android.content.Context
import android.net.Uri
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.dataObjects
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.UploadPart
import kotlinx.serialization.json.JsonObject

/** All projects & tasks API calls (`/api/mobile/v1/projects`, `tasks`, `task-stages`, task discussions). */
class TasksRepository(private val api: ApiClient) {

    /** Every visible task: the standalone group plus one group per project (`GET projects/workspace`). */
    suspend fun workspace(): ProjectsPage = ProjectsPage.fromJson(api.get("projects/workspace"))

    /** Project list (`GET projects`). */
    suspend fun projects(): ProjectsPage = ProjectsPage.fromJson(api.get("projects"))

    /** Project page with `activeProject` (`GET projects/{id}`). */
    suspend fun project(projectId: Long): ProjectsPage = ProjectsPage.fromJson(api.get("projects/$projectId"))

    /** Task page with `activeTask` (`GET tasks/{id}`). */
    suspend fun task(taskId: Long): ProjectsPage = ProjectsPage.fromJson(api.get("tasks/$taskId"))

    /** Creates a project and returns its id. Body: name, description, is_archived, member_user_ids. */
    suspend fun createProject(body: JsonObject): Long = api.post("projects", body).dataObject().long("id") ?: 0L

    /** Full replace of a project. */
    suspend fun updateProject(projectId: Long, body: JsonObject) {
        api.patch("projects/$projectId", body)
    }

    suspend fun deleteProject(projectId: Long) {
        api.delete("projects/$projectId")
    }

    /** Creates a task and returns its id (the body carries every field, see the task form). */
    suspend fun createTask(body: JsonObject): Long = api.post("tasks", body).dataObject().long("id") ?: 0L

    /** Full replace of a task. */
    suspend fun updateTask(taskId: Long, body: JsonObject) {
        api.patch("tasks/$taskId", body)
    }

    suspend fun deleteTask(taskId: Long) {
        api.delete("tasks/$taskId")
    }

    /** Moves a task to another kanban stage (`PATCH tasks/{id}/move {status}`). */
    suspend fun moveTask(taskId: Long, status: String) {
        api.patch("tasks/$taskId/move", jsonObjectOf("status" to status))
    }

    /** Opens (creates / joins) the task discussion and marks it read. */
    suspend fun conversation(taskId: Long): TaskConversation =
        TaskConversation.fromJson(api.get("tasks/$taskId/conversation"))

    /** Posts a discussion message: JSON `{body}` or multipart `body` + `attachments[]`. */
    suspend fun sendMessage(context: Context, conversationId: Long, body: String, attachments: List<Uri>) {
        if (attachments.isEmpty()) {
            api.post("chats/$conversationId/messages", jsonObjectOf("body" to body))
        } else {
            api.multipart(
                "chats/$conversationId/messages",
                mapOf("body" to body.ifBlank { null }),
                attachments.map { uri -> UploadPart.fromUri(context, "attachments[]", uri) },
            )
        }
    }

    suspend fun createStage(name: String, color: String) {
        api.post("task-stages", jsonObjectOf("name" to name, "color" to color))
    }

    suspend fun updateStage(stageId: Long, name: String, color: String) {
        api.patch("task-stages/$stageId", jsonObjectOf("name" to name, "color" to color))
    }

    /** Reorders the stages; [stageIds] must contain every stored stage id in the new order. */
    suspend fun moveStages(stageIds: List<Long>): List<TaskStage> =
        api.patch("task-stages/move", jsonObjectOf("stage_ids" to stageIds)).dataObjects().map { TaskStage.fromJson(it) }
}
