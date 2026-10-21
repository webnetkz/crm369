package com.appswebnetkz.crm369.ui.onboarding

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.core.portal.DomainNormalizer
import com.appswebnetkz.crm369.core.portal.PortalInfo
import com.appswebnetkz.crm369.core.session.LoginResult
import com.appswebnetkz.crm369.core.util.describeError
import com.appswebnetkz.crm369.ui.components.PortalLogo
import com.appswebnetkz.crm369.ui.navigation.AppRoutes
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

/** Sign in to a portal with e-mail and password, then (optionally) a two-factor code. */
@Composable
fun LoginScreen(baseUrl: String, initialEmail: String?) {
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val context = LocalContext.current
    val focusManager = LocalFocusManager.current
    val scope = rememberCoroutineScope()

    var portal by remember(baseUrl) { mutableStateOf(container.session.cachedPortal(baseUrl)) }
    var portalError by remember { mutableStateOf<String?>(null) }

    var email by rememberSaveable { mutableStateOf(initialEmail.orEmpty()) }
    var password by rememberSaveable { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    var challenge by rememberSaveable { mutableStateOf<String?>(null) }
    var code by rememberSaveable { mutableStateOf("") }
    var useRecoveryCode by rememberSaveable { mutableStateOf(false) }

    var busy by remember { mutableStateOf(false) }
    var emailError by remember { mutableStateOf<String?>(null) }
    var passwordError by remember { mutableStateOf<String?>(null) }
    var codeError by remember { mutableStateOf<String?>(null) }
    var generalError by remember { mutableStateOf<String?>(null) }
    var showSetupTwoFactor by remember { mutableStateOf(false) }

    LaunchedEffect(baseUrl) {
        if (portal == null) {
            try {
                portal = container.session.discover(baseUrl)
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                portalError = describeError(context, exception)
            }
        }
    }

    fun handleResult(result: LoginResult) {
        when (result) {
            is LoginResult.Success -> navigator.resetToHome()
            is LoginResult.TwoFactorRequired -> {
                challenge = result.challenge
                code = ""
                codeError = null
            }
        }
    }

    fun handleError(exception: Exception) {
        if (exception is ApiException) {
            when {
                exception.code == "email_not_verified" -> generalError = context.getString(R.string.login_error_email_not_verified)
                exception.code == "two_factor_setup_required" -> {
                    generalError = context.getString(R.string.login_error_two_factor_setup)
                    showSetupTwoFactor = true
                }
                exception.isValidation -> {
                    emailError = exception.firstError("email")
                    passwordError = exception.firstError("password")
                    codeError = exception.firstError("code") ?: exception.firstError("recovery_code")
                    val challengeError = exception.firstError("challenge")
                    if (challengeError != null) {
                        challenge = null
                        generalError = challengeError
                    } else if (emailError == null && passwordError == null && codeError == null) {
                        generalError = exception.firstMessage()
                    }
                }
                exception.isRateLimited -> generalError = context.getString(R.string.error_rate_limited)
                else -> generalError = describeError(context, exception)
            }
        } else {
            generalError = describeError(context, exception)
        }
    }

    fun submit() {
        val currentPortal = portal ?: return
        if (busy) return
        emailError = null
        passwordError = null
        codeError = null
        generalError = null
        showSetupTwoFactor = false
        val currentChallenge = challenge
        if (currentChallenge == null) {
            if (email.isBlank()) emailError = context.getString(R.string.form_required)
            if (password.isEmpty()) passwordError = context.getString(R.string.form_required)
            if (emailError != null || passwordError != null) return
        } else if (code.isBlank()) {
            codeError = context.getString(R.string.form_required)
            return
        }
        focusManager.clearFocus()
        busy = true
        scope.launch {
            try {
                val result = if (currentChallenge == null) {
                    container.session.login(currentPortal, email, password)
                } else if (useRecoveryCode) {
                    container.session.completeTwoFactor(currentPortal, currentChallenge, code = null, recoveryCode = code)
                } else {
                    container.session.completeTwoFactor(currentPortal, currentChallenge, code = code, recoveryCode = null)
                }
                handleResult(result)
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                handleError(exception)
            } finally {
                busy = false
            }
        }
    }

    Scaffold { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .imePadding()
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Row(modifier = Modifier.fillMaxWidth()) {
                IconButton(onClick = { if (!navigator.back()) navigator.resetToPortal() }) {
                    Icon(AppIcons.Back, contentDescription = stringResource(R.string.action_back))
                }
            }
            Column(
                modifier = Modifier
                    .widthIn(max = 480.dp)
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                val currentPortal = portal
                PortalHeader(portal = currentPortal, baseUrl = baseUrl)

                if (currentPortal == null) {
                    if (portalError != null) {
                        ErrorBanner(portalError.orEmpty())
                        OutlinedButton(onClick = { navigator.resetToPortal() }) {
                            Text(stringResource(R.string.login_change_portal))
                        }
                    } else {
                        CircularProgressIndicator(modifier = Modifier.padding(24.dp))
                    }
                } else {
                    val currentChallenge = challenge
                    if (currentChallenge == null) {
                        Text(
                            text = stringResource(R.string.login_title),
                            style = MaterialTheme.typography.titleLarge,
                        )
                        OutlinedTextField(
                            value = email,
                            onValueChange = {
                                email = it
                                emailError = null
                            },
                            label = { Text(stringResource(R.string.login_email)) },
                            leadingIcon = { Icon(AppIcons.Email, contentDescription = null) },
                            singleLine = true,
                            enabled = !busy,
                            isError = emailError != null,
                            supportingText = messageContent(emailError),
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next),
                            modifier = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value = password,
                            onValueChange = {
                                password = it
                                passwordError = null
                            },
                            label = { Text(stringResource(R.string.login_password)) },
                            leadingIcon = { Icon(AppIcons.Lock, contentDescription = null) },
                            trailingIcon = {
                                IconButton(onClick = { passwordVisible = !passwordVisible }) {
                                    Icon(
                                        if (passwordVisible) AppIcons.VisibilityOff else AppIcons.Visibility,
                                        contentDescription = stringResource(R.string.login_show_password),
                                    )
                                }
                            },
                            singleLine = true,
                            enabled = !busy,
                            isError = passwordError != null,
                            supportingText = messageContent(passwordError),
                            visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done),
                            keyboardActions = KeyboardActions(onDone = { submit() }),
                            modifier = Modifier.fillMaxWidth(),
                        )
                    } else {
                        Text(
                            text = stringResource(R.string.login_two_factor_title),
                            style = MaterialTheme.typography.titleLarge,
                        )
                        Text(
                            text = stringResource(
                                if (useRecoveryCode) R.string.login_two_factor_recovery_hint else R.string.login_two_factor_hint,
                            ),
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            textAlign = TextAlign.Center,
                        )
                        OutlinedTextField(
                            value = code,
                            onValueChange = { value ->
                                code = if (useRecoveryCode) value.trim() else value.filter { it.isDigit() }.take(6)
                                codeError = null
                            },
                            label = {
                                Text(stringResource(if (useRecoveryCode) R.string.login_recovery_code else R.string.login_code))
                            },
                            singleLine = true,
                            enabled = !busy,
                            isError = codeError != null,
                            supportingText = messageContent(codeError),
                            keyboardOptions = KeyboardOptions(
                                keyboardType = if (useRecoveryCode) KeyboardType.Text else KeyboardType.NumberPassword,
                                imeAction = ImeAction.Done,
                            ),
                            keyboardActions = KeyboardActions(onDone = { submit() }),
                            modifier = Modifier.fillMaxWidth(),
                        )
                        TextButton(
                            onClick = {
                                useRecoveryCode = !useRecoveryCode
                                code = ""
                                codeError = null
                            },
                        ) {
                            Text(
                                stringResource(
                                    if (useRecoveryCode) R.string.login_use_code else R.string.login_use_recovery_code,
                                ),
                            )
                        }
                    }

                    val error = generalError
                    if (error != null) {
                        ErrorBanner(error)
                    }
                    if (showSetupTwoFactor) {
                        OutlinedButton(onClick = { navigator.openUrl(currentPortal.baseUrl) }) {
                            Text(stringResource(R.string.login_open_portal_in_browser))
                        }
                    }

                    Button(
                        onClick = { submit() },
                        enabled = !busy,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(52.dp),
                    ) {
                        if (busy) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                strokeWidth = 2.dp,
                                color = MaterialTheme.colorScheme.onPrimary,
                            )
                        } else {
                            Text(
                                stringResource(if (currentChallenge == null) R.string.login_submit else R.string.login_confirm),
                            )
                        }
                    }

                    if (currentChallenge != null) {
                        TextButton(
                            onClick = {
                                challenge = null
                                code = ""
                            },
                        ) { Text(stringResource(R.string.login_back_to_password)) }
                    } else {
                        TextButton(onClick = { navigator.openWebPasswordReset(currentPortal) }) {
                            Text(stringResource(R.string.login_forgot_password))
                        }
                        TextButton(onClick = { navigator.open(AppRoutes.PORTAL) }) {
                            Text(stringResource(R.string.login_change_portal))
                        }
                    }
                }
                Spacer(Modifier.height(24.dp))
            }
        }
    }
}

private fun messageContent(message: String?): (@Composable () -> Unit)? {
    if (message == null) return null
    val content: @Composable () -> Unit = { Text(message) }
    return content
}

@Composable
private fun PortalHeader(portal: PortalInfo?, baseUrl: String) {
    val name = portal?.name ?: DomainNormalizer.hostOf(baseUrl)
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        PortalLogo(name = name, logoUrl = portal?.logoUrl, size = 72.dp)
        Spacer(Modifier.height(12.dp))
        Text(text = name, style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center)
        Text(
            text = DomainNormalizer.hostOf(portal?.baseUrl ?: baseUrl),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun ErrorBanner(message: String) {
    Surface(
        color = MaterialTheme.colorScheme.errorContainer,
        contentColor = MaterialTheme.colorScheme.onErrorContainer,
        shape = MaterialTheme.shapes.medium,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(AppIcons.Error, contentDescription = null)
            Spacer(Modifier.width(12.dp))
            Text(message, style = MaterialTheme.typography.bodyMedium)
        }
    }
}

/** Password reset happens in the web application (Fortify), opened in the system browser. */
private fun com.appswebnetkz.crm369.ui.navigation.AppNavigator.openWebPasswordReset(portal: PortalInfo) {
    openUrl(portal.baseUrl.trimEnd('/') + "/forgot-password")
}
