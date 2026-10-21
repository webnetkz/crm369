package com.appswebnetkz.crm369.feature.tasks

import com.appswebnetkz.crm369.core.json.arr
import com.appswebnetkz.crm369.core.json.asLong
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import kotlinx.serialization.json.JsonObject
import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter

/*
 * Models of the projects & tasks API (`ProjectsPage` and its parts, task discussions) plus the pure
 * list logic (filters, tree rows, kanban columns, due date conversion). No Android dependencies.
 */

/** `UserMini` / `ChatUser` / `ApiUser` (only the fields that are reliable everywhere). */
data class TaskUser(
    val id: Long,
    val name: String,
    val email: String?,
    val avatarUrl: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): TaskUser {
            val email = json.text("email")
            val fullName = listOfNotNull(json.text("name"), json.text("last_name")).joinToString(" ")
            return TaskUser(
                id = json.long("id") ?: 0L,
                name = fullName.ifBlank { email.orEmpty() },
                email = email,
                avatarUrl = json.text("avatar"),
            )
        }

        fun fromJsonOrNull(json: JsonObject?): TaskUser? =
            if (json == null) null else fromJson(json).takeIf { it.id > 0L }
    }
}

/** `StageOption`: a kanban column; [value] is the stage slug stored in `task.status`. */
data class TaskStage(
    val id: Long?,
    val value: String,
    val label: String,
    val color: String?,
    val isCompleted: Boolean,
    val sortOrder: Int,
) {
    companion object {
        fun fromJson(json: JsonObject): TaskStage {
            val value = json.str("value").orEmpty()
            return TaskStage(
                id = json.long("id"),
                value = value,
                label = json.text("label") ?: value,
                color = json.text("color"),
                isCompleted = json.boolOr("is_completed", false),
                sortOrder = json.int("sort_order") ?: 0,
            )
        }
    }
}

/** `{value, label}` choice (importances). */
data class TaskChoice(val value: String, val label: String)

/** `TaskNode` (workspace / project trees, subtasks of a task). */
data class TaskNode(
    val id: Long,
    val projectId: Long?,
    val projectName: String?,
    val parentTaskId: Long?,
    val parentTaskTitle: String?,
    val title: String,
    val status: String,
    val importance: String,
    val complexity: Int,
    val dueAt: String?,
    val completedAt: String?,
    val createdAt: String?,
    val updatedAt: String?,
    val assignee: TaskUser?,
    val creator: TaskUser?,
    val coAssigneesCount: Int,
    val subtasksCount: Int,
    val subtasks: List<TaskNode>,
) {
    companion object {
        fun fromJson(json: JsonObject): TaskNode {
            val subtasks = json.objects("subtasks").map { fromJson(it) }
            return TaskNode(
                id = json.long("id") ?: 0L,
                projectId = json.long("project_id") ?: json.obj("project").long("id"),
                projectName = json.text("project_name") ?: json.obj("project").text("name"),
                parentTaskId = json.long("parent_task_id"),
                parentTaskTitle = json.text("parent_task_title") ?: json.obj("parent_task").text("title"),
                title = json.str("title").orEmpty(),
                status = json.str("status").orEmpty(),
                importance = json.text("importance") ?: IMPORTANCE_NORMAL,
                complexity = json.int("complexity") ?: DEFAULT_COMPLEXITY,
                dueAt = json.text("due_at"),
                completedAt = json.text("completed_at"),
                createdAt = json.text("created_at"),
                updatedAt = json.text("updated_at"),
                assignee = TaskUser.fromJsonOrNull(json.obj("assignee")),
                creator = TaskUser.fromJsonOrNull(json.obj("creator")),
                coAssigneesCount = json.int("co_assignees_count") ?: json.objects("co_assignees").size,
                subtasksCount = json.int("subtasks_count") ?: subtasks.size,
                subtasks = subtasks,
            )
        }
    }
}

/** `TaskGroup`: standalone tasks or the tasks of one project. */
data class TaskGroup(
    val key: String,
    val isProject: Boolean,
    val title: String,
    val description: String?,
    val projectId: Long?,
    val isArchived: Boolean,
    val tasksCount: Int,
    val openTasksCount: Int,
    val completedTasksCount: Int,
    val tasks: List<TaskNode>,
) {
    companion object {
        fun fromJson(json: JsonObject): TaskGroup {
            val project = json.obj("project")
            val tasks = json.objects("tasks").map { TaskNode.fromJson(it) }
            return TaskGroup(
                key = json.text("key") ?: (project.long("id")?.let { id -> "project-$id" } ?: "standalone"),
                isProject = json.str("kind") == "project" || project != null,
                title = json.text("title") ?: project.text("name").orEmpty(),
                description = json.text("description"),
                projectId = project.long("id"),
                isArchived = project.boolOr("is_archived", false),
                tasksCount = json.int("tasks_count") ?: tasks.size,
                openTasksCount = json.int("open_tasks_count") ?: 0,
                completedTasksCount = json.int("completed_tasks_count") ?: 0,
                tasks = tasks,
            )
        }
    }
}

/** `ProjectListItem`. */
data class ProjectSummary(
    val id: Long,
    val name: String,
    val slug: String?,
    val description: String?,
    val isArchived: Boolean,
    val membersCount: Int,
    val tasksCount: Int,
    val openTasksCount: Int,
    val completedTasksCount: Int,
    val updatedAt: String?,
    val owner: TaskUser?,
) {
    /** Share of completed tasks, 0..1. */
    val progress: Float
        get() = if (tasksCount > 0) (completedTasksCount.toFloat() / tasksCount).coerceIn(0f, 1f) else 0f

    companion object {
        fun fromJson(json: JsonObject): ProjectSummary = ProjectSummary(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            slug = json.text("slug"),
            description = json.text("description"),
            isArchived = json.boolOr("is_archived", false),
            membersCount = json.int("members_count") ?: 0,
            tasksCount = json.int("tasks_count") ?: 0,
            openTasksCount = json.int("open_tasks_count") ?: 0,
            completedTasksCount = json.int("completed_tasks_count") ?: 0,
            updatedAt = json.text("updated_at"),
            owner = TaskUser.fromJsonOrNull(json.obj("owner")),
        )
    }
}

/** `availableProjects[]`: projects a task can be placed in, with their members (assignee pool). */
data class AvailableProject(
    val id: Long,
    val name: String,
    val isArchived: Boolean,
    val members: List<TaskUser>,
) {
    companion object {
        fun fromJson(json: JsonObject): AvailableProject = AvailableProject(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            isArchived = json.boolOr("is_archived", false),
            members = json.objects("members").map { TaskUser.fromJson(it) },
        )
    }
}

/** `can` block of a `ProjectsPage`. */
data class TaskPermissions(
    val createProject: Boolean,
    val createTask: Boolean,
    val manageTaskStages: Boolean,
    val manageProject: Boolean,
    val manageTask: Boolean,
    val workOnActiveProject: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject?): TaskPermissions = TaskPermissions(
            createProject = json.boolOr("createProject", false),
            createTask = json.boolOr("createTask", false),
            manageTaskStages = json.boolOr("manageTaskStages", false),
            manageProject = json.boolOr("manageProject", false),
            manageTask = json.boolOr("manageTask", false),
            workOnActiveProject = json.boolOr("workOnActiveProject", false),
        )
    }
}

/** `ActiveProject`. */
data class ActiveProject(
    val id: Long,
    val name: String,
    val slug: String?,
    val description: String?,
    val isArchived: Boolean,
    val owner: TaskUser?,
    val members: List<TaskUser>,
    val tasks: List<TaskNode>,
) {
    companion object {
        fun fromJson(json: JsonObject): ActiveProject = ActiveProject(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            slug = json.text("slug"),
            description = json.text("description"),
            isArchived = json.boolOr("is_archived", false),
            owner = TaskUser.fromJsonOrNull(json.obj("owner")),
            members = json.objects("members").map { TaskUser.fromJson(it) },
            tasks = json.objects("tasks").map { TaskNode.fromJson(it) },
        )
    }
}

/** `ActiveTask` (task card). */
data class ActiveTask(
    val id: Long,
    val projectId: Long?,
    val projectName: String?,
    val parentTaskId: Long?,
    val parentTaskTitle: String?,
    val title: String,
    val description: String?,
    val status: String,
    val importance: String,
    val complexity: Int,
    val dueAt: String?,
    val completedAt: String?,
    val createdAt: String?,
    val updatedAt: String?,
    val sortOrder: Int,
    val creator: TaskUser?,
    val assignee: TaskUser?,
    val coAssignees: List<TaskUser>,
    val subtasks: List<TaskNode>,
) {
    /** True when [userId] is the creator, the assignee or a co-assignee. */
    fun involves(userId: Long?): Boolean =
        userId != null && (creator?.id == userId || assignee?.id == userId || coAssignees.any { it.id == userId })

    companion object {
        fun fromJson(json: JsonObject): ActiveTask {
            val parent = json.obj("parent_task")
            return ActiveTask(
                id = json.long("id") ?: 0L,
                projectId = json.long("project_id") ?: json.obj("project").long("id"),
                projectName = json.text("project_name") ?: json.obj("project").text("name"),
                parentTaskId = json.long("parent_task_id") ?: parent.long("id"),
                parentTaskTitle = parent.text("title") ?: json.text("parent_task_title"),
                title = json.str("title").orEmpty(),
                description = json.text("description"),
                status = json.str("status").orEmpty(),
                importance = json.text("importance") ?: IMPORTANCE_NORMAL,
                complexity = json.int("complexity") ?: DEFAULT_COMPLEXITY,
                dueAt = json.text("due_at"),
                completedAt = json.text("completed_at"),
                createdAt = json.text("created_at"),
                updatedAt = json.text("updated_at"),
                sortOrder = json.int("sort_order") ?: 0,
                creator = TaskUser.fromJsonOrNull(json.obj("creator")),
                assignee = TaskUser.fromJsonOrNull(json.obj("assignee")),
                coAssignees = json.objects("co_assignees").map { TaskUser.fromJson(it) },
                subtasks = json.objects("subtasks").map { TaskNode.fromJson(it) },
            )
        }
    }
}

/** `ProjectsPage`: the answer of `GET projects/workspace`, `projects`, `projects/{id}` and `tasks/{id}`. */
data class ProjectsPage(
    val projects: List<ProjectSummary>,
    val groups: List<TaskGroup>,
    val activeProject: ActiveProject?,
    val activeTask: ActiveTask?,
    val availableUsers: List<TaskUser>,
    val availableProjects: List<AvailableProject>,
    val can: TaskPermissions,
    val statuses: List<TaskStage>,
    val importances: List<TaskChoice>,
    val complexities: List<Int>,
) {
    fun stage(value: String?): TaskStage? = statuses.firstOrNull { it.value == value }

    fun importanceLabel(value: String): String = importances.firstOrNull { it.value == value }?.label ?: value

    companion object {
        fun fromJson(response: JsonObject): ProjectsPage {
            val json = response.dataObject()
            val options = json.obj("taskOptions")
            val complexities = options.arr("complexity").orEmpty().mapNotNull { it.asLong()?.toInt() }
            return ProjectsPage(
                projects = json.objects("projects").map { ProjectSummary.fromJson(it) },
                groups = json.objects("taskGroups").map { TaskGroup.fromJson(it) },
                activeProject = json.obj("activeProject")?.let { ActiveProject.fromJson(it) },
                activeTask = json.obj("activeTask")?.let { ActiveTask.fromJson(it) },
                availableUsers = json.objects("availableUsers").map { TaskUser.fromJson(it) },
                availableProjects = json.objects("availableProjects").map { AvailableProject.fromJson(it) },
                can = TaskPermissions.fromJson(json.obj("can")),
                statuses = options.objects("statuses").map { TaskStage.fromJson(it) }.filter { it.value.isNotEmpty() },
                importances = options.objects("importances").mapNotNull { item ->
                    val value = item.text("value") ?: return@mapNotNull null
                    TaskChoice(value = value, label = item.text("label") ?: value)
                },
                complexities = complexities.ifEmpty { (MIN_COMPLEXITY..MAX_COMPLEXITY).toList() },
            )
        }
    }
}

/** Attachment of a discussion message (`ChatAttachment`, camelCase). */
data class DiscussionAttachment(
    val id: Long,
    val name: String,
    val mimeType: String?,
    val sizeBytes: Long?,
    val downloadUrl: String,
    val previewUrl: String?,
) {
    val isImage: Boolean
        get() = previewUrl != null && (mimeType == null || mimeType.startsWith("image/"))

    companion object {
        fun fromJson(json: JsonObject): DiscussionAttachment = DiscussionAttachment(
            id = json.long("id") ?: 0L,
            name = json.text("name") ?: json.text("original_name") ?: "file",
            mimeType = json.text("mimeType") ?: json.text("mime_type"),
            sizeBytes = json.long("sizeBytes") ?: json.long("size_bytes"),
            downloadUrl = json.text("downloadUrl") ?: json.text("download_url").orEmpty(),
            previewUrl = json.text("previewUrl") ?: json.text("preview_url"),
        )
    }
}

/** Discussion message (`ChatMessage`, camelCase). */
data class DiscussionMessage(
    val id: Long,
    val body: String,
    val createdAt: String?,
    val isEdited: Boolean,
    val isDeleted: Boolean,
    val isOwn: Boolean,
    val author: TaskUser?,
    val attachments: List<DiscussionAttachment>,
) {
    companion object {
        fun fromJson(json: JsonObject): DiscussionMessage = DiscussionMessage(
            id = json.long("id") ?: 0L,
            body = json.str("body").orEmpty(),
            createdAt = json.text("createdAt") ?: json.text("created_at"),
            isEdited = json.boolOr("isEdited", false),
            isDeleted = json.boolOr("isDeleted", false),
            isOwn = json.boolOr("isOwn", false),
            author = TaskUser.fromJsonOrNull(json.obj("user")),
            attachments = json.objects("attachments").map { DiscussionAttachment.fromJson(it) },
        )
    }
}

/** `ActiveConversation` of type `task` (`GET tasks/{id}/conversation`). */
data class TaskConversation(
    val id: Long,
    val title: String,
    val subtitle: String?,
    val participants: List<TaskUser>,
    val messages: List<DiscussionMessage>,
) {
    companion object {
        fun fromJson(response: JsonObject): TaskConversation {
            val json = response.dataObject().obj("conversation") ?: response.obj("conversation") ?: response.dataObject()
            return TaskConversation(
                id = json.long("id") ?: 0L,
                title = json.str("title").orEmpty(),
                subtitle = json.text("subtitle"),
                participants = json.objects("participants").map { TaskUser.fromJson(it) },
                messages = json.objects("messages").map { DiscussionMessage.fromJson(it) }.distinctBy { it.id },
            )
        }
    }
}

// ---------------------------------------------------------------------------------------------
// List logic

/** Quick filter of the tasks tab (chip order: "assigned to me", "assigned by me", "all"). */
enum class TaskScope { MINE, DELEGATED, ALL }

/** Filter of the workspace list / kanban board. */
data class TaskFilter(
    val scope: TaskScope,
    val userId: Long?,
    val status: String?,
    val query: String,
) {
    val isActive: Boolean
        get() = scope != TaskScope.ALL || status != null || query.isNotBlank()

    fun matches(task: TaskNode, includeStatus: Boolean = true): Boolean {
        val scopeMatches = when (scope) {
            TaskScope.ALL -> true
            TaskScope.MINE -> userId != null && task.assignee?.id == userId
            TaskScope.DELEGATED -> userId != null && task.creator?.id == userId
        }
        val statusMatches = !includeStatus || status == null || task.status == status
        val text = query.trim()
        val queryMatches = text.isEmpty() || task.title.contains(text, ignoreCase = true)
        return scopeMatches && statusMatches && queryMatches
    }

    companion object {
        val NONE = TaskFilter(scope = TaskScope.ALL, userId = null, status = null, query = "")
    }
}

/** One visible row of a task list ([depth] > 0 for expanded subtasks). */
data class TaskRowItem(
    val task: TaskNode,
    val depth: Int,
    val hasChildren: Boolean,
    val expanded: Boolean,
    val showParent: Boolean,
)

/** A group of the workspace list with its visible rows. */
data class TaskSection(val group: TaskGroup, val rows: List<TaskRowItem>)

/** Kanban column: a stage and its (filtered) tasks. */
data class KanbanColumn(val stage: TaskStage, val tasks: List<TaskNode>)

/** Tasks of a tree in pre-order (parents before their subtasks), each task once. */
fun flattenTasks(nodes: List<TaskNode>): List<TaskNode> {
    val result = ArrayList<TaskNode>()
    val seen = HashSet<Long>()
    fun visit(node: TaskNode) {
        if (!seen.add(node.id)) return
        result.add(node)
        node.subtasks.forEach { visit(it) }
    }
    nodes.forEach { visit(it) }
    return result
}

/**
 * Rows of a task tree: without an active filter the tree is shown (subtasks of [expandedIds]
 * are visible, indented); with a filter every matching task (subtasks included) is listed flat.
 */
fun buildTaskRows(roots: List<TaskNode>, filter: TaskFilter, expandedIds: Set<Long>): List<TaskRowItem> {
    if (filter.isActive) {
        return flattenTasks(roots)
            .filter { filter.matches(it) }
            .map { TaskRowItem(task = it, depth = 0, hasChildren = false, expanded = false, showParent = true) }
    }
    val rows = ArrayList<TaskRowItem>()
    val seen = HashSet<Long>()
    fun add(node: TaskNode, depth: Int) {
        if (!seen.add(node.id)) return
        val expanded = node.id in expandedIds
        rows.add(
            TaskRowItem(
                task = node,
                depth = depth,
                hasChildren = node.subtasks.isNotEmpty(),
                expanded = expanded,
                showParent = false,
            ),
        )
        if (expanded) node.subtasks.forEach { add(it, depth + 1) }
    }
    roots.forEach { add(it, 0) }
    return rows
}

/** Sections of the workspace list; with an active filter empty groups are hidden. Rows are unique. */
fun buildSections(groups: List<TaskGroup>, filter: TaskFilter, expandedIds: Set<Long>): List<TaskSection> {
    val seen = HashSet<Long>()
    return groups
        .map { group -> TaskSection(group, buildTaskRows(group.tasks, filter, expandedIds).filter { seen.add(it.task.id) }) }
        .filter { !filter.isActive || it.rows.isNotEmpty() }
}

/** Kanban columns = stages (board order); every task (subtasks included) in the column of its status. */
fun buildKanban(statuses: List<TaskStage>, groups: List<TaskGroup>, filter: TaskFilter): List<KanbanColumn> {
    val byStatus = flattenTasks(groups.flatMap { it.tasks })
        .filter { filter.matches(it, includeStatus = false) }
        .groupBy { it.status }
    return statuses.map { stage -> KanbanColumn(stage, byStatus[stage.value].orEmpty()) }
}

// ---------------------------------------------------------------------------------------------
// Dates

private val FORM_DATE_TIME: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm")

/** Parses an ISO-8601 date-time (with offset or `Z`); null when blank or invalid. */
fun parseInstant(value: String?): Instant? {
    val text = value?.trim().orEmpty()
    if (text.isEmpty()) return null
    return try {
        OffsetDateTime.parse(text).toInstant()
    } catch (_: Exception) {
        try {
            Instant.parse(text)
        } catch (_: Exception) {
            null
        }
    }
}

/** True when the task has a due date in the past and is not completed. */
fun isOverdue(dueAt: String?, completed: Boolean, now: Instant = Instant.now()): Boolean {
    if (completed) return false
    val due = parseInstant(dueAt) ?: return false
    return due.isBefore(now)
}

/** API due date (ISO UTC) → form value `YYYY-MM-DD HH:MM` in [zone]. */
fun isoToFormDateTime(value: String?, zone: ZoneId = ZoneId.systemDefault()): String? =
    parseInstant(value)?.atZone(zone)?.format(FORM_DATE_TIME)

/** Form value `YYYY-MM-DD HH:MM` (local time in [zone]) → ISO-8601 with offset; null when blank. */
fun formDateTimeToIso(value: String?, zone: ZoneId = ZoneId.systemDefault()): String? {
    val text = value?.trim().orEmpty()
    if (text.isEmpty()) return null
    val local = try {
        LocalDateTime.parse(text.replace(' ', 'T'))
    } catch (_: Exception) {
        try {
            LocalDate.parse(text.take(10)).atTime(END_OF_DAY_HOUR, END_OF_DAY_MINUTE)
        } catch (_: Exception) {
            return null
        }
    }
    return local.atZone(zone).toOffsetDateTime().format(DateTimeFormatter.ISO_OFFSET_DATE_TIME)
}

const val IMPORTANCE_LOW = "low"
const val IMPORTANCE_NORMAL = "normal"
const val IMPORTANCE_HIGH = "high"
const val IMPORTANCE_CRITICAL = "critical"
const val DEFAULT_COMPLEXITY = 5
const val MIN_COMPLEXITY = 1
const val MAX_COMPLEXITY = 10
private const val END_OF_DAY_HOUR = 23
private const val END_OF_DAY_MINUTE = 59
