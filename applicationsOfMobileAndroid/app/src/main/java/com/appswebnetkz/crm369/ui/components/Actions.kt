package com.appswebnetkz.crm369.ui.components

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Snackbar
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.core.util.AppMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch

/**
 * Runs mutations (create / update / delete calls) from UI events.
 *
 * - [isBusy] is true while an action runs (disable buttons, show progress),
 * - errors are shown in the snackbar unless [execute]'s `onError` returns true (handled),
 * - [execute]'s `successMessage` is shown after success.
 *
 * ```
 * val runner = rememberActionRunner()
 * runner.execute(successMessage = saved, onSuccess = { navigator.back() }) {
 *     api.delete("contacts/$id")
 * }
 * ```
 */
@Stable
class ActionRunner internal constructor(
    private val scope: CoroutineScope,
    private val messenger: AppMessenger,
) {
    var isBusy: Boolean by mutableStateOf(false)
        private set

    fun <R> execute(
        successMessage: String? = null,
        onError: (Throwable) -> Boolean = { false },
        onSuccess: (R) -> Unit = {},
        action: suspend () -> R,
    ) {
        if (isBusy) return
        isBusy = true
        scope.launch {
            try {
                val result = action()
                isBusy = false
                if (successMessage != null) messenger.show(successMessage)
                onSuccess(result)
            } catch (exception: CancellationException) {
                isBusy = false
                throw exception
            } catch (exception: Throwable) {
                isBusy = false
                if (!onError(exception)) {
                    messenger.showError(exception)
                }
            }
        }
    }
}

@Composable
fun rememberActionRunner(): ActionRunner {
    val scope = rememberCoroutineScope()
    val messenger = LocalMessenger.current
    return remember(scope, messenger) { ActionRunner(scope, messenger) }
}

/** Shows [AppMessenger] messages as snackbars at the bottom of the screen. */
@Composable
fun AppSnackbarHost(messenger: AppMessenger, modifier: Modifier = Modifier) {
    val hostState = remember { SnackbarHostState() }
    LaunchedEffect(messenger) {
        messenger.messages.collect { message ->
            hostState.currentSnackbarData?.dismiss()
            launch { hostState.showSnackbar(message) }
        }
    }
    Box(modifier = modifier.fillMaxWidth(), contentAlignment = Alignment.BottomCenter) {
        SnackbarHost(
            hostState = hostState,
            modifier = Modifier
                .navigationBarsPadding()
                .padding(bottom = 80.dp, start = 12.dp, end = 12.dp),
        ) { data ->
            Snackbar(snackbarData = data)
        }
    }
}
