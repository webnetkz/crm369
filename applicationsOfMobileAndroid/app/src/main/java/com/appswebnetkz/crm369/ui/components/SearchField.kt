package com.appswebnetkz.crm369.ui.components

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.delay

/** Single line search input with a clear button. */
@Composable
fun SearchField(
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String = stringResource(R.string.action_search),
    onSearch: (() -> Unit)? = null,
) {
    val focusManager = LocalFocusManager.current
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        placeholder = { Text(placeholder) },
        leadingIcon = { Icon(AppIcons.Search, contentDescription = null) },
        trailingIcon = if (value.isNotEmpty()) {
            {
                IconButton(onClick = { onValueChange("") }) {
                    Icon(AppIcons.Close, contentDescription = stringResource(R.string.action_clear))
                }
            }
        } else {
            null
        },
        singleLine = true,
        shape = RoundedCornerShape(28.dp),
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
        keyboardActions = KeyboardActions(onSearch = {
            focusManager.clearFocus()
            onSearch?.invoke()
        }),
    )
}

/**
 * Search field that reports the query after the user stopped typing for [debounceMillis].
 * Returns nothing; [onQuery] receives the debounced value (also the initial one).
 */
@Composable
fun DebouncedSearchField(
    onQuery: (String) -> Unit,
    modifier: Modifier = Modifier,
    initialValue: String = "",
    placeholder: String = stringResource(R.string.action_search),
    debounceMillis: Long = 400L,
) {
    var text by rememberSaveable { mutableStateOf(initialValue) }
    val currentOnQuery by rememberUpdatedState(onQuery)
    LaunchedEffect(text) {
        if (text != initialValue) {
            delay(debounceMillis)
        }
        currentOnQuery(text.trim())
    }
    SearchField(
        value = text,
        onValueChange = { text = it },
        modifier = modifier,
        placeholder = placeholder,
        onSearch = { currentOnQuery(text.trim()) },
    )
}
