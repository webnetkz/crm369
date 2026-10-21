package com.appswebnetkz.crm369.core.util

import android.text.format.DateUtils
import java.text.NumberFormat
import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle
import java.util.Locale
import kotlin.math.abs

/**
 * Formatting helpers for values returned by the API. Dates come as ISO-8601 strings
 * (`2026-10-21T09:15:00.000000Z`) or plain dates (`2026-10-21`).
 */
object Formatters {

    /** Parses an ISO date-time or date into a [ZonedDateTime] in the device time zone. */
    fun parseDateTime(value: String?): ZonedDateTime? {
        if (value.isNullOrBlank()) return null
        val text = value.trim()
        return try {
            OffsetDateTime.parse(text).atZoneSameInstant(ZoneId.systemDefault())
        } catch (_: Exception) {
            try {
                Instant.parse(text).atZone(ZoneId.systemDefault())
            } catch (_: Exception) {
                try {
                    LocalDateTime.parse(text.replace(' ', 'T')).atZone(ZoneId.systemDefault())
                } catch (_: Exception) {
                    parseDate(text)?.atStartOfDay(ZoneId.systemDefault())
                }
            }
        }
    }

    /** Parses `YYYY-MM-DD` (or the date part of an ISO date-time). */
    fun parseDate(value: String?): LocalDate? {
        if (value.isNullOrBlank()) return null
        return try {
            LocalDate.parse(value.trim().take(10))
        } catch (_: Exception) {
            null
        }
    }

    /** "21 окт. 2026 г., 14:05" */
    fun dateTime(value: String?): String {
        val date = parseDateTime(value) ?: return ""
        return date.format(DateTimeFormatter.ofLocalizedDateTime(FormatStyle.MEDIUM, FormatStyle.SHORT).withLocale(Locale.getDefault()))
    }

    /** "21 окт. 2026 г." */
    fun date(value: String?): String {
        val zoned = parseDateTime(value)
        val date = zoned?.toLocalDate() ?: parseDate(value) ?: return ""
        return date.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM).withLocale(Locale.getDefault()))
    }

    fun date(value: LocalDate?): String {
        if (value == null) return ""
        return value.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM).withLocale(Locale.getDefault()))
    }

    /** "14:05" */
    fun time(value: String?): String {
        val date = parseDateTime(value) ?: return ""
        return date.format(DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT).withLocale(Locale.getDefault()))
    }

    /** "5 минут назад", "вчера", or a date for older values. */
    fun relative(value: String?): String {
        val date = parseDateTime(value) ?: return ""
        val millis = date.toInstant().toEpochMilli()
        val now = System.currentTimeMillis()
        if (abs(now - millis) < DateUtils.MINUTE_IN_MILLIS) {
            return DateUtils.getRelativeTimeSpanString(millis, now, DateUtils.SECOND_IN_MILLIS).toString()
        }
        if (abs(now - millis) > 7 * DateUtils.DAY_IN_MILLIS) {
            return dateTime(value)
        }
        return DateUtils.getRelativeTimeSpanString(millis, now, DateUtils.MINUTE_IN_MILLIS, DateUtils.FORMAT_ABBREV_RELATIVE).toString()
    }

    /** Short timestamp for lists: time for today, date otherwise. */
    fun shortStamp(value: String?): String {
        val date = parseDateTime(value) ?: return ""
        val today = LocalDate.now()
        return if (date.toLocalDate() == today) {
            date.format(DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT).withLocale(Locale.getDefault()))
        } else {
            date.format(DateTimeFormatter.ofPattern("dd.MM.yy", Locale.getDefault()))
        }
    }

    fun number(value: Double?, maxFractionDigits: Int = 2): String {
        if (value == null) return ""
        val format = NumberFormat.getNumberInstance(Locale.getDefault()).apply {
            maximumFractionDigits = maxFractionDigits
            minimumFractionDigits = 0
        }
        return format.format(value)
    }

    fun number(value: Long?): String {
        if (value == null) return ""
        return NumberFormat.getIntegerInstance(Locale.getDefault()).format(value)
    }

    /** "1 250 000 ₸" */
    fun money(amount: Double?, currency: String? = null): String {
        if (amount == null) return ""
        val formatted = number(amount)
        val symbol = currencySymbol(currency)
        return if (symbol.isEmpty()) formatted else "$formatted $symbol"
    }

    fun currencySymbol(currency: String?): String = when (val code = currency?.trim()?.uppercase()) {
        null, "" -> ""
        "KZT" -> "₸"
        "RUB" -> "₽"
        "USD" -> "$"
        "EUR" -> "€"
        "CNY" -> "¥"
        "UZS" -> "сўм"
        "KGS" -> "сом"
        else -> code
    }

    /** "Иван Петров" */
    fun fullName(name: String?, lastName: String? = null, middleName: String? = null): String =
        listOfNotNull(
            name?.trim()?.takeIf { it.isNotEmpty() },
            middleName?.trim()?.takeIf { it.isNotEmpty() },
            lastName?.trim()?.takeIf { it.isNotEmpty() },
        ).joinToString(" ")

    /** "ИП" for "Иван Петров". */
    fun initials(name: String?): String {
        val parts = name.orEmpty().trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
        return when {
            parts.isEmpty() -> "?"
            parts.size == 1 -> parts[0].take(2).uppercase()
            else -> (parts[0].take(1) + parts[1].take(1)).uppercase()
        }
    }

    /** "1,5 МБ" */
    fun fileSize(bytes: Long?): String {
        if (bytes == null || bytes < 0) return ""
        val units = listOf("B", "KB", "MB", "GB", "TB")
        var value = bytes.toDouble()
        var unit = 0
        while (value >= 1024 && unit < units.lastIndex) {
            value /= 1024
            unit++
        }
        return number(value, if (unit == 0) 0 else 1) + " " + units[unit]
    }

    /** Converts a date picker value (UTC midnight millis) to `YYYY-MM-DD`. */
    fun isoDateFromUtcMillis(millis: Long): String =
        Instant.ofEpochMilli(millis).atZone(ZoneId.of("UTC")).toLocalDate().toString()

    /** Converts `YYYY-MM-DD` to the UTC midnight millis expected by the date picker. */
    fun utcMillisFromIsoDate(value: String?): Long? =
        parseDate(value)?.atStartOfDay(ZoneId.of("UTC"))?.toInstant()?.toEpochMilli()
}
