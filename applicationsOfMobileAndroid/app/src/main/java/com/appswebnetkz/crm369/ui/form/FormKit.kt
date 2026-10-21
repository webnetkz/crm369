package com.appswebnetkz.crm369.ui.form

import android.app.TimePickerDialog
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.json.anyToJson
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.core.util.ServerColors
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.LoadingState
import com.appswebnetkz.crm369.ui.components.MultiOptionPickerDialog
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.OptionPickerDialog
import com.appswebnetkz.crm369.ui.components.rememberActionRunner
import com.appswebnetkz.crm369.ui.theme.AppIcons
import java.time.LocalTime
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject

/**
 * Declarative form field. Keys may use dot notation for nested request fields
 * (`company_requisites.iin` → `{"company_requisites":{"iin":...}}`); Laravel validation
 * errors use the same keys, so [FormState.applyErrors] maps them back to the fields.
 */
sealed class FieldSpec {
    abstract val key: String
    abstract val label: String
    abstract val required: Boolean
    abstract val enabled: Boolean
    abstract val hint: String?

    /** Single line text. */
    data class Text(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
        val maxLength: Int? = null,
    ) : FieldSpec()

    /** Multi line text (description, notes, comments). */
    data class Multiline(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
        val minLines: Int = 3,
        val maxLength: Int? = null,
    ) : FieldSpec()

    data class Email(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
    ) : FieldSpec()

    data class Phone(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
    ) : FieldSpec()

    data class Password(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
    ) : FieldSpec()

    /** Integer number (sent as a JSON number). */
    data class Number(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
    ) : FieldSpec()

    /** Decimal number (amounts, quantities; sent as a JSON number). */
    data class Decimal(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
    ) : FieldSpec()

    /** Date, value `YYYY-MM-DD`. */
    data class Date(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
    ) : FieldSpec()

    /** Date and time, value `YYYY-MM-DD HH:MM` (device local time). */
    data class DateTime(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
    ) : FieldSpec()

    /** Single choice. [numeric] sends the option value as a number (ids). */
    data class Select(
        override val key: String,
        override val label: String,
        val options: List<Option>,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
        val numeric: Boolean = false,
    ) : FieldSpec()

    /** Multiple choice, sent as a JSON array. */
    data class MultiSelect(
        override val key: String,
        override val label: String,
        val options: List<Option>,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
        val numeric: Boolean = false,
    ) : FieldSpec()

    /** User (employee) picker; values are user ids sent as numbers. */
    data class UserPicker(
        override val key: String,
        override val label: String,
        val users: List<Option>,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
        val multiple: Boolean = false,
    ) : FieldSpec()

    /** Boolean toggle. */
    data class Switch(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
    ) : FieldSpec()

    /** Hex color (`#2563EB`) with preset swatches. */
    data class Color(
        override val key: String,
        override val label: String,
        override val required: Boolean = false,
        override val enabled: Boolean = true,
        override val hint: String? = null,
        val presets: List<String> = DEFAULT_COLOR_PRESETS,
    ) : FieldSpec()

    /** Visual group title (not a value). */
    data class Section(
        val title: String,
    ) : FieldSpec() {
        override val key: String get() = ""
        override val label: String get() = title
        override val required: Boolean get() = false
        override val enabled: Boolean get() = true
        override val hint: String? get() = null
    }

    companion object {
        val DEFAULT_COLOR_PRESETS: List<String> = listOf(
            "#64748B", "#2563EB", "#0A6C74", "#16A34A", "#CA8A04", "#EA580C", "#DC2626", "#7C3AED", "#DB2777",
        )
    }
}

/**
 * Values and validation errors of a form. Values are stored as:
 * text-like fields → String, Switch → Boolean, multi choice → List<String>, single choice → String?.
 */
@Stable
class FormState(initial: Map<String, Any?> = emptyMap()) {

    private val values = mutableStateMapOf<String, Any?>()

    /** Field errors keyed by field key (shown under the fields). */
    val errors = mutableStateMapOf<String, String>()

    /** Error that is not bound to a field (shown above the form). */
    var generalError: String? by mutableStateOf(null)

    init {
        initial.forEach { (key, value) -> values[key] = normalizeInitial(value) }
    }

    fun value(key: String): Any? = values[key]

    fun string(key: String): String = when (val value = values[key]) {
        null -> ""
        is String -> value
        else -> value.toString()
    }

    fun bool(key: String): Boolean = when (val value = values[key]) {
        is Boolean -> value
        is String -> value == "1" || value.equals("true", ignoreCase = true)
        is kotlin.Number -> value.toInt() != 0
        else -> false
    }

    fun list(key: String): List<String> = when (val value = values[key]) {
        is List<*> -> value.mapNotNull { it?.toString() }
        is String -> if (value.isBlank()) emptyList() else listOf(value)
        else -> emptyList()
    }

    fun set(key: String, value: Any?) {
        values[key] = value
        errors.remove(key)
        generalError = null
    }

    fun setAll(newValues: Map<String, Any?>) {
        newValues.forEach { (key, value) -> values[key] = normalizeInitial(value) }
    }

    /** Checks required fields; returns false (and sets errors) when something is missing. */
    fun validate(specs: List<FieldSpec>, requiredMessage: String): Boolean {
        var valid = true
        specs.forEach { spec ->
            if (!spec.required || spec is FieldSpec.Section || spec is FieldSpec.Switch) return@forEach
            val missing = when (spec) {
                is FieldSpec.MultiSelect -> list(spec.key).isEmpty()
                is FieldSpec.UserPicker -> if (spec.multiple) list(spec.key).isEmpty() else string(spec.key).isBlank()
                else -> string(spec.key).isBlank()
            }
            if (missing) {
                errors[spec.key] = requiredMessage
                valid = false
            }
        }
        return valid
    }

    /**
     * Request body for [specs] (typed JSON values, nested objects for dotted keys) merged with
     * [extra] values that are not shown as fields (e.g. `type`, ids).
     */
    fun toJson(specs: List<FieldSpec>, extra: Map<String, Any?> = emptyMap()): JsonObject {
        val root = LinkedHashMap<String, Any?>()
        specs.forEach { spec ->
            if (spec is FieldSpec.Section) return@forEach
            putNested(root, spec.key, typedValue(spec))
        }
        extra.forEach { (key, value) -> putNested(root, key, value) }
        val json: JsonElement = anyToJson(root)
        return json as? JsonObject ?: JsonObject(emptyMap())
    }

    /**
     * Maps a Laravel 422 response to field errors. Returns true when the error was a validation
     * error (the caller should not show another message).
     */
    fun applyErrors(error: Throwable): Boolean {
        if (error !is ApiException || !error.isValidation) return false
        errors.clear()
        error.errors.forEach { (key, messages) ->
            val message = messages.firstOrNull() ?: return@forEach
            errors[key] = message
        }
        generalError = error.firstMessage().ifBlank { null }
        return true
    }

    /** Error of [key] or of any nested key (`custom_fields.budget` for `custom_fields`). */
    fun errorFor(key: String): String? =
        errors[key] ?: errors.entries.firstOrNull { it.key.startsWith("$key.") }?.value

    private fun typedValue(spec: FieldSpec): Any? = when (spec) {
        is FieldSpec.Section -> null
        is FieldSpec.Switch -> bool(spec.key)
        is FieldSpec.Number -> string(spec.key).filter { !it.isWhitespace() }.takeIf { it.isNotEmpty() }?.let { text ->
            text.toLongOrNull() ?: text.replace(',', '.').toDoubleOrNull()?.toLong()
        }
        is FieldSpec.Decimal -> string(spec.key).filter { !it.isWhitespace() }.replace(',', '.').takeIf { it.isNotEmpty() }?.toDoubleOrNull()
        is FieldSpec.MultiSelect -> list(spec.key).map { if (spec.numeric) (it.toLongOrNull() ?: it) else it }
        is FieldSpec.UserPicker -> if (spec.multiple) {
            list(spec.key).mapNotNull { it.toLongOrNull() }
        } else {
            string(spec.key).toLongOrNull()
        }
        is FieldSpec.Select -> string(spec.key).takeIf { it.isNotEmpty() }?.let { if (spec.numeric) (it.toLongOrNull() ?: it) else it }
        is FieldSpec.Password -> string(spec.key).takeIf { it.isNotEmpty() }
        else -> string(spec.key).trim().takeIf { it.isNotEmpty() }
    }

    private fun normalizeInitial(value: Any?): Any? = when (value) {
        null -> null
        is String, is Boolean -> value
        is List<*> -> value.mapNotNull { it?.toString() }
        is Double -> if (value % 1.0 == 0.0) value.toLong().toString() else value.toString()
        is kotlin.Number -> value.toString()
        else -> value.toString()
    }

    @Suppress("UNCHECKED_CAST")
    private fun putNested(root: MutableMap<String, Any?>, key: String, value: Any?) {
        val parts = key.split('.')
        var current: MutableMap<String, Any?> = root
        parts.dropLast(1).forEach { part ->
            val next = current[part] as? MutableMap<String, Any?> ?: LinkedHashMap<String, Any?>().also { current[part] = it }
            current = next
        }
        current[parts.last()] = value
    }
}

/** Renders [specs] bound to [state]. */
@Composable
fun FormFields(
    specs: List<FieldSpec>,
    state: FormState,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        specs.forEach { spec ->
            FormField(spec = spec, state = state, enabled = enabled && spec.enabled)
        }
    }
}

@Composable
private fun FormField(spec: FieldSpec, state: FormState, enabled: Boolean) {
    val label = if (spec.required) spec.label + " *" else spec.label
    val error = if (spec.key.isNotEmpty()) state.errorFor(spec.key) else null
    when (spec) {
        is FieldSpec.Section -> Text(
            text = spec.title,
            style = MaterialTheme.typography.titleSmall,
            color = MaterialTheme.colorScheme.primary,
            modifier = Modifier.padding(top = 12.dp),
        )
        is FieldSpec.Text -> TextInput(spec.key, label, state, enabled, error, spec.hint, KeyboardType.Text, KeyboardCapitalization.Sentences)
        is FieldSpec.Email -> TextInput(spec.key, label, state, enabled, error, spec.hint, KeyboardType.Email, KeyboardCapitalization.None)
        is FieldSpec.Phone -> TextInput(spec.key, label, state, enabled, error, spec.hint, KeyboardType.Phone, KeyboardCapitalization.None)
        is FieldSpec.Number -> TextInput(spec.key, label, state, enabled, error, spec.hint, KeyboardType.Number, KeyboardCapitalization.None)
        is FieldSpec.Decimal -> TextInput(spec.key, label, state, enabled, error, spec.hint, KeyboardType.Decimal, KeyboardCapitalization.None)
        is FieldSpec.Password -> PasswordInput(spec.key, label, state, enabled, error, spec.hint)
        is FieldSpec.Multiline -> OutlinedTextField(
            value = state.string(spec.key),
            onValueChange = { value -> state.set(spec.key, if (spec.maxLength != null) value.take(spec.maxLength) else value) },
            label = { Text(label) },
            enabled = enabled,
            isError = error != null,
            supportingText = supportingText(error, spec.hint),
            minLines = spec.minLines,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            modifier = Modifier.fillMaxWidth(),
        )
        is FieldSpec.Date -> DateInput(spec.key, label, state, enabled, error, spec.hint, withTime = false)
        is FieldSpec.DateTime -> DateInput(spec.key, label, state, enabled, error, spec.hint, withTime = true)
        is FieldSpec.Select -> {
            val selected = state.string(spec.key).takeIf { it.isNotEmpty() }
            val display = spec.options.firstOrNull { it.value == selected }?.label ?: selected.orEmpty()
            PickerInput(label = label, display = display, enabled = enabled, error = error, hint = spec.hint) { close ->
                OptionPickerDialog(
                    title = spec.label,
                    options = spec.options,
                    selected = selected,
                    onSelect = { option -> state.set(spec.key, option?.value) },
                    onDismiss = close,
                    allowClear = !spec.required,
                )
            }
        }
        is FieldSpec.MultiSelect -> {
            val selected = state.list(spec.key).toSet()
            val display = spec.options.filter { it.value in selected }.joinToString(", ") { it.label }
            PickerInput(label = label, display = display, enabled = enabled, error = error, hint = spec.hint) { close ->
                MultiOptionPickerDialog(
                    title = spec.label,
                    options = spec.options,
                    selected = selected,
                    onConfirm = { values -> state.set(spec.key, values.toList()) },
                    onDismiss = close,
                )
            }
        }
        is FieldSpec.UserPicker -> {
            if (spec.multiple) {
                val selected = state.list(spec.key).toSet()
                val display = spec.users.filter { it.value in selected }.joinToString(", ") { it.label }
                PickerInput(label = label, display = display, enabled = enabled, error = error, hint = spec.hint) { close ->
                    MultiOptionPickerDialog(
                        title = spec.label,
                        options = spec.users,
                        selected = selected,
                        onConfirm = { values -> state.set(spec.key, values.toList()) },
                        onDismiss = close,
                    )
                }
            } else {
                val selected = state.string(spec.key).takeIf { it.isNotEmpty() }
                val display = spec.users.firstOrNull { it.value == selected }?.label ?: selected.orEmpty()
                PickerInput(label = label, display = display, enabled = enabled, error = error, hint = spec.hint) { close ->
                    OptionPickerDialog(
                        title = spec.label,
                        options = spec.users,
                        selected = selected,
                        onSelect = { option -> state.set(spec.key, option?.value) },
                        onDismiss = close,
                        allowClear = !spec.required,
                    )
                }
            }
        }
        is FieldSpec.Switch -> Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable(enabled = enabled) { state.set(spec.key, !state.bool(spec.key)) }
                .padding(vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(spec.label, style = MaterialTheme.typography.bodyLarge)
                if (!spec.hint.isNullOrBlank()) {
                    Text(
                        spec.hint,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            Switch(
                checked = state.bool(spec.key),
                onCheckedChange = { checked -> state.set(spec.key, checked) },
                enabled = enabled,
            )
        }
        is FieldSpec.Color -> ColorInput(spec, label, state, enabled, error)
    }
}

private fun supportingText(error: String?, hint: String?): (@Composable () -> Unit)? {
    val message = error ?: hint?.takeIf { it.isNotBlank() } ?: return null
    val content: @Composable () -> Unit = { Text(message) }
    return content
}

@Composable
private fun TextInput(
    key: String,
    label: String,
    state: FormState,
    enabled: Boolean,
    error: String?,
    hint: String?,
    keyboardType: KeyboardType,
    capitalization: KeyboardCapitalization,
) {
    OutlinedTextField(
        value = state.string(key),
        onValueChange = { value -> state.set(key, value) },
        label = { Text(label) },
        enabled = enabled,
        singleLine = true,
        isError = error != null,
        supportingText = supportingText(error, hint),
        keyboardOptions = KeyboardOptions(capitalization = capitalization, keyboardType = keyboardType),
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun PasswordInput(
    key: String,
    label: String,
    state: FormState,
    enabled: Boolean,
    error: String?,
    hint: String?,
) {
    var visible by remember { mutableStateOf(false) }
    OutlinedTextField(
        value = state.string(key),
        onValueChange = { value -> state.set(key, value) },
        label = { Text(label) },
        enabled = enabled,
        singleLine = true,
        isError = error != null,
        supportingText = supportingText(error, hint),
        visualTransformation = if (visible) VisualTransformation.None else PasswordVisualTransformation(),
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
        trailingIcon = {
            IconButton(onClick = { visible = !visible }) {
                Icon(if (visible) AppIcons.VisibilityOff else AppIcons.Visibility, contentDescription = null)
            }
        },
        modifier = Modifier.fillMaxWidth(),
    )
}

/** Read-only field that opens a dialog provided by [dialog] (it receives a "close" callback). */
@Composable
fun PickerInput(
    label: String,
    display: String,
    enabled: Boolean,
    error: String?,
    hint: String?,
    dialog: @Composable (close: () -> Unit) -> Unit,
) {
    var showDialog by remember { mutableStateOf(false) }
    Box {
        OutlinedTextField(
            value = display,
            onValueChange = {},
            readOnly = true,
            label = { Text(label) },
            enabled = enabled,
            isError = error != null,
            supportingText = supportingText(error, hint),
            trailingIcon = { Icon(AppIcons.DropDown, contentDescription = null) },
            modifier = Modifier.fillMaxWidth(),
        )
        Box(
            modifier = Modifier
                .matchParentSize()
                .clickable(enabled = enabled) { showDialog = true },
        )
    }
    if (showDialog) {
        dialog { showDialog = false }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateInput(
    key: String,
    label: String,
    state: FormState,
    enabled: Boolean,
    error: String?,
    hint: String?,
    withTime: Boolean,
) {
    val context = LocalContext.current
    val raw = state.string(key)
    val datePart = raw.take(10)
    val timePart = if (withTime && raw.length >= 16) raw.substring(11, 16) else ""
    val display = buildString {
        append(Formatters.date(Formatters.parseDate(datePart)))
        if (timePart.isNotEmpty()) append(", ").append(timePart)
    }
    var showDialog by remember { mutableStateOf(false) }
    Box {
        OutlinedTextField(
            value = display,
            onValueChange = {},
            readOnly = true,
            label = { Text(label) },
            enabled = enabled,
            isError = error != null,
            supportingText = supportingText(error, hint),
            trailingIcon = {
                if (raw.isNotEmpty() && enabled) {
                    IconButton(onClick = { state.set(key, null) }) {
                        Icon(AppIcons.Close, contentDescription = stringResource(R.string.action_clear))
                    }
                } else {
                    Icon(AppIcons.Calendar, contentDescription = null)
                }
            },
            modifier = Modifier.fillMaxWidth(),
        )
        Box(
            modifier = Modifier
                .matchParentSize()
                .padding(end = 48.dp)
                .clickable(enabled = enabled) { showDialog = true },
        )
    }
    if (showDialog) {
        val pickerState = rememberDatePickerState(initialSelectedDateMillis = Formatters.utcMillisFromIsoDate(datePart))
        DatePickerDialog(
            onDismissRequest = { showDialog = false },
            confirmButton = {
                TextButton(
                    onClick = {
                        val millis = pickerState.selectedDateMillis
                        showDialog = false
                        if (millis != null) {
                            val date = Formatters.isoDateFromUtcMillis(millis)
                            if (withTime) {
                                val initial = runCatching { LocalTime.parse(timePart) }.getOrNull()
                                    ?: LocalTime.now().withSecond(0).withNano(0)
                                TimePickerDialog(
                                    context,
                                    { _, hour, minute ->
                                        state.set(key, "%s %02d:%02d".format(date, hour, minute))
                                    },
                                    initial.hour,
                                    initial.minute,
                                    true,
                                ).show()
                            } else {
                                state.set(key, date)
                            }
                        }
                    },
                ) { Text(stringResource(R.string.action_ok)) }
            },
            dismissButton = {
                TextButton(onClick = { showDialog = false }) { Text(stringResource(R.string.action_cancel)) }
            },
        ) {
            DatePicker(state = pickerState)
        }
    }
}

@Composable
private fun ColorInput(spec: FieldSpec.Color, label: String, state: FormState, enabled: Boolean, error: String?) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        OutlinedTextField(
            value = state.string(spec.key),
            onValueChange = { value -> state.set(spec.key, value.trim().take(9)) },
            label = { Text(label) },
            enabled = enabled,
            singleLine = true,
            isError = error != null,
            supportingText = supportingText(error, spec.hint),
            leadingIcon = {
                Box(
                    modifier = Modifier
                        .size(20.dp)
                        .background(ServerColors.parse(state.string(spec.key), MaterialTheme.colorScheme.outline), CircleShape),
                )
            },
            modifier = Modifier.fillMaxWidth(),
        )
        Row(
            modifier = Modifier.horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            spec.presets.forEach { preset ->
                val selected = state.string(spec.key).equals(preset, ignoreCase = true)
                Box(
                    modifier = Modifier
                        .size(32.dp)
                        .background(ServerColors.parse(preset), CircleShape)
                        .border(
                            width = if (selected) 3.dp else 0.dp,
                            color = if (selected) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.surface,
                            shape = CircleShape,
                        )
                        .clickable(enabled = enabled) { state.set(spec.key, preset) },
                )
            }
        }
    }
}

/**
 * Complete create/edit screen: top bar, scrollable [FormFields] and a bottom submit button.
 *
 * [onSubmit] performs the API call (use `state.toJson(specs)` for the body) and may throw;
 * Laravel 422 errors are mapped to the fields automatically. [onSuccess] runs after success
 * (typically `navigator.back()`).
 */
@Composable
fun FormScreen(
    title: String,
    specs: List<FieldSpec>,
    state: FormState,
    onSubmit: suspend () -> Unit,
    onSuccess: () -> Unit,
    submitLabel: String = stringResource(R.string.action_save),
    successMessage: String? = null,
    isLoading: Boolean = false,
    actions: @Composable RowScope.() -> Unit = {},
    header: @Composable ColumnScope.() -> Unit = {},
    footer: @Composable ColumnScope.() -> Unit = {},
) {
    val runner = rememberActionRunner()
    val requiredMessage = stringResource(R.string.form_required)
    CrmScaffold(
        title = title,
        actions = actions,
        bottomBar = {
            if (!isLoading) {
                Surface(tonalElevation = 3.dp) {
                    Button(
                        onClick = {
                            if (state.validate(specs, requiredMessage)) {
                                runner.execute(
                                    successMessage = successMessage,
                                    onError = { error -> state.applyErrors(error) },
                                    onSuccess = { _: Unit -> onSuccess() },
                                ) { onSubmit() }
                            }
                        },
                        enabled = !runner.isBusy,
                        modifier = Modifier
                            .fillMaxWidth()
                            .navigationBarsPadding()
                            .imePadding()
                            .padding(horizontal = 16.dp, vertical = 12.dp),
                    ) {
                        if (runner.isBusy) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(18.dp),
                                strokeWidth = 2.dp,
                                color = MaterialTheme.colorScheme.onPrimary,
                            )
                            Spacer(Modifier.size(8.dp))
                        }
                        Text(submitLabel)
                    }
                }
            }
        },
    ) { padding ->
        if (isLoading) {
            LoadingState(modifier = Modifier.padding(padding))
        } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .verticalScroll(rememberScrollState())
                    .padding(vertical = 12.dp),
            ) {
                header()
                val generalError = state.generalError
                if (!generalError.isNullOrBlank()) {
                    Surface(
                        color = MaterialTheme.colorScheme.errorContainer,
                        contentColor = MaterialTheme.colorScheme.onErrorContainer,
                        shape = MaterialTheme.shapes.medium,
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 8.dp),
                    ) {
                        Text(generalError, modifier = Modifier.padding(12.dp))
                    }
                }
                FormFields(specs = specs, state = state, enabled = !runner.isBusy)
                footer()
                Spacer(Modifier.height(24.dp))
            }
        }
    }
}
