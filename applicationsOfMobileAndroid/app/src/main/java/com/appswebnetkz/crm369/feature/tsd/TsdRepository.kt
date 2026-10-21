package com.appswebnetkz.crm369.feature.tsd

import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.dataObjects
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.feature.equipment.EquipmentItem
import com.appswebnetkz.crm369.feature.equipment.EquipmentRepository
import com.appswebnetkz.crm369.feature.warehouses.ResolvedQr
import com.appswebnetkz.crm369.feature.warehouses.WarehousesRepository
import kotlinx.serialization.json.JsonObject

/** `TsdScan`: one QR scan of the common log (web, API or webhook). */
data class TsdScan(
    val id: Long,
    val qrCode: String,
    val normalizedQrCode: String?,
    val source: String?,
    val sourceLabel: String?,
    val deviceName: String?,
    val location: String?,
    val context: String?,
    val scannedAt: String?,
    val actorName: String?,
    val webhookName: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): TsdScan = TsdScan(
            id = json.long("id") ?: 0L,
            qrCode = json.str("qr_code").orEmpty(),
            normalizedQrCode = json.text("normalized_qr_code"),
            source = json.text("source"),
            sourceLabel = json.text("source_label") ?: json.text("source"),
            deviceName = json.text("device_name"),
            location = json.text("location"),
            context = json.text("context"),
            scannedAt = json.text("scanned_at") ?: json.text("created_at"),
            actorName = personName(json.obj("actor")),
            webhookName = json.obj("webhook").text("name"),
        )

        private fun personName(json: JsonObject?): String? {
            if (json == null) return null
            return Formatters.fullName(json.str("name"), json.str("last_name")).ifBlank { json.text("email").orEmpty() }
                .ifBlank { null }
        }
    }
}

/** `POST tsd/scans` response: the saved scan and the warehouse entity behind the code (if any). */
data class TsdScanResult(
    val scan: TsdScan,
    val resolved: ResolvedQr?,
    val message: String?,
)

/** TSD API calls (`/api/mobile/v1/tsd/scans`). */
class TsdRepository(private val api: ApiClient) {

    /** Latest scans of all users (not paginated; `per_page` 1..100). */
    suspend fun history(perPage: Int = HISTORY_SIZE): List<TsdScan> =
        api.get("tsd/scans", mapOf("per_page" to perPage)).dataObjects().map { TsdScan.fromJson(it) }

    suspend fun submit(code: String, deviceName: String?, location: String?, context: String?): TsdScanResult {
        val response = api.post(
            "tsd/scans",
            jsonObjectOf(
                "qr_code" to code,
                "device_name" to deviceName?.takeIf { it.isNotBlank() },
                "location" to location?.takeIf { it.isNotBlank() },
                "context" to context?.takeIf { it.isNotBlank() },
            ),
        )
        return TsdScanResult(
            scan = TsdScan.fromJson(response.dataObject()),
            resolved = ResolvedQr.fromJson(response.obj("resolved")),
            message = response.text("message"),
        )
    }

    companion object {
        const val HISTORY_SIZE = 50
    }
}

/** Outcome of the quick QR lookup screen. */
data class QrLookupResult(
    val code: String,
    val resolved: ResolvedQr?,
    val warehouseChecked: Boolean,
    val equipment: EquipmentItem?,
    val equipmentChecked: Boolean,
    val scan: TsdScan?,
) {
    val found: Boolean get() = resolved != null || equipment != null
}

/**
 * Quick QR lookup: the warehouse resolver (`GET warehouses/qr/{code}`) when the user can access
 * warehouses, a local `qr_code` match in the equipment list when the user can access equipment,
 * and `POST tsd/scans` (which also resolves warehouse codes) when neither is available.
 */
class QrLookup(api: ApiClient) {
    private val warehouses = WarehousesRepository(api)
    private val equipment = EquipmentRepository(api)
    private val tsd = TsdRepository(api)

    suspend fun lookup(
        code: String,
        canWarehouses: Boolean,
        canEquipment: Boolean,
        canTsd: Boolean,
        deviceName: String?,
    ): QrLookupResult {
        if (!canWarehouses && !canEquipment) {
            if (!canTsd) {
                return QrLookupResult(code, null, warehouseChecked = false, equipment = null, equipmentChecked = false, scan = null)
            }
            val result = tsd.submit(code, deviceName, null, null)
            return QrLookupResult(
                code = code,
                resolved = result.resolved,
                warehouseChecked = true,
                equipment = null,
                equipmentChecked = false,
                scan = result.scan,
            )
        }
        val resolved = if (canWarehouses) notFoundAsNull { warehouses.lookup(code) } else null
        val match = if (canEquipment) notFoundAsNull { equipment.findByQr(code) } else null
        return QrLookupResult(
            code = code,
            resolved = resolved,
            warehouseChecked = canWarehouses,
            equipment = match,
            equipmentChecked = canEquipment,
            scan = null,
        )
    }

    /** 404 (unknown code / module off) and 403 mean "not found" here; other errors propagate. */
    private suspend fun <T> notFoundAsNull(block: suspend () -> T?): T? = try {
        block()
    } catch (exception: ApiException) {
        if (exception.isNotFound || exception.isForbidden) null else throw exception
    }
}
