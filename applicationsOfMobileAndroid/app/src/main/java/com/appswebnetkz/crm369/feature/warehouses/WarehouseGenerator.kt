package com.appswebnetkz.crm369.feature.warehouses

/** Sizes of a generated warehouse hierarchy (every level needs at least one node). */
data class WarehouseLayout(
    val rows: Int,
    val columnsPerRow: Int,
    val floorsPerColumn: Int,
    val placesPerFloor: Int,
) {
    val isValid: Boolean
        get() = rows >= 1 && columnsPerRow >= 1 && floorsPerColumn >= 1 && placesPerFloor >= 1

    val totalColumns: Long get() = rows.toLong() * columnsPerRow
    val totalFloors: Long get() = totalColumns * floorsPerColumn
    val totalPlaces: Long get() = totalFloors * placesPerFloor

    /** QR codes created by the server: the warehouse plus every row, column, floor and place. */
    val totalQrCodes: Long get() = 1L + rows + totalColumns + totalFloors + totalPlaces
}

/** Name patterns of the generated nodes, see [WarehouseGenerator] for the tokens. */
data class WarehouseNamePatterns(
    val row: String,
    val column: String,
    val floor: String,
    val place: String,
)

/** Hierarchy levels and the token that numbers the nodes of each level. */
enum class WarehouseLevel(val token: String) {
    ROW(WarehouseGenerator.TOKEN_ROW),
    COLUMN(WarehouseGenerator.TOKEN_COLUMN),
    FLOOR(WarehouseGenerator.TOKEN_FLOOR),
    PLACE(WarehouseGenerator.TOKEN_PLACE),
}

/**
 * Builds the `rows[].columns[].floors[].places[]` body of `POST warehouses` like the web form:
 * "Ряд A" / "Колонка 01" / "Этаж 1" / "A-01-1-001".
 *
 * Tokens: `{R}` row letter (A … Z, AA …), `{C}` column number (01), `{F}` floor number (1),
 * `{P}` place number (001).
 */
object WarehouseGenerator {
    const val TOKEN_ROW = "{R}"
    const val TOKEN_COLUMN = "{C}"
    const val TOKEN_FLOOR = "{F}"
    const val TOKEN_PLACE = "{P}"

    /** Upper bound of one level (keeps the request reasonable). */
    const val MAX_PER_LEVEL = 999

    /** Upper bound of all places of one warehouse created from the app. */
    const val MAX_PLACES = 5000L

    /** 0 → "A", 25 → "Z", 26 → "AA" (bijective base 26). */
    fun rowLetter(index: Int): String {
        var number = index.coerceAtLeast(0) + 1
        val builder = StringBuilder()
        while (number > 0) {
            val remainder = (number - 1) % 26
            builder.append('A' + remainder)
            number = (number - 1) / 26
        }
        return builder.reverse().toString()
    }

    /** Applies the tokens of [pattern] for the node at the given zero-based indexes. */
    fun name(pattern: String, row: Int, column: Int = 0, floor: Int = 0, place: Int = 0): String =
        pattern
            .replace(TOKEN_ROW, rowLetter(row))
            .replace(TOKEN_COLUMN, (column + 1).toString().padStart(2, '0'))
            .replace(TOKEN_FLOOR, (floor + 1).toString())
            .replace(TOKEN_PLACE, (place + 1).toString().padStart(3, '0'))
            .trim()

    /** Levels with more than one node whose pattern lacks the level token (names would repeat). */
    fun levelsMissingToken(layout: WarehouseLayout, patterns: WarehouseNamePatterns): List<WarehouseLevel> = buildList {
        if (layout.rows > 1 && !patterns.row.contains(TOKEN_ROW)) add(WarehouseLevel.ROW)
        if (layout.columnsPerRow > 1 && !patterns.column.contains(TOKEN_COLUMN)) add(WarehouseLevel.COLUMN)
        if (layout.floorsPerColumn > 1 && !patterns.floor.contains(TOKEN_FLOOR)) add(WarehouseLevel.FLOOR)
        if (layout.placesPerFloor > 1 && !patterns.place.contains(TOKEN_PLACE)) add(WarehouseLevel.PLACE)
    }

    /** First generated address, e.g. "Ряд A / Колонка 01 / Этаж 1 / A-01-1-001". */
    fun example(patterns: WarehouseNamePatterns): String = listOf(
        name(patterns.row, 0),
        name(patterns.column, 0, 0),
        name(patterns.floor, 0, 0, 0),
        name(patterns.place, 0, 0, 0, 0),
    ).joinToString(" / ")

    /** The `rows` array of the request body. */
    fun build(layout: WarehouseLayout, patterns: WarehouseNamePatterns): List<Map<String, Any?>> =
        (0 until layout.rows).map { row ->
            mapOf(
                "name" to name(patterns.row, row),
                "columns" to (0 until layout.columnsPerRow).map { column ->
                    mapOf(
                        "name" to name(patterns.column, row, column),
                        "floors" to (0 until layout.floorsPerColumn).map { floor ->
                            mapOf(
                                "name" to name(patterns.floor, row, column, floor),
                                "places" to (0 until layout.placesPerFloor).map { place ->
                                    mapOf("name" to name(patterns.place, row, column, floor, place))
                                },
                            )
                        },
                    )
                },
            )
        }
}
