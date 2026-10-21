package com.appswebnetkz.crm369.feature.tasks

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ArrowDownward
import androidx.compose.material.icons.outlined.ArrowUpward
import androidx.compose.material.icons.outlined.Layers
import androidx.compose.material.icons.outlined.SwapHoriz
import androidx.compose.material.icons.outlined.ViewColumn
import androidx.compose.ui.graphics.vector.ImageVector

/** Icons of the tasks module that are not part of AppIcons. */
internal object TasksIcons {
    val Kanban: ImageVector = Icons.Outlined.ViewColumn
    val Subtasks: ImageVector = Icons.Outlined.Layers
    val Move: ImageVector = Icons.Outlined.SwapHoriz
    val MoveUp: ImageVector = Icons.Outlined.ArrowUpward
    val MoveDown: ImageVector = Icons.Outlined.ArrowDownward
}
