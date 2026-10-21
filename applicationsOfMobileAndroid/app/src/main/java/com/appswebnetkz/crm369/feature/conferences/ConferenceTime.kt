package com.appswebnetkz.crm369.feature.conferences

import java.time.LocalDateTime
import java.time.ZoneId
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter
import java.util.Locale

/** Conversion of the form's local date-time into the UTC value expected by `POST conferences`. */
internal object ConferenceTime {
    private val UTC_FORMAT: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:00'Z'", Locale.ROOT)

    /**
     * "YYYY-MM-DD HH:MM" in [zone] → "YYYY-MM-DDTHH:MM:00Z" (UTC); blank → null (the conference
     * starts now). Throws [java.time.format.DateTimeParseException] for malformed values.
     */
    fun toUtcIso(local: String, zone: ZoneId = ZoneId.systemDefault()): String? {
        val text = local.trim()
        if (text.isEmpty()) return null
        val dateTime = LocalDateTime.parse(text.replace(' ', 'T'))
        return dateTime.atZone(zone).withZoneSameInstant(ZoneOffset.UTC).format(UTC_FORMAT)
    }
}
