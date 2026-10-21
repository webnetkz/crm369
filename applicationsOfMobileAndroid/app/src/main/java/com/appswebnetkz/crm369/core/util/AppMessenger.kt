package com.appswebnetkz.crm369.core.util

import android.content.Context
import androidx.annotation.StringRes
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.core.network.NetworkException
import com.appswebnetkz.crm369.core.portal.PortalDiscoveryException
import com.appswebnetkz.crm369.core.scan.BarcodeScannerUnavailableException
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

/**
 * Application-wide snackbar messages. Any screen can call [show] / [showError]; the root
 * composable displays them above the current screen.
 */
class AppMessenger(private val context: Context) {

    private val events = MutableSharedFlow<String>(extraBufferCapacity = 16)
    val messages: SharedFlow<String> = events.asSharedFlow()

    fun show(message: String?) {
        if (!message.isNullOrBlank()) events.tryEmit(message)
    }

    fun show(@StringRes messageRes: Int) {
        events.tryEmit(context.getString(messageRes))
    }

    fun showError(error: Throwable) {
        show(describeError(context, error))
    }
}

/** A human readable, localized description of an error thrown by the API layer. */
fun describeError(context: Context, error: Throwable): String = when (error) {
    is ApiException -> when {
        error.isValidation -> error.firstMessage().ifBlank { context.getString(R.string.error_validation) }
        error.isUnauthorized -> context.getString(R.string.error_session_expired)
        error.isForbidden -> error.message.ifBlank { context.getString(R.string.error_forbidden) }
            .let { if (it == "This action is unauthorized.") context.getString(R.string.error_forbidden) else it }
        error.isNotFound -> context.getString(R.string.error_not_found)
        error.isRateLimited -> context.getString(R.string.error_rate_limited)
        error.isServerError -> context.getString(R.string.error_server, error.status)
        else -> error.message.ifBlank { context.getString(R.string.error_unknown_status, error.status) }
    }
    is NetworkException -> context.getString(R.string.error_network)
    is PortalDiscoveryException -> context.getString(
        when (error.reason) {
            PortalDiscoveryException.Reason.InvalidAddress -> R.string.portal_error_invalid_address
            PortalDiscoveryException.Reason.Unreachable -> R.string.portal_error_unreachable
            PortalDiscoveryException.Reason.NotCrm369 -> R.string.portal_error_not_crm369
            PortalDiscoveryException.Reason.UnsupportedVersion -> R.string.portal_error_unsupported
            PortalDiscoveryException.Reason.ServerError -> R.string.portal_error_server
        },
    )
    is BarcodeScannerUnavailableException -> context.getString(R.string.error_scanner_unavailable)
    else -> error.message?.takeIf { it.isNotBlank() } ?: context.getString(R.string.error_unknown)
}
