package com.appswebnetkz.crm369.feature.warehouses

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.InfoCard
import com.appswebnetkz.crm369.ui.components.KeyValueRow
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons

private const val KEY_NAME = "name"
private const val KEY_AREA = "area_sqm"
private const val KEY_ROWS = "rows_count"
private const val KEY_COLUMNS = "columns_per_row"
private const val KEY_FLOORS = "floors_per_column"
private const val KEY_PLACES = "places_per_floor"
private const val KEY_ROW_PATTERN = "row_pattern"
private const val KEY_COLUMN_PATTERN = "column_pattern"
private const val KEY_FLOOR_PATTERN = "floor_pattern"
private const val KEY_PLACE_PATTERN = "place_pattern"

private val COUNT_KEYS = listOf(KEY_ROWS, KEY_COLUMNS, KEY_FLOORS, KEY_PLACES)

/**
 * New warehouse: name, area and a generator of the address hierarchy
 * (rows × columns × floors × places with name patterns) sent as `rows[].columns[].floors[].places[]`.
 */
@Composable
fun WarehouseCreateScreen() {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val repository = remember(api) { WarehousesRepository(api) }
    val rowPattern = stringResource(R.string.warehouses_pattern_row_default)
    val columnPattern = stringResource(R.string.warehouses_pattern_column_default)
    val floorPattern = stringResource(R.string.warehouses_pattern_floor_default)
    val placePattern = stringResource(R.string.warehouses_pattern_place_default)
    val state = remember {
        FormState(
            mapOf(
                KEY_ROWS to "1",
                KEY_COLUMNS to "1",
                KEY_FLOORS to "1",
                KEY_PLACES to "1",
                KEY_ROW_PATTERN to rowPattern,
                KEY_COLUMN_PATTERN to columnPattern,
                KEY_FLOOR_PATTERN to floorPattern,
                KEY_PLACE_PATTERN to placePattern,
            ),
        )
    }
    val tokensHint = stringResource(R.string.warehouses_pattern_tokens_hint)

    val specs = listOf<FieldSpec>(
        FieldSpec.Text(KEY_NAME, stringResource(R.string.warehouses_name), required = true, hint = stringResource(R.string.warehouses_name_placeholder), maxLength = 255),
        FieldSpec.Decimal(KEY_AREA, stringResource(R.string.warehouses_area_sqm), required = true),
        FieldSpec.Section(stringResource(R.string.warehouses_structure_section)),
        FieldSpec.Number(KEY_ROWS, stringResource(R.string.warehouses_rows_count), required = true),
        FieldSpec.Number(KEY_COLUMNS, stringResource(R.string.warehouses_columns_per_row), required = true),
        FieldSpec.Number(KEY_FLOORS, stringResource(R.string.warehouses_floors_per_column), required = true),
        FieldSpec.Number(KEY_PLACES, stringResource(R.string.warehouses_places_per_floor), required = true),
        FieldSpec.Section(stringResource(R.string.warehouses_patterns_section)),
        FieldSpec.Text(KEY_ROW_PATTERN, stringResource(R.string.warehouses_pattern_row), required = true, hint = tokensHint, maxLength = 200),
        FieldSpec.Text(KEY_COLUMN_PATTERN, stringResource(R.string.warehouses_pattern_column), required = true, maxLength = 200),
        FieldSpec.Text(KEY_FLOOR_PATTERN, stringResource(R.string.warehouses_pattern_floor), required = true, maxLength = 200),
        FieldSpec.Text(KEY_PLACE_PATTERN, stringResource(R.string.warehouses_pattern_place), required = true, maxLength = 200),
    )

    FormScreen(
        title = stringResource(R.string.warehouses_create_title),
        specs = specs,
        state = state,
        submitLabel = stringResource(R.string.warehouses_create_submit),
        successMessage = stringResource(R.string.warehouses_created_success),
        onSubmit = {
            var invalid = false
            COUNT_KEYS.forEach { key ->
                val count = state.string(key).trim().toIntOrNull() ?: 0
                if (count < 1 || count > WarehouseGenerator.MAX_PER_LEVEL) {
                    state.errors[key] = context.getString(R.string.warehouses_level_range, WarehouseGenerator.MAX_PER_LEVEL)
                    invalid = true
                }
            }
            val area = state.string(KEY_AREA).trim().replace(',', '.').toDoubleOrNull()
            if (area == null || area < 0.0) {
                state.errors[KEY_AREA] = context.getString(R.string.warehouses_area_invalid)
                invalid = true
            }
            if (invalid || area == null) {
                throw IllegalArgumentException(context.getString(R.string.warehouses_form_invalid))
            }
            val layout = layoutOf(state)
            val patterns = patternsOf(state)
            if (layout.totalPlaces > WarehouseGenerator.MAX_PLACES) {
                throw IllegalArgumentException(context.getString(R.string.warehouses_too_many_places, WarehouseGenerator.MAX_PLACES))
            }
            val missing = WarehouseGenerator.levelsMissingToken(layout, patterns)
            if (missing.isNotEmpty()) {
                missing.forEach { level ->
                    state.errors[patternKey(level)] = context.getString(R.string.warehouses_pattern_token_missing, level.token)
                }
                throw IllegalArgumentException(context.getString(R.string.warehouses_form_invalid))
            }
            val created = repository.create(
                name = state.string(KEY_NAME).trim(),
                areaSqm = area,
                rows = WarehouseGenerator.build(layout, patterns),
            )
            navigator.replace(WarehousesRoutes.detail(created.id))
        },
        onSuccess = {},
        footer = { WarehousePreview(layout = layoutOf(state), patterns = patternsOf(state)) },
    )
}

@Composable
private fun WarehousePreview(layout: WarehouseLayout, patterns: WarehouseNamePatterns) {
    InfoCard(title = stringResource(R.string.warehouses_preview_title)) {
        KeyValueRow(
            label = stringResource(R.string.warehouses_capacity_preview),
            value = if (layout.isValid) Formatters.number(layout.totalPlaces) else null,
            icon = AppIcons.Place,
        )
        KeyValueRow(
            label = stringResource(R.string.warehouses_qr_preview),
            value = if (layout.isValid) Formatters.number(layout.totalQrCodes) else null,
            icon = AppIcons.QrScan,
        )
        KeyValueRow(
            label = stringResource(R.string.warehouses_example_address),
            value = WarehouseGenerator.example(patterns),
            icon = AppIcons.Info,
        )
    }
}

private fun layoutOf(state: FormState): WarehouseLayout = WarehouseLayout(
    rows = state.string(KEY_ROWS).trim().toIntOrNull() ?: 0,
    columnsPerRow = state.string(KEY_COLUMNS).trim().toIntOrNull() ?: 0,
    floorsPerColumn = state.string(KEY_FLOORS).trim().toIntOrNull() ?: 0,
    placesPerFloor = state.string(KEY_PLACES).trim().toIntOrNull() ?: 0,
)

private fun patternsOf(state: FormState): WarehouseNamePatterns = WarehouseNamePatterns(
    row = state.string(KEY_ROW_PATTERN),
    column = state.string(KEY_COLUMN_PATTERN),
    floor = state.string(KEY_FLOOR_PATTERN),
    place = state.string(KEY_PLACE_PATTERN),
)

private fun patternKey(level: WarehouseLevel): String = when (level) {
    WarehouseLevel.ROW -> KEY_ROW_PATTERN
    WarehouseLevel.COLUMN -> KEY_COLUMN_PATTERN
    WarehouseLevel.FLOOR -> KEY_FLOOR_PATTERN
    WarehouseLevel.PLACE -> KEY_PLACE_PATTERN
}
