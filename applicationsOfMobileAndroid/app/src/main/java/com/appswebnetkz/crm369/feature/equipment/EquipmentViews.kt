package com.appswebnetkz.crm369.feature.equipment

import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Color of the equipment stage chip. */
@Composable
internal fun equipmentStatusColor(status: String): Color {
    val extended = LocalExtendedColors.current
    return when (status) {
        EquipmentItem.STATUS_ON_BALANCE -> extended.success
        EquipmentItem.STATUS_ISSUED -> extended.info
        EquipmentItem.STATUS_MAINTENANCE -> extended.warning
        EquipmentItem.STATUS_REPAIR -> extended.accent
        EquipmentItem.STATUS_WRITTEN_OFF -> MaterialTheme.colorScheme.onSurfaceVariant
        else -> MaterialTheme.colorScheme.primary
    }
}

/** Local stage names, used when `status_options` could not be loaded. */
@Composable
internal fun fallbackEquipmentStatuses(): List<Option> = listOf(
    Option(EquipmentItem.STATUS_ON_BALANCE, stringResource(R.string.equipment_status_on_balance)),
    Option(EquipmentItem.STATUS_ISSUED, stringResource(R.string.equipment_status_issued)),
    Option(EquipmentItem.STATUS_MAINTENANCE, stringResource(R.string.equipment_status_maintenance)),
    Option(EquipmentItem.STATUS_REPAIR, stringResource(R.string.equipment_status_repair)),
    Option(EquipmentItem.STATUS_WRITTEN_OFF, stringResource(R.string.equipment_status_written_off)),
)

/** Copies a QR code to the clipboard and confirms with a snackbar. */
@Composable
internal fun rememberEquipmentCopy(): (String) -> Unit {
    val clipboard = LocalClipboardManager.current
    val messenger = LocalMessenger.current
    val copied = stringResource(R.string.equipment_qr_copied)
    return remember(clipboard, messenger, copied) {
        { text: String ->
            clipboard.setText(AnnotatedString(text))
            messenger.show(copied)
        }
    }
}
