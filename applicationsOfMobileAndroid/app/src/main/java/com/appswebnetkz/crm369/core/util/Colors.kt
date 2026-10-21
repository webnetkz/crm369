package com.appswebnetkz.crm369.core.util

import androidx.compose.ui.graphics.Color

/** Color helpers for values coming from the server ("#2563EB", "var(--color-chart-3)"). */
object ServerColors {

    /** Palette used for "var(--color-chart-N)" values and for data series without a color. */
    val chartPalette: List<Color> = listOf(
        Color(0xFF0A6C74),
        Color(0xFFC99A2E),
        Color(0xFF2563EB),
        Color(0xFF16A34A),
        Color(0xFFDC2626),
        Color(0xFF7C3AED),
        Color(0xFFEA580C),
        Color(0xFF0891B2),
    )

    private val chartVariable = Regex("""var\(--color-chart-(\d+)\)""")

    /** Parses `#RGB`, `#RRGGBB`, `#AARRGGBB` or a chart CSS variable. */
    fun parse(value: String?, fallback: Color = chartPalette[0]): Color {
        val text = value?.trim().orEmpty()
        if (text.isEmpty()) return fallback
        chartVariable.find(text)?.let { match ->
            val index = (match.groupValues[1].toIntOrNull() ?: 1) - 1
            return chartPalette[Math.floorMod(index, chartPalette.size)]
        }
        if (!text.startsWith("#")) return fallback
        val hex = text.removePrefix("#")
        val expanded = when (hex.length) {
            3 -> "FF" + hex.map { "$it$it" }.joinToString("")
            6 -> "FF$hex"
            8 -> hex
            else -> return fallback
        }
        return try {
            Color(expanded.toLong(16).toInt())
        } catch (_: NumberFormatException) {
            fallback
        }
    }

    /** Stable color for an arbitrary key (used for avatars without a picture). */
    fun forKey(key: String): Color = chartPalette[Math.floorMod(key.hashCode(), chartPalette.size)]
}
