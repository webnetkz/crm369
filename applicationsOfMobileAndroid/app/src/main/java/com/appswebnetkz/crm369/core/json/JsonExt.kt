package com.appswebnetkz.crm369.core.json

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonObjectBuilder
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonArray
import kotlinx.serialization.json.booleanOrNull
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.longOrNull

/**
 * Null-safe helpers for reading the CRM369 API responses with the kotlinx JsonElement API.
 *
 * All readers return null (or an empty collection) when a key is missing, null or of an
 * unexpected type, so screens never crash because of a server-side shape change.
 */
val CrmJson: Json = Json {
    ignoreUnknownKeys = true
    isLenient = true
}

val EmptyJsonObject: JsonObject = JsonObject(emptyMap())

fun parseJsonObject(text: String): JsonObject {
    if (text.isBlank()) return EmptyJsonObject
    val element = try {
        CrmJson.parseToJsonElement(text)
    } catch (_: Exception) {
        return EmptyJsonObject
    }
    return when (element) {
        is JsonObject -> element
        is JsonArray -> JsonObject(mapOf("data" to element))
        else -> EmptyJsonObject
    }
}

fun JsonElement?.asObj(): JsonObject? = this as? JsonObject

fun JsonElement?.asArr(): JsonArray? = this as? JsonArray

fun JsonElement?.asStr(): String? {
    val primitive = this as? JsonPrimitive ?: return null
    if (primitive is JsonNull) return null
    return primitive.content
}

fun JsonElement?.asLong(): Long? {
    val primitive = this as? JsonPrimitive ?: return null
    if (primitive is JsonNull) return null
    return primitive.longOrNull ?: primitive.doubleOrNull?.toLong() ?: primitive.content.trim().toLongOrNull()
}

fun JsonElement?.asDouble(): Double? {
    val primitive = this as? JsonPrimitive ?: return null
    if (primitive is JsonNull) return null
    return primitive.doubleOrNull ?: primitive.content.trim().replace(',', '.').toDoubleOrNull()
}

fun JsonElement?.asBool(): Boolean? {
    val primitive = this as? JsonPrimitive ?: return null
    if (primitive is JsonNull) return null
    primitive.booleanOrNull?.let { return it }
    return when (primitive.content.trim().lowercase()) {
        "1", "true", "yes", "on" -> true
        "0", "false", "no", "off", "" -> false
        else -> null
    }
}

/** String value of [key]; numbers and booleans are converted to text. Blank strings are kept. */
fun JsonObject?.str(key: String): String? = this?.get(key).asStr()

/** Non-blank string value of [key] or [fallback]. */
fun JsonObject?.strOr(key: String, fallback: String = ""): String {
    val value = str(key)
    return if (value.isNullOrBlank()) fallback else value
}

/** Non-blank string or null. */
fun JsonObject?.text(key: String): String? = str(key)?.takeIf { it.isNotBlank() }

fun JsonObject?.long(key: String): Long? = this?.get(key).asLong()

fun JsonObject?.int(key: String): Int? = this?.get(key).asLong()?.toInt()

fun JsonObject?.double(key: String): Double? = this?.get(key).asDouble()

fun JsonObject?.bool(key: String): Boolean? = this?.get(key).asBool()

fun JsonObject?.boolOr(key: String, fallback: Boolean = false): Boolean = bool(key) ?: fallback

fun JsonObject?.obj(key: String): JsonObject? = this?.get(key).asObj()

fun JsonObject?.arr(key: String): JsonArray? = this?.get(key).asArr()

/** All object items of the array at [key]. Non-object items are skipped. */
fun JsonObject?.objects(key: String): List<JsonObject> =
    arr(key)?.mapNotNull { it as? JsonObject } ?: emptyList()

/** All primitive items of the array at [key] converted to strings. */
fun JsonObject?.strings(key: String): List<String> =
    arr(key)?.mapNotNull { it.asStr() } ?: emptyList()

/** All numeric items of the array at [key]. */
fun JsonObject?.longs(key: String): List<Long> =
    arr(key)?.mapNotNull { it.asLong() } ?: emptyList()

/** Follows nested object keys, e.g. `json.path("data", "user")`. */
fun JsonObject?.path(vararg keys: String): JsonElement? {
    var current: JsonElement? = this
    for (key in keys) {
        current = (current as? JsonObject)?.get(key) ?: return null
    }
    return current
}

fun JsonObject?.pathObj(vararg keys: String): JsonObject? = path(*keys).asObj()

fun JsonObject?.pathStr(vararg keys: String): String? = path(*keys).asStr()

/** The `data` object of a standard `{ "data": {...} }` response, or the root itself. */
fun JsonObject.dataObject(): JsonObject = obj("data") ?: this

/** The `data` array of a standard `{ "data": [...] }` response. */
fun JsonObject.dataObjects(): List<JsonObject> = objects("data")

/** Converts a JSON object with primitive values into a string map (useful for custom fields). */
fun JsonObject?.toStringMap(): Map<String, String?> =
    this?.mapValues { (_, value) -> value.asStr() } ?: emptyMap()

fun JsonObjectBuilder.putValue(key: String, value: String?) {
    put(key, if (value == null) JsonNull else JsonPrimitive(value))
}

fun JsonObjectBuilder.putValue(key: String, value: Number?) {
    put(key, if (value == null) JsonNull else JsonPrimitive(value))
}

fun JsonObjectBuilder.putValue(key: String, value: Boolean?) {
    put(key, if (value == null) JsonNull else JsonPrimitive(value))
}

fun JsonObjectBuilder.putStrings(key: String, values: List<String>) {
    put(key, buildJsonArray { values.forEach { add(JsonPrimitive(it)) } })
}

fun JsonObjectBuilder.putLongs(key: String, values: List<Long>) {
    put(key, buildJsonArray { values.forEach { add(JsonPrimitive(it)) } })
}

/** Converts arbitrary Kotlin values (String, Number, Boolean, List, Map, JsonElement) into JSON. */
fun anyToJson(value: Any?): JsonElement = when (value) {
    null -> JsonNull
    is JsonElement -> value
    is String -> JsonPrimitive(value)
    is Number -> JsonPrimitive(value)
    is Boolean -> JsonPrimitive(value)
    is Map<*, *> -> JsonObject(value.entries.associate { (k, v) -> k.toString() to anyToJson(v) })
    is Iterable<*> -> JsonArray(value.map { anyToJson(it) })
    is Array<*> -> JsonArray(value.map { anyToJson(it) })
    else -> JsonPrimitive(value.toString())
}

fun jsonObjectOf(vararg pairs: Pair<String, Any?>): JsonObject =
    JsonObject(pairs.associate { (key, value) -> key to anyToJson(value) })

/**
 * Flattens a JSON object into multipart form fields using Laravel's bracket notation:
 * `{"a":{"b":1},"c":[1,2],"d":true}` becomes `a[b]=1, c[0]=1, c[1]=2, d=1`.
 * Null values are sent as empty strings (Laravel converts them back to null).
 */
fun JsonObject.toMultipartFields(): Map<String, String> {
    val result = LinkedHashMap<String, String>()
    fun visit(prefix: String, element: JsonElement) {
        when (element) {
            is JsonObject -> element.forEach { (key, value) ->
                visit(if (prefix.isEmpty()) key else "$prefix[$key]", value)
            }
            is JsonArray -> element.forEachIndexed { index, value -> visit("$prefix[$index]", value) }
            is JsonNull -> result[prefix] = ""
            is JsonPrimitive -> {
                val bool = if (element.isString) null else element.booleanOrNull
                result[prefix] = when (bool) {
                    true -> "1"
                    false -> "0"
                    null -> element.content
                }
            }
        }
    }
    forEach { (key, value) -> visit(key, value) }
    return result
}
