package com.appswebnetkz.crm369.feature.conferences

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.components.StatusChip
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.theme.LocalExtendedColors

/** Status chip: live / scheduled / ended. */
@Composable
internal fun ConferenceStatusChip(status: String) {
    val extended = LocalExtendedColors.current
    when (status) {
        ConferenceItem.STATUS_LIVE -> StatusChip(
            text = stringResource(R.string.conferences_status_live),
            color = extended.success,
        )
        ConferenceItem.STATUS_ENDED -> StatusChip(
            text = stringResource(R.string.conferences_status_ended),
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        else -> StatusChip(
            text = stringResource(R.string.conferences_status_scheduled),
            color = extended.info,
        )
    }
}

@Composable
internal fun conferenceStatusLabel(status: String): String = when (status) {
    ConferenceItem.STATUS_LIVE -> stringResource(R.string.conferences_status_live)
    ConferenceItem.STATUS_ENDED -> stringResource(R.string.conferences_status_ended)
    else -> stringResource(R.string.conferences_status_scheduled)
}

/** Copies a link to the clipboard and confirms with a snackbar. */
@Composable
internal fun rememberConferenceCopy(): (String) -> Unit {
    val clipboard = LocalClipboardManager.current
    val messenger = LocalMessenger.current
    val copied = stringResource(R.string.conferences_copy_link_success)
    return remember(clipboard, messenger, copied) {
        { text: String ->
            clipboard.setText(AnnotatedString(text))
            messenger.show(copied)
        }
    }
}

/** System share sheet for a plain-text link. */
internal fun shareConferenceLink(context: Context, link: String, subject: String?) {
    val send = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_TEXT, link)
        if (!subject.isNullOrBlank()) putExtra(Intent.EXTRA_SUBJECT, subject)
    }
    val chooser = Intent.createChooser(send, null).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    try {
        context.startActivity(chooser)
    } catch (exception: ActivityNotFoundException) {
        // No app can share plain text: nothing to do, the link can still be copied.
    }
}
