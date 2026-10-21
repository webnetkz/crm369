package com.appswebnetkz.crm369.core.links

/**
 * Percent-encoding of navigation arguments (route path segments and query values).
 * Only RFC 3986 unreserved characters are kept as-is, so the value survives
 * Navigation's `Uri.decode` unchanged (`+` stays `+`, spaces become `%20`).
 */
object RouteArgs {
    private const val HEX = "0123456789ABCDEF"

    fun encode(value: String): String {
        val builder = StringBuilder()
        for (byte in value.toByteArray(Charsets.UTF_8)) {
            val code = byte.toInt() and 0xFF
            val char = code.toChar()
            if (code < 128 && (char.isLetterOrDigit() || char == '-' || char == '.' || char == '_' || char == '~')) {
                builder.append(char)
            } else {
                builder.append('%')
                builder.append(HEX[code shr 4])
                builder.append(HEX[code and 0x0F])
            }
        }
        return builder.toString()
    }

    fun decode(value: String): String {
        val bytes = ArrayList<Byte>(value.length)
        var index = 0
        while (index < value.length) {
            val char = value[index]
            if (char == '%' && index + 2 < value.length) {
                val high = HEX.indexOf(value[index + 1].uppercaseChar())
                val low = HEX.indexOf(value[index + 2].uppercaseChar())
                if (high >= 0 && low >= 0) {
                    bytes.add(((high shl 4) or low).toByte())
                    index += 3
                    continue
                }
            }
            char.toString().toByteArray(Charsets.UTF_8).forEach { bytes.add(it) }
            index++
        }
        return String(bytes.toByteArray(), Charsets.UTF_8)
    }
}
