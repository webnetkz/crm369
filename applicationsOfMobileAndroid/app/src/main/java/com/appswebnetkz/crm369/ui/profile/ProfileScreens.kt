package com.appswebnetkz.crm369.ui.profile

import android.content.Intent
import android.net.Uri
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.appswebnetkz.crm369.BuildConfig
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.toMultipartFields
import com.appswebnetkz.crm369.core.network.UploadPart
import com.appswebnetkz.crm369.core.push.NotificationChannels
import com.appswebnetkz.crm369.core.session.UserSummary
import com.appswebnetkz.crm369.core.settings.AppLanguage
import com.appswebnetkz.crm369.core.settings.ThemeMode
import com.appswebnetkz.crm369.ui.components.Avatar
import com.appswebnetkz.crm369.ui.components.ConfirmDialog
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.components.ListRow
import com.appswebnetkz.crm369.ui.components.LoadableContent
import com.appswebnetkz.crm369.ui.components.rememberLoadable
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.AppRoutes
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.navigation.rememberBootstrap
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.launch

/** The signed in user's profile with links to settings, security and sign out. */
@Composable
fun ProfileScreen() {
    val api = rememberApi()
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val scope = rememberCoroutineScope()
    var confirmLogout by remember { mutableStateOf(false) }
    val profile = rememberLoadable(api.baseUrl) {
        UserSummary.fromJson(api.get("profile").dataObject())
    }

    CrmScaffold(
        title = stringResource(R.string.profile_title),
        actions = {
            androidx.compose.material3.IconButton(onClick = { navigator.open(AppRoutes.PROFILE_EDIT) }) {
                Icon(AppIcons.Edit, contentDescription = stringResource(R.string.action_edit))
            }
        },
    ) { padding ->
        LoadableContent(loadable = profile, modifier = Modifier.padding(padding)) { user ->
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState()),
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Avatar(name = user.fullName, imageUrl = user.avatarUrl, size = 96.dp)
                    Spacer(Modifier.height(12.dp))
                    Text(user.fullName, style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
                    val subtitle = listOfNotNull(user.position, user.groupName).joinToString(" · ")
                    if (subtitle.isNotBlank()) {
                        Text(
                            subtitle,
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            textAlign = TextAlign.Center,
                        )
                    }
                }
                InfoCard(title = stringResource(R.string.profile_contacts)) {
                    KeyValueRow(
                        label = stringResource(R.string.profile_email),
                        value = user.email,
                        icon = AppIcons.Email,
                        onClick = { navigator.email(user.email) },
                    )
                    KeyValueRow(
                        label = stringResource(R.string.profile_phone),
                        value = user.phone,
                        icon = AppIcons.Phone,
                        onClick = user.phone?.let { phone -> { navigator.dial(phone) } },
                    )
                    KeyValueRow(label = stringResource(R.string.profile_middle_name), value = user.middleName, icon = AppIcons.Person)
                }
                InfoCard {
                    ListRow(
                        title = stringResource(R.string.profile_change_password),
                        leading = { Icon(AppIcons.Key, contentDescription = null) },
                        onClick = { navigator.open(AppRoutes.PROFILE_PASSWORD) },
                    )
                    ListRow(
                        title = stringResource(R.string.profile_security),
                        subtitle = stringResource(R.string.profile_security_hint),
                        leading = { Icon(AppIcons.Security, contentDescription = null) },
                        onClick = { navigator.openWeb("/settings/security", null) },
                    )
                    ListRow(
                        title = stringResource(R.string.settings_title),
                        leading = { Icon(AppIcons.Settings, contentDescription = null) },
                        onClick = { navigator.open(AppRoutes.SETTINGS) },
                    )
                    ListRow(
                        title = stringResource(R.string.accounts_title),
                        leading = { Icon(AppIcons.Portal, contentDescription = null) },
                        onClick = { navigator.open(AppRoutes.ACCOUNTS) },
                    )
                }
                OutlinedButton(
                    onClick = { confirmLogout = true },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                ) {
                    Icon(AppIcons.Logout, contentDescription = null)
                    Spacer(Modifier.padding(4.dp))
                    Text(stringResource(R.string.profile_logout))
                }
            }
        }
    }

    if (confirmLogout) {
        ConfirmDialog(
            title = stringResource(R.string.profile_logout_title),
            text = stringResource(R.string.profile_logout_text),
            confirmLabel = stringResource(R.string.profile_logout),
            destructive = true,
            onDismiss = { confirmLogout = false },
            onConfirm = {
                scope.launch {
                    container.session.logout()
                    if (container.session.activeAccount.value == null) {
                        navigator.resetToPortal()
                    } else {
                        navigator.resetToHome()
                    }
                }
            },
        )
    }
}

/**
 * Edit name, contacts and photo (`PATCH profile`, multipart `POST` + `_method=PATCH` when a new
 * photo is chosen). The endpoint replaces all six text fields, so the whole form is always sent.
 * A changed e-mail must be verified again: the portal stops accepting the session token, so the
 * account is signed out locally and the sign in screen is opened with the new address.
 */
@Composable
fun EditProfileScreen() {
    val api = rememberApi()
    val context = LocalContext.current
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val state = remember { FormState() }
    var loaded by remember { mutableStateOf(false) }
    var avatarUrl by remember { mutableStateOf<String?>(null) }
    var fullName by remember { mutableStateOf("") }
    var originalEmail by remember { mutableStateOf("") }
    var newAvatar by remember { mutableStateOf<Uri?>(null) }
    var sessionEnded by remember { mutableStateOf(false) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) newAvatar = uri
    }

    LaunchedEffect(api.baseUrl) {
        try {
            val user = UserSummary.fromJson(api.get("profile").dataObject())
            state.setAll(
                mapOf(
                    "name" to user.name,
                    "last_name" to user.lastName,
                    "middle_name" to user.middleName,
                    "email" to user.email,
                    "phone" to user.phone,
                    "position" to user.position,
                ),
            )
            avatarUrl = user.avatarUrl
            fullName = user.fullName
            originalEmail = user.email
        } catch (exception: Exception) {
            if (exception is kotlinx.coroutines.CancellationException) throw exception
            container.messenger.showError(exception)
        } finally {
            loaded = true
        }
    }

    val emailChanged = originalEmail.isNotBlank() &&
        !state.string("email").trim().equals(originalEmail, ignoreCase = true)
    val emailChangedMessage = stringResource(R.string.profile_email_changed)

    val specs = listOf(
        FieldSpec.Text("name", stringResource(R.string.profile_name), required = true),
        FieldSpec.Text("last_name", stringResource(R.string.profile_last_name)),
        FieldSpec.Text("middle_name", stringResource(R.string.profile_middle_name)),
        FieldSpec.Email(
            "email",
            stringResource(R.string.profile_email),
            required = true,
            hint = if (emailChanged) stringResource(R.string.profile_email_change_warning) else null,
        ),
        FieldSpec.Phone("phone", stringResource(R.string.profile_phone), hint = stringResource(R.string.profile_phone_hint)),
        FieldSpec.Text("position", stringResource(R.string.profile_position)),
    )

    FormScreen(
        title = stringResource(R.string.profile_edit_title),
        specs = specs,
        state = state,
        isLoading = !loaded,
        successMessage = if (emailChanged) null else stringResource(R.string.profile_saved),
        onSubmit = {
            val body = state.toJson(specs)
            val avatar = newAvatar
            if (avatar != null) {
                api.multipart(
                    path = "profile",
                    fields = body.toMultipartFields(),
                    files = listOf(UploadPart.fromUri(context, "avatar", avatar)),
                    methodOverride = "PATCH",
                )
            } else {
                api.patch("profile", body)
            }
            val account = container.session.activeAccount.value
            val newEmail = state.string("email").trim()
            val changedEmail = originalEmail.isNotBlank() && !newEmail.equals(originalEmail, ignoreCase = true)
            if (changedEmail && account != null) {
                container.session.clearSession(account.id, email = newEmail)
                sessionEnded = true
                container.messenger.show(emailChangedMessage)
                navigator.resetToLogin(account.baseUrl, newEmail)
            } else {
                container.session.refreshBootstrapQuietly()
            }
        },
        onSuccess = { if (!sessionEnded) navigator.back() },
        header = {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                val preview = newAvatar
                Avatar(name = fullName, imageUrl = preview?.toString() ?: avatarUrl, size = 72.dp)
                OutlinedButton(onClick = { picker.launch("image/*") }) {
                    Icon(AppIcons.Camera, contentDescription = null)
                    Spacer(Modifier.padding(4.dp))
                    Text(stringResource(R.string.profile_change_photo))
                }
            }
        },
    )
}

/** `PUT profile/password` with the current password. */
@Composable
fun ChangePasswordScreen() {
    val api = rememberApi()
    val navigator = LocalNavigator.current
    val state = remember { FormState() }
    val specs = listOf(
        FieldSpec.Password("current_password", stringResource(R.string.password_current), required = true),
        FieldSpec.Password("password", stringResource(R.string.password_new), required = true, hint = stringResource(R.string.password_hint)),
        FieldSpec.Password("password_confirmation", stringResource(R.string.password_confirmation), required = true),
    )
    FormScreen(
        title = stringResource(R.string.profile_change_password),
        specs = specs,
        state = state,
        submitLabel = stringResource(R.string.password_submit),
        successMessage = stringResource(R.string.password_changed),
        onSubmit = { api.put("profile/password", state.toJson(specs)) },
        onSuccess = { navigator.back() },
    )
}

/** Language, theme, notifications and information about the application. */
@Composable
fun SettingsScreen() {
    val api = rememberApi()
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val context = LocalContext.current
    val bootstrap = rememberBootstrap()
    val themeMode by container.settings.themeMode.collectAsStateWithLifecycle()
    var language by remember { mutableStateOf(AppLanguage.selected()) }
    val notificationsEnabled = NotificationChannels.canPostNotifications(context)

    CrmScaffold(title = stringResource(R.string.settings_title)) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState()),
        ) {
            InfoCard(title = stringResource(R.string.settings_language)) {
                listOf(
                    null to R.string.settings_language_system,
                    "ru" to R.string.settings_language_ru,
                    "en" to R.string.settings_language_en,
                ).forEach { (value, label) ->
                    ChoiceRow(
                        label = stringResource(label),
                        selected = language == value,
                        onClick = {
                            language = value
                            if (value != null) {
                                // The activity is recreated after the locale change, so the request
                                // runs in the application scope.
                                container.appScope.launch {
                                    try {
                                        api.patch("profile/language", jsonObjectOf("language" to value))
                                    } catch (exception: Exception) {
                                        if (exception is kotlinx.coroutines.CancellationException) throw exception
                                    }
                                }
                            }
                            AppLanguage.apply(value)
                        },
                    )
                }
            }
            InfoCard(title = stringResource(R.string.settings_theme)) {
                listOf(
                    ThemeMode.System to R.string.settings_theme_system,
                    ThemeMode.Light to R.string.settings_theme_light,
                    ThemeMode.Dark to R.string.settings_theme_dark,
                ).forEach { (mode, label) ->
                    ChoiceRow(
                        label = stringResource(label),
                        selected = themeMode == mode,
                        onClick = { container.settings.setThemeMode(mode) },
                    )
                }
            }
            InfoCard(title = stringResource(R.string.settings_notifications)) {
                ListRow(
                    title = stringResource(
                        if (notificationsEnabled) R.string.settings_notifications_on else R.string.settings_notifications_off,
                    ),
                    subtitle = stringResource(
                        if (bootstrap?.push != null) R.string.settings_push_configured else R.string.settings_push_not_configured,
                    ),
                    leading = { Icon(AppIcons.Notifications, contentDescription = null) },
                    onClick = {
                        val intent = Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS)
                            .putExtra(Settings.EXTRA_APP_PACKAGE, context.packageName)
                            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        context.startActivity(intent)
                    },
                )
            }
            InfoCard(title = stringResource(R.string.settings_about)) {
                KeyValueRow(label = stringResource(R.string.settings_version), value = BuildConfig.VERSION_NAME, icon = AppIcons.Info)
                KeyValueRow(
                    label = stringResource(R.string.settings_portal),
                    value = api.baseUrl,
                    icon = AppIcons.Public,
                    onClick = { navigator.openUrl(api.baseUrl) },
                )
                KeyValueRow(
                    label = stringResource(R.string.settings_device),
                    value = container.deviceIdentity.deviceName,
                    icon = AppIcons.Tsd,
                )
            }
            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun ChoiceRow(label: String, selected: Boolean, onClick: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        RadioButton(selected = selected, onClick = onClick)
        Text(label, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.padding(start = 4.dp))
    }
}
