package com.appswebnetkz.crm369.feature.equipment

import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.dataObjects
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Option
import kotlinx.serialization.json.JsonObject

/** `UserRef {id, name, last_name?, email}`. */
data class EquipmentUser(
    val id: Long,
    val name: String,
    val email: String?,
) {
    fun toOption(): Option = Option(value = id.toString(), label = name, subtitle = email)

    companion object {
        fun fromJson(json: JsonObject?): EquipmentUser? {
            if (json == null) return null
            val id = json.long("id") ?: return null
            val email = json.text("email")
            val name = Formatters.fullName(json.str("name"), json.str("last_name")).ifBlank { email ?: id.toString() }
            return EquipmentUser(id = id, name = name, email = email)
        }
    }
}

/** `Equipment`. */
data class EquipmentItem(
    val id: Long,
    val name: String,
    val qrCode: String,
    val status: String,
    val statusLabel: String,
    val issuedTo: EquipmentUser?,
    val responsible: EquipmentUser?,
    val createdBy: EquipmentUser?,
    val updatedBy: EquipmentUser?,
    val createdAt: String?,
    val updatedAt: String?,
) {
    /** Codes are compared trimmed and case-insensitively (scanners may change the case). */
    fun matchesQr(code: String): Boolean {
        val needle = code.trim()
        return needle.isNotEmpty() && qrCode.trim().equals(needle, ignoreCase = true)
    }

    /** Client-side search: name, QR code, employees and their e-mails. */
    fun matches(query: String): Boolean {
        val needle = query.trim()
        if (needle.isEmpty()) return true
        return listOfNotNull(name, qrCode, statusLabel, issuedTo?.name, issuedTo?.email, responsible?.name, responsible?.email)
            .any { it.contains(needle, ignoreCase = true) }
    }

    companion object {
        const val STATUS_ON_BALANCE = "on_balance"
        const val STATUS_ISSUED = "issued"
        const val STATUS_MAINTENANCE = "maintenance"
        const val STATUS_REPAIR = "repair"
        const val STATUS_WRITTEN_OFF = "written_off"

        fun fromJson(json: JsonObject): EquipmentItem {
            val status = json.str("status") ?: STATUS_ON_BALANCE
            return EquipmentItem(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                qrCode = json.str("qr_code").orEmpty(),
                status = status,
                statusLabel = json.text("status_label") ?: status,
                issuedTo = EquipmentUser.fromJson(json.obj("issued_to_user")),
                responsible = EquipmentUser.fromJson(json.obj("responsible_user")),
                createdBy = EquipmentUser.fromJson(json.obj("created_by")),
                updatedBy = EquipmentUser.fromJson(json.obj("updated_by")),
                createdAt = json.text("created_at"),
                updatedAt = json.text("updated_at"),
            )
        }
    }
}

/** `status_options[]` of `GET equipment`. */
data class EquipmentStatusOption(
    val value: String,
    val label: String,
    val description: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): EquipmentStatusOption? {
            val value = json.text("value") ?: return null
            return EquipmentStatusOption(value = value, label = json.text("label") ?: value, description = json.text("description"))
        }
    }
}

data class EquipmentList(
    val items: List<EquipmentItem>,
    val statuses: List<EquipmentStatusOption>,
)

/** All equipment API calls (`/api/mobile/v1/equipment`). The list is not paginated. */
class EquipmentRepository(private val api: ApiClient) {

    suspend fun list(): EquipmentList {
        val response = api.get("equipment")
        return EquipmentList(
            items = response.dataObjects().map { EquipmentItem.fromJson(it) },
            statuses = response.objects("status_options").mapNotNull { EquipmentStatusOption.fromJson(it) },
        )
    }

    suspend fun item(id: Long): EquipmentItem = EquipmentItem.fromJson(api.get("equipment/$id").dataObject())

    suspend fun create(body: JsonObject): EquipmentItem = EquipmentItem.fromJson(api.post("equipment", body).dataObject())

    /** Full update: the body must contain the current `qr_code` (omitting it generates a new code). */
    suspend fun update(id: Long, body: JsonObject): EquipmentItem =
        EquipmentItem.fromJson(api.patch("equipment/$id", body).dataObject())

    /** Equipment codes are not resolved by the warehouse lookup: match `qr_code` in the list. */
    suspend fun findByQr(code: String): EquipmentItem? = list().items.firstOrNull { it.matchesQr(code) }
}
