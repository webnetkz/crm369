package com.appswebnetkz.crm369.feature.warehouses

import com.appswebnetkz.crm369.core.json.bool
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.dataObjects
import com.appswebnetkz.crm369.core.json.double
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.strings
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.links.RouteArgs
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.PageResult
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.coroutineScope
import kotlinx.serialization.json.JsonObject

/** `{id, name, qr_code}` reference to a warehouse node (`WarehouseNode`, item place). */
data class WarehouseNodeRef(
    val id: Long,
    val name: String,
    val qrCode: String?,
) {
    companion object {
        fun fromJson(json: JsonObject?): WarehouseNodeRef? {
            if (json == null) return null
            return WarehouseNodeRef(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                qrCode = json.text("qr_code"),
            )
        }
    }
}

/** Exact storage address (`Location`): `path` is "Склад / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001". */
data class WarehouseLocation(
    val warehouse: String?,
    val row: String?,
    val column: String?,
    val floor: String?,
    val place: String?,
    val path: String,
) {
    companion object {
        fun fromJson(json: JsonObject?): WarehouseLocation? {
            if (json == null) return null
            val segments = json.strings("segments").filter { it.isNotBlank() }
            return WarehouseLocation(
                warehouse = json.text("warehouse"),
                row = json.text("row"),
                column = json.text("column"),
                floor = json.text("floor"),
                place = json.text("place"),
                path = json.text("path") ?: segments.joinToString(" / "),
            )
        }
    }
}

data class WarehousePlace(
    val id: Long,
    val name: String,
    val qrCode: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): WarehousePlace = WarehousePlace(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            qrCode = json.text("qr_code"),
        )
    }
}

data class WarehouseFloor(
    val id: Long,
    val name: String,
    val qrCode: String?,
    val placeCount: Int,
    val places: List<WarehousePlace>,
) {
    companion object {
        fun fromJson(json: JsonObject): WarehouseFloor {
            val places = json.objects("places").map { WarehousePlace.fromJson(it) }
            return WarehouseFloor(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                qrCode = json.text("qr_code"),
                placeCount = json.int("place_count") ?: places.size,
                places = places,
            )
        }
    }
}

data class WarehouseColumn(
    val id: Long,
    val name: String,
    val qrCode: String?,
    val floorCount: Int,
    val placeCount: Int,
    val floors: List<WarehouseFloor>,
) {
    companion object {
        fun fromJson(json: JsonObject): WarehouseColumn {
            val floors = json.objects("floors").map { WarehouseFloor.fromJson(it) }
            return WarehouseColumn(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                qrCode = json.text("qr_code"),
                floorCount = json.int("floor_count") ?: floors.size,
                placeCount = json.int("place_count") ?: floors.sumOf { it.placeCount },
                floors = floors,
            )
        }
    }
}

data class WarehouseRow(
    val id: Long,
    val name: String,
    val qrCode: String?,
    val columnCount: Int,
    val floorCount: Int,
    val placeCount: Int,
    val columns: List<WarehouseColumn>,
) {
    companion object {
        fun fromJson(json: JsonObject): WarehouseRow {
            val columns = json.objects("columns").map { WarehouseColumn.fromJson(it) }
            return WarehouseRow(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                qrCode = json.text("qr_code"),
                columnCount = json.int("column_count") ?: columns.size,
                floorCount = json.int("floor_count") ?: columns.sumOf { it.floorCount },
                placeCount = json.int("place_count") ?: columns.sumOf { it.placeCount },
                columns = columns,
            )
        }
    }
}

/** Floor found in the hierarchy together with its parents (title and path of the floor screen). */
data class WarehouseFloorPath(
    val warehouseName: String,
    val row: WarehouseRow,
    val column: WarehouseColumn,
    val floor: WarehouseFloor,
) {
    val label: String get() = listOf(warehouseName, row.name, column.name).filter { it.isNotBlank() }.joinToString(" / ")
}

/** `Warehouse` with the full hierarchy rows → columns → floors → places. */
data class Warehouse(
    val id: Long,
    val name: String,
    val areaSqm: Double?,
    val qrCode: String?,
    val rowCount: Int,
    val columnCount: Int,
    val floorCount: Int,
    val placeCount: Int,
    val rows: List<WarehouseRow>,
    val createdAt: String?,
    val updatedAt: String?,
    val createdBy: String?,
    val updatedBy: String?,
) {
    fun findFloor(floorId: Long): WarehouseFloorPath? {
        for (row in rows) {
            for (column in row.columns) {
                for (floor in column.floors) {
                    if (floor.id == floorId) return WarehouseFloorPath(name, row, column, floor)
                }
            }
        }
        return null
    }

    companion object {
        fun fromJson(json: JsonObject): Warehouse {
            val rows = json.objects("rows").map { WarehouseRow.fromJson(it) }
            return Warehouse(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                areaSqm = json.double("area_sqm"),
                qrCode = json.text("qr_code"),
                rowCount = json.int("row_count") ?: rows.size,
                columnCount = json.int("column_count") ?: rows.sumOf { it.columnCount },
                floorCount = json.int("floor_count") ?: rows.sumOf { it.floorCount },
                placeCount = json.int("place_count") ?: rows.sumOf { it.placeCount },
                rows = rows,
                createdAt = json.text("created_at"),
                updatedAt = json.text("updated_at"),
                createdBy = personName(json.obj("created_by")),
                updatedBy = personName(json.obj("updated_by")),
            )
        }

        private fun personName(json: JsonObject?): String? =
            json?.let { Formatters.fullName(it.str("name"), it.str("last_name")) }?.takeIf { it.isNotBlank() }
    }
}

/** `meta` of `GET warehouses`. */
data class WarehousesSummary(
    val total: Int,
    val totalAreaSqm: Double?,
    val rowCount: Int,
    val columnCount: Int,
    val floorCount: Int,
    val placeCount: Int,
) {
    companion object {
        fun fromJson(json: JsonObject?, warehouses: List<Warehouse>): WarehousesSummary = WarehousesSummary(
            total = json.int("total") ?: warehouses.size,
            totalAreaSqm = json.double("total_area_sqm") ?: warehouses.sumOf { it.areaSqm ?: 0.0 },
            rowCount = json.int("row_count") ?: warehouses.sumOf { it.rowCount },
            columnCount = json.int("column_count") ?: warehouses.sumOf { it.columnCount },
            floorCount = json.int("floor_count") ?: warehouses.sumOf { it.floorCount },
            placeCount = json.int("place_count") ?: warehouses.sumOf { it.placeCount },
        )
    }
}

data class WarehousesOverview(
    val warehouses: List<Warehouse>,
    val summary: WarehousesSummary,
)

/** `WarehouseItem` (paged `GET warehouses/{id}/items`). */
data class WarehouseItem(
    val id: Long,
    val title: String,
    val sku: String?,
    val qrCode: String?,
    val quantity: Double?,
    val notes: String?,
    val place: WarehouseNodeRef?,
    val location: WarehouseLocation?,
) {
    /** Client-side search over the loaded pages. */
    fun matches(query: String): Boolean {
        val needle = query.trim()
        if (needle.isEmpty()) return true
        return listOfNotNull(title, sku, qrCode, notes, location?.path, place?.name)
            .any { it.contains(needle, ignoreCase = true) }
    }

    companion object {
        fun fromJson(json: JsonObject): WarehouseItem {
            val details = json.obj("details")
            return WarehouseItem(
                id = json.long("id") ?: 0L,
                title = json.text("title") ?: json.str("name").orEmpty(),
                sku = json.text("sku") ?: details.text("sku"),
                qrCode = json.text("qr_code"),
                quantity = json.double("quantity") ?: details.double("quantity"),
                notes = json.text("notes") ?: details.text("notes"),
                place = WarehouseNodeRef.fromJson(json.obj("place")),
                location = WarehouseLocation.fromJson(json.obj("location")),
            )
        }
    }
}

data class FloorPlaceItem(
    val id: Long,
    val name: String,
    val sku: String?,
    val qrCode: String?,
    val quantity: Double?,
) {
    companion object {
        fun fromJson(json: JsonObject): FloorPlaceItem = FloorPlaceItem(
            id = json.long("id") ?: 0L,
            name = json.text("name") ?: json.str("title").orEmpty(),
            sku = json.text("sku"),
            qrCode = json.text("qr_code"),
            quantity = json.double("quantity"),
        )
    }
}

data class FloorPlace(
    val id: Long,
    val name: String,
    val qrCode: String?,
    val itemCount: Int,
    val items: List<FloorPlaceItem>,
) {
    companion object {
        fun fromJson(json: JsonObject): FloorPlace {
            val items = json.objects("items").map { FloorPlaceItem.fromJson(it) }
            return FloorPlace(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                qrCode = json.text("qr_code"),
                itemCount = json.int("item_count") ?: items.size,
                items = items,
            )
        }
    }
}

/** Floor screen: places with their items plus the floor position in the warehouse hierarchy. */
data class FloorScreenData(
    val floorId: Long,
    val places: List<FloorPlace>,
    val warehouseName: String,
    val location: WarehouseFloorPath?,
)

/** Item stored at a resolved address (`contents[]`, at most 100). */
data class ResolvedContent(
    val id: Long,
    val name: String,
    val sku: String?,
    val quantity: Double?,
    val qrCode: String?,
    val location: WarehouseLocation?,
) {
    companion object {
        fun fromJson(json: JsonObject): ResolvedContent = ResolvedContent(
            id = json.long("id") ?: 0L,
            name = json.text("name") ?: json.str("title").orEmpty(),
            sku = json.text("sku"),
            quantity = json.double("quantity"),
            qrCode = json.text("qr_code"),
            location = WarehouseLocation.fromJson(json.obj("location")),
        )
    }
}

/** QR lookup result (`Resolved`): item, warehouse, row, column, floor or place. */
data class ResolvedQr(
    val entityType: String,
    val entityTypeLabel: String?,
    val id: Long?,
    val title: String,
    val qrCode: String?,
    val inputQrCode: String?,
    val normalizedQrCode: String?,
    val warehouse: WarehouseNodeRef?,
    val place: WarehouseNodeRef?,
    val location: WarehouseLocation?,
    val areaSqm: Double?,
    val rowCount: Int?,
    val columnCount: Int?,
    val floorCount: Int?,
    val placeCount: Int?,
    val itemCount: Int?,
    val quantity: Double?,
    val sku: String?,
    val notes: String?,
    val contents: List<ResolvedContent>,
    val contentsTruncated: Boolean,
) {
    val isItem: Boolean get() = entityType == TYPE_ITEM

    /** Warehouse that can be opened for this code (the top-level id of a warehouse, else its parent warehouse). */
    val warehouseId: Long?
        get() = (if (entityType == TYPE_WAREHOUSE) id else null) ?: warehouse?.id?.takeIf { it > 0L }

    /** Floor that can be opened for this code (top-level id of a floor). */
    val floorId: Long? get() = if (entityType == TYPE_FLOOR) id else null

    companion object {
        const val TYPE_ITEM = "item"
        const val TYPE_WAREHOUSE = "warehouse"
        const val TYPE_ROW = "row"
        const val TYPE_COLUMN = "column"
        const val TYPE_FLOOR = "floor"
        const val TYPE_PLACE = "place"

        fun fromJson(json: JsonObject?): ResolvedQr? {
            if (json == null || json.isEmpty()) return null
            if (json.bool("matched") == false) return null
            val details = json.obj("details")
            return ResolvedQr(
                entityType = json.str("entity_type").orEmpty(),
                entityTypeLabel = json.text("entity_type_label"),
                id = json.long("id")?.takeIf { it > 0L },
                title = json.text("title") ?: json.text("name") ?: json.str("qr_code").orEmpty(),
                qrCode = json.text("qr_code"),
                inputQrCode = json.text("input_qr_code"),
                normalizedQrCode = json.text("normalized_qr_code"),
                warehouse = WarehouseNodeRef.fromJson(json.obj("warehouse")),
                place = WarehouseNodeRef.fromJson(json.obj("place")),
                location = WarehouseLocation.fromJson(json.obj("location")),
                areaSqm = details.double("area_sqm"),
                rowCount = details.int("row_count"),
                columnCount = details.int("column_count"),
                floorCount = details.int("floor_count"),
                placeCount = details.int("place_count"),
                itemCount = details.int("item_count"),
                quantity = details.double("quantity") ?: json.double("quantity"),
                sku = details.text("sku") ?: json.text("sku"),
                notes = details.text("notes") ?: json.text("notes"),
                contents = json.objects("contents").map { ResolvedContent.fromJson(it) },
                contentsTruncated = json.boolOr("contents_truncated", false),
            )
        }
    }
}

/** Result of `POST warehouses/scan`: [resolved] is null when the code is unknown (the scan is still logged). */
data class WarehouseScanOutcome(
    val code: String,
    val resolved: ResolvedQr?,
    val message: String?,
)

/** All warehouse API calls (`/api/mobile/v1/warehouses`). */
class WarehousesRepository(private val api: ApiClient) {

    suspend fun overview(): WarehousesOverview {
        val response = api.get("warehouses")
        val warehouses = response.dataObjects().map { Warehouse.fromJson(it) }
        return WarehousesOverview(
            warehouses = warehouses,
            summary = WarehousesSummary.fromJson(response.obj("meta"), warehouses),
        )
    }

    suspend fun warehouse(id: Long): Warehouse = Warehouse.fromJson(api.get("warehouses/$id").dataObject())

    suspend fun items(warehouseId: Long, page: Int): PageResult<WarehouseItem> {
        val response = api.get("warehouses/$warehouseId/items", mapOf("page" to page, "per_page" to ITEMS_PER_PAGE))
        val meta = response.obj("meta")
        return PageResult(
            items = response.dataObjects().map { WarehouseItem.fromJson(it) },
            hasMore = (meta.int("current_page") ?: 1) < (meta.int("last_page") ?: 1),
            total = meta.int("total"),
        )
    }

    /** Places of a floor (`GET warehouses/{id}/floors/{floorId}`) plus the warehouse for the floor name/path. */
    suspend fun floor(warehouseId: Long, floorId: Long): FloorScreenData = coroutineScope {
        val (floorJson, warehouseJson) = awaitAll(
            async { api.get("warehouses/$warehouseId/floors/$floorId").dataObject() },
            async { api.get("warehouses/$warehouseId").dataObject() },
        )
        val warehouse = Warehouse.fromJson(warehouseJson)
        FloorScreenData(
            floorId = floorJson.long("id") ?: floorId,
            places = floorJson.objects("places").map { FloorPlace.fromJson(it) },
            warehouseName = warehouse.name,
            location = warehouse.findFloor(floorId),
        )
    }

    /** `POST warehouses` with the generated hierarchy (every level has at least one node). */
    suspend fun create(name: String, areaSqm: Double, rows: List<Map<String, Any?>>): Warehouse {
        val body = jsonObjectOf("name" to name, "area_sqm" to areaSqm, "rows" to rows)
        return Warehouse.fromJson(api.post("warehouses", body).dataObject())
    }

    /** `POST warehouses/scan`: resolves the code and writes it to the TSD log. */
    suspend fun scan(code: String, deviceName: String?): WarehouseScanOutcome {
        val response = api.post(
            "warehouses/scan",
            jsonObjectOf("qr_code" to code, "device_name" to deviceName?.takeIf { it.isNotBlank() }),
        )
        return WarehouseScanOutcome(
            code = code,
            resolved = ResolvedQr.fromJson(response.obj("data")),
            message = response.text("message"),
        )
    }

    /** `GET warehouses/qr/{code}` without logging a scan; throws a 404 [com.appswebnetkz.crm369.core.network.ApiException] for unknown codes. */
    suspend fun lookup(code: String): ResolvedQr? =
        ResolvedQr.fromJson(api.get("warehouses/qr/" + RouteArgs.encode(code)).obj("data"))

    companion object {
        const val ITEMS_PER_PAGE = 50
    }
}
