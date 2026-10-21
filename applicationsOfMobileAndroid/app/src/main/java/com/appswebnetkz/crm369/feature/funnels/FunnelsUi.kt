package com.appswebnetkz.crm369.feature.funnels

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.ServerColors
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Small colored circle (stage or funnel color). */
@Composable
internal fun ColorDot(color: String?, modifier: Modifier = Modifier, size: Dp = 10.dp) {
    Box(
        modifier = modifier
            .size(size)
            .clip(CircleShape)
            .background(serverColor(color)),
    )
}

/** Server color ("#2563EB") with the theme outline color as fallback. */
@Composable
internal fun serverColor(color: String?): Color = ServerColors.parse(color, MaterialTheme.colorScheme.outline)

/** Localized stage type (open / won / lost); unknown types use the server label. */
@Composable
internal fun stageTypeLabel(type: String, serverOptions: List<ValueLabel> = emptyList()): String = when (type) {
    StageRef.TYPE_OPEN -> stringResource(R.string.funnels_stage_type_open)
    StageRef.TYPE_WON -> stringResource(R.string.funnels_stage_type_won)
    StageRef.TYPE_LOST -> stringResource(R.string.funnels_stage_type_lost)
    else -> serverOptions.firstOrNull { it.value == type }?.label ?: type
}

/** Accent color of a stage type. */
@Composable
internal fun stageTypeColor(type: String): Color = when (type) {
    StageRef.TYPE_WON -> LocalExtendedColors.current.success
    StageRef.TYPE_LOST -> MaterialTheme.colorScheme.error
    else -> MaterialTheme.colorScheme.primary
}

/** Localized custom field type; unknown types use the server label. */
@Composable
internal fun fieldTypeLabel(type: String, serverOptions: List<ValueLabel> = emptyList()): String = when (type) {
    DealField.TYPE_TEXT -> stringResource(R.string.funnels_field_type_text)
    DealField.TYPE_TEXTAREA -> stringResource(R.string.funnels_field_type_textarea)
    DealField.TYPE_NUMBER -> stringResource(R.string.funnels_field_type_number)
    DealField.TYPE_DATE -> stringResource(R.string.funnels_field_type_date)
    else -> serverOptions.firstOrNull { it.value == type }?.label ?: type
}
