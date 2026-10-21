package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.core.util.ServerColors
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.theme.AppIcons
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Column color of a stage (server `#RRGGBB`), primary when unknown. */
@Composable
internal fun stageColor(stage: TaskStage?): Color =
    ServerColors.parse(stage?.color, MaterialTheme.colorScheme.primary)

/** Color of an importance level: critical → error, high → warning, normal → primary, low → outline. */
@Composable
internal fun importanceColor(importance: String): Color = when (importance) {
    IMPORTANCE_CRITICAL -> MaterialTheme.colorScheme.error
    IMPORTANCE_HIGH -> LocalExtendedColors.current.warning
    IMPORTANCE_LOW -> MaterialTheme.colorScheme.outline
    else -> MaterialTheme.colorScheme.primary
}

/** Stage chip in the stage color; clickable when [onClick] is set (quick status change). */
@Composable
internal fun TaskStatusChip(stage: TaskStage?, fallback: String, modifier: Modifier = Modifier, onClick: (() -> Unit)? = null) {
    val clickModifier = if (onClick != null) modifier.clickable(onClick = onClick) else modifier
    StatusChip(text = stage?.label ?: fallback, modifier = clickModifier, color = stageColor(stage))
}

/** Small colored dot (stage color in tabs and the stage list). */
@Composable
internal fun ColorDot(color: Color, modifier: Modifier = Modifier, diameter: Int = 10) {
    Box(
        modifier = modifier
            .size(diameter.dp)
            .clip(CircleShape)
            .background(color),
    )
}

/**
 * Task row of lists and trees: importance bar, title (struck through when done), stage chip,
 * due date (error color when overdue), subtasks count and the assignee avatar.
 */
@Composable
internal fun TaskRow(
    task: TaskNode,
    stage: TaskStage?,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    depth: Int = 0,
    overline: String? = null,
    hasChildren: Boolean = false,
    expanded: Boolean = false,
    onToggle: (() -> Unit)? = null,
) {
    val completed = task.completedAt != null || stage?.isCompleted == true
    val overdue = isOverdue(task.dueAt, completed)
    val assignee = task.assignee
    Row(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(start = 16.dp + (depth * 20).dp, end = 8.dp, top = 10.dp, bottom = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .width(4.dp)
                .height(40.dp)
                .clip(RoundedCornerShape(2.dp))
                .background(importanceColor(task.importance)),
        )
        Spacer(Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            if (overline != null) {
                Text(
                    text = overline,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            Text(
                text = task.title,
                style = MaterialTheme.typography.bodyLarge,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
                textDecoration = if (completed) TextDecoration.LineThrough else null,
            )
            Spacer(Modifier.height(4.dp))
            TaskMetaRow(task = task, stage = stage, overdue = overdue)
        }
        if (assignee != null) {
            Spacer(Modifier.width(8.dp))
            Avatar(name = assignee.name, imageUrl = assignee.avatarUrl, size = 32.dp)
        }
        if (hasChildren && onToggle != null) {
            IconButton(onClick = onToggle) {
                Icon(
                    imageVector = if (expanded) AppIcons.ExpandLess else AppIcons.ExpandMore,
                    contentDescription = stringResource(if (expanded) R.string.tasks_collapse_subtasks else R.string.tasks_expand_subtasks),
                )
            }
        } else {
            Spacer(Modifier.width(8.dp))
        }
    }
}

/** Stage chip, due date and subtasks count of a task. */
@Composable
internal fun TaskMetaRow(task: TaskNode, stage: TaskStage?, overdue: Boolean, showStage: Boolean = true) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        if (showStage) {
            TaskStatusChip(stage = stage, fallback = task.status)
        }
        if (task.dueAt != null) {
            val dueColor = if (overdue) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(AppIcons.Schedule, contentDescription = null, tint = dueColor, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(2.dp))
                Text(
                    text = Formatters.shortStamp(task.dueAt),
                    style = MaterialTheme.typography.labelMedium,
                    color = dueColor,
                    maxLines = 1,
                )
            }
        }
        if (task.subtasksCount > 0) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    TasksIcons.Subtasks,
                    contentDescription = stringResource(R.string.tasks_subtasks),
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(14.dp),
                )
                Spacer(Modifier.width(2.dp))
                Text(
                    text = task.subtasksCount.toString(),
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

/** A person with a role label (creator, assignee, owner …); [emptyText] when there is nobody. */
@Composable
internal fun PersonRow(label: String, user: TaskUser?, emptyText: String? = null) {
    if (user == null && emptyText == null) return
    ListRow(
        title = user?.name ?: emptyText.orEmpty(),
        overline = label,
        subtitle = user?.email,
        leading = { Avatar(name = user?.name, imageUrl = user?.avatarUrl, size = 36.dp) },
    )
}

/** Avatar with the name below (member strips). */
@Composable
internal fun MemberChip(user: TaskUser, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier.width(72.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Avatar(name = user.name, imageUrl = user.avatarUrl, size = 40.dp)
        Spacer(Modifier.height(4.dp))
        Text(
            text = user.name,
            style = MaterialTheme.typography.labelSmall,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
    }
}
