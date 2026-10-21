package com.appswebnetkz.crm369.feature.directories

import android.content.Context
import android.net.Uri
import com.appswebnetkz.crm369.core.json.EmptyJsonObject
import com.appswebnetkz.crm369.core.json.asBool
import com.appswebnetkz.crm369.core.json.asDouble
import com.appswebnetkz.crm369.core.json.asStr
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.dataObjects
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.UploadPart
import com.appswebnetkz.crm369.core.util.Formatters
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject

/** Column of a directory schema. */
data class DirectoryColumn(
    val key: String,
    val label: String,
    val type: String,
    val isRequired: Boolean,
) {
    companion object {
        const val TYPE_TEXT = "text"
        const val TYPE_TEXTAREA = "textarea"
        const val TYPE_NUMBER = "number"
        const val TYPE_DATE = "date"
        const val TYPE_BOOLEAN = "boolean"

        val TYPES: List<String> = listOf(TYPE_TEXT, TYPE_TEXTAREA, TYPE_NUMBER, TYPE_DATE, TYPE_BOOLEAN)

        fun fromJson(json: JsonObject): DirectoryColumn {
            val key = json.str("key").orEmpty()
            return DirectoryColumn(
                key = key,
                label = json.text("label") ?: key,
                type = json.text("type") ?: TYPE_TEXT,
                isRequired = json.boolOr("is_required", false),
            )
        }
    }
}

/** Record of a directory: `values` keyed by column key. */
data class DirectoryRecord(
    val id: Long,
    val values: JsonObject,
    val createdAt: String?,
    val updatedAt: String?,
    val creatorName: String?,
    val updaterName: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): DirectoryRecord = DirectoryRecord(
            id = json.long("id") ?: 0L,
            values = json.obj("values") ?: EmptyJsonObject,
            createdAt = json.text("created_at"),
            updatedAt = json.text("updated_at"),
            creatorName = personName(json.obj("creator")),
            updaterName = personName(json.obj("updater")),
        )
    }
}

/** `Directory` of the mobile API (records only in `GET directories/{id}`). */
data class ReferenceDirectory(
    val id: Long,
    val name: String,
    val slug: String,
    val description: String?,
    val csvExchangeEnabled: Boolean,
    val columns: List<DirectoryColumn>,
    val recordsCount: Int,
    val records: List<DirectoryRecord>,
    val createdAt: String?,
    val updatedAt: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): ReferenceDirectory {
            val records = json.objects("records").map { DirectoryRecord.fromJson(it) }
            return ReferenceDirectory(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                slug = json.str("slug").orEmpty(),
                description = json.text("description"),
                csvExchangeEnabled = json.boolOr("csv_exchange_enabled", false),
                columns = json.objects("columns").map { DirectoryColumn.fromJson(it) },
                recordsCount = json.int("records_count") ?: records.size,
                records = records,
                createdAt = json.text("created_at"),
                updatedAt = json.text("updated_at"),
            )
        }
    }
}

private fun personName(json: JsonObject?): String? {
    if (json == null) return null
    return Formatters.fullName(json.str("name"), json.str("last_name")).ifBlank { null } ?: json.text("email")
}

/** Human readable value of [column] in [values] (null when empty). */
internal fun formatDirectoryValue(column: DirectoryColumn, values: JsonObject, yes: String, no: String): String? {
    val element = values[column.key] ?: return null
    if (element is JsonNull) return null
    return when (column.type) {
        DirectoryColumn.TYPE_BOOLEAN -> when (element.asBool()) {
            true -> yes
            false -> no
            null -> null
        }
        DirectoryColumn.TYPE_NUMBER -> element.asDouble()?.let { Formatters.number(it, 4) } ?: element.asStr()
        DirectoryColumn.TYPE_DATE -> element.asStr()?.let { raw -> Formatters.date(raw).ifBlank { raw } }
        else -> element.asStr()?.takeIf { it.isNotBlank() }
    }
}

/** Directories API (`/api/mobile/v1/directories`). */
class DirectoriesRepository(private val api: ApiClient) {

    suspend fun directories(): List<ReferenceDirectory> =
        api.get("directories").dataObjects().map { ReferenceDirectory.fromJson(it) }

    suspend fun directory(id: Long): ReferenceDirectory =
        ReferenceDirectory.fromJson(api.get("directories/$id").dataObject())

    suspend fun create(body: JsonObject): ReferenceDirectory =
        ReferenceDirectory.fromJson(api.post("directories", body).dataObject())

    /** Full update: name, slug, description, CSV flag and every column. */
    suspend fun update(id: Long, body: JsonObject): ReferenceDirectory =
        ReferenceDirectory.fromJson(api.patch("directories/$id", body).dataObject())

    suspend fun delete(id: Long) {
        api.delete("directories/$id")
    }

    suspend fun createRecord(directoryId: Long, values: JsonObject) {
        api.post("directories/$directoryId/records", jsonObjectOf("values" to values))
    }

    /** Replaces all values of the record. */
    suspend fun updateRecord(directoryId: Long, recordId: Long, values: JsonObject) {
        api.patch("directories/$directoryId/records/$recordId", jsonObjectOf("values" to values))
    }

    suspend fun deleteRecord(directoryId: Long, recordId: Long) {
        api.delete("directories/$directoryId/records/$recordId")
    }

    /** Imports a CSV file; returns the number of imported rows. */
    suspend fun importCsv(context: Context, directoryId: Long, file: Uri, delimiter: String?): Int {
        val response = api.multipart(
            "directories/$directoryId/import",
            mapOf("delimiter" to delimiter),
            listOf(UploadPart.fromUri(context, "file", file)),
        )
        return response.dataObject().int("imported_count") ?: 0
    }

    fun exportUrl(directoryId: Long, delimiter: String?): String =
        api.apiUrl("directories/$directoryId/export", mapOf("delimiter" to delimiter))

    fun templateUrl(directoryId: Long, delimiter: String?): String =
        api.apiUrl("directories/$directoryId/template", mapOf("delimiter" to delimiter))
}
