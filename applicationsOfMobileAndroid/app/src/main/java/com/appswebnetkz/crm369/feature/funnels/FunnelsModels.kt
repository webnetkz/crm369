package com.appswebnetkz.crm369.feature.funnels

import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.double
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.longs
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.json.toStringMap
import java.math.BigDecimal
import kotlinx.serialization.json.JsonObject

/*
 * Models of the funnels (CRM deals) API. Pure Kotlin: no Android or Compose dependencies, so the
 * parsing can be unit tested on the JVM.
 */

/** `UserRef`: {id, name, last_name?, email}. */
data class FunnelUser(
    val id: Long,
    val name: String,
    val lastName: String?,
    val email: String?,
) {
    /** "Ada Admin" (falls back to the e-mail). */
    val fullName: String
        get() = listOfNotNull(name.trim().ifBlank { null }, lastName?.trim()?.ifBlank { null })
            .joinToString(" ")
            .ifBlank { email.orEmpty() }

    companion object {
        fun fromJson(json: JsonObject?): FunnelUser? {
            val id = json.long("id") ?: return null
            return FunnelUser(
                id = id,
                name = json.str("name").orEmpty(),
                lastName = json.text("last_name"),
                email = json.text("email"),
            )
        }
    }
}

/** `{value, label}` option sent by the server (`funnelOptions.stageTypes`, `fieldTypes`). */
data class ValueLabel(
    val value: String,
    val label: String,
) {
    companion object {
        fun list(items: List<JsonObject>): List<ValueLabel> = items.mapNotNull { item ->
            val value = item.text("value") ?: return@mapNotNull null
            ValueLabel(value = value, label = item.text("label") ?: value)
        }
    }
}

/** `DealField`: custom deal field of a funnel. */
data class DealField(
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
        val TYPES: List<String> = listOf(TYPE_TEXT, TYPE_TEXTAREA, TYPE_NUMBER, TYPE_DATE)

        fun fromJson(json: JsonObject): DealField {
            val key = json.str("key").orEmpty()
            return DealField(
                key = key,
                label = json.text("label") ?: key,
                type = json.text("type") ?: TYPE_TEXT,
                isRequired = json.boolOr("is_required", false),
            )
        }
    }
}

/** Stage without deals (`stages` of the deal page, `stage` of a deal). */
data class StageRef(
    val id: Long,
    val name: String,
    val color: String?,
    val type: String,
    val sortOrder: Int,
) {
    companion object {
        const val TYPE_OPEN = "open"
        const val TYPE_WON = "won"
        const val TYPE_LOST = "lost"
        val TYPES: List<String> = listOf(TYPE_OPEN, TYPE_WON, TYPE_LOST)

        fun fromJson(json: JsonObject): StageRef = StageRef(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            color = json.text("color"),
            type = json.text("type") ?: TYPE_OPEN,
            sortOrder = json.int("sort_order") ?: 0,
        )
    }
}

/** `DealCard` (board) and `DealDetail` (deal page, create/update/move responses). */
data class Deal(
    val id: Long,
    val stageId: Long,
    val funnelId: Long?,
    val title: String,
    val companyName: String?,
    val contactName: String?,
    val contactPhone: String?,
    val contactEmail: String?,
    val amount: Double?,
    val currency: String?,
    val expectedCloseAt: String?,
    val description: String?,
    /** Custom field values as text (`custom_fields`, keys of [DealField.key]). */
    val customFields: Map<String, String?>,
    val sortOrder: Int,
    val responsible: FunnelUser?,
    val stage: StageRef?,
    val creator: FunnelUser?,
    val updater: FunnelUser?,
    val createdAt: String?,
    val updatedAt: String?,
) {
    /** Company and contact person in one line, null when both are empty. */
    val counterparty: String?
        get() = listOfNotNull(companyName, contactName).joinToString(" · ").ifBlank { null }

    companion object {
        fun fromJson(json: JsonObject): Deal {
            val stage = json.obj("stage")?.let { StageRef.fromJson(it) }
            return Deal(
                id = json.long("id") ?: 0L,
                stageId = json.long("crm_funnel_stage_id") ?: stage?.id ?: 0L,
                funnelId = json.long("crm_funnel_id"),
                title = json.str("title").orEmpty(),
                companyName = json.text("company_name"),
                contactName = json.text("contact_name"),
                contactPhone = json.text("contact_phone"),
                contactEmail = json.text("contact_email"),
                amount = json.double("amount"),
                currency = json.text("currency"),
                expectedCloseAt = json.text("expected_close_at"),
                description = json.text("description"),
                customFields = json.obj("custom_fields").toStringMap(),
                sortOrder = json.int("sort_order") ?: 0,
                responsible = FunnelUser.fromJson(json.obj("responsible_user")),
                stage = stage,
                creator = FunnelUser.fromJson(json.obj("creator")),
                updater = FunnelUser.fromJson(json.obj("updater")),
                createdAt = json.text("created_at"),
                updatedAt = json.text("updated_at"),
            )
        }
    }
}

/** `BoardStage`: a kanban column with its deals. */
data class BoardStage(
    val id: Long,
    val name: String,
    val color: String?,
    val type: String,
    val sortOrder: Int,
    val dealsCount: Int,
    val deals: List<Deal>,
) {
    val ref: StageRef
        get() = StageRef(id = id, name = name, color = color, type = type, sortOrder = sortOrder)

    companion object {
        fun fromJson(json: JsonObject): BoardStage {
            val deals = json.objects("deals").map { Deal.fromJson(it) }
            return BoardStage(
                id = json.long("id") ?: 0L,
                name = json.str("name").orEmpty(),
                color = json.text("color"),
                type = json.text("type") ?: StageRef.TYPE_OPEN,
                sortOrder = json.int("sort_order") ?: 0,
                dealsCount = json.int("deals_count") ?: deals.size,
                deals = deals,
            )
        }
    }
}

/** `stats` of a funnel board. */
data class FunnelStats(
    val dealsCount: Int,
    val activeDealsCount: Int,
    val wonDealsCount: Int,
    val lostDealsCount: Int,
    val amountSum: Double,
) {
    companion object {
        fun fromJson(json: JsonObject?): FunnelStats = FunnelStats(
            dealsCount = json.int("deals_count") ?: 0,
            activeDealsCount = json.int("active_deals_count") ?: 0,
            wonDealsCount = json.int("won_deals_count") ?: 0,
            lostDealsCount = json.int("lost_deals_count") ?: 0,
            amountSum = json.double("amount_sum") ?: 0.0,
        )
    }
}

/** User group (`groups` of a board, `availableGroups` of the page). */
data class FunnelGroup(
    val id: Long,
    val name: String,
    val description: String?,
    val usersCount: Int,
) {
    companion object {
        fun fromJson(json: JsonObject): FunnelGroup = FunnelGroup(
            id = json.long("id") ?: 0L,
            name = json.text("display_name") ?: json.str("name").orEmpty(),
            description = json.text("description"),
            usersCount = json.int("users_count") ?: 0,
        )
    }
}

/** `FunnelBoard`: the selected funnel with stages, deals, custom fields and access groups. */
data class FunnelBoard(
    val id: Long,
    val name: String,
    val slug: String?,
    val description: String?,
    val color: String?,
    val isActive: Boolean,
    val dealFields: List<DealField>,
    val groupIds: List<Long>,
    val groups: List<FunnelGroup>,
    val stages: List<BoardStage>,
    val stats: FunnelStats,
) {
    companion object {
        fun fromJson(json: JsonObject): FunnelBoard = FunnelBoard(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            slug = json.text("slug"),
            description = json.text("description"),
            color = json.text("color"),
            isActive = json.boolOr("is_active", true),
            dealFields = json.objects("deal_fields").map { DealField.fromJson(it) },
            groupIds = json.longs("group_ids"),
            groups = json.objects("groups").map { FunnelGroup.fromJson(it) },
            stages = json.objects("stages").map { BoardStage.fromJson(it) }.sortedBy { it.sortOrder },
            stats = FunnelStats.fromJson(json.obj("stats")),
        )
    }
}

/** Item of the funnel switcher (`funnels` of the page). */
data class FunnelSummary(
    val id: Long,
    val name: String,
    val description: String?,
    val color: String?,
    val isActive: Boolean,
    val stagesCount: Int,
    val dealsCount: Int,
    val dealsAmountSum: Double,
) {
    companion object {
        fun fromJson(json: JsonObject): FunnelSummary = FunnelSummary(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            description = json.text("description"),
            color = json.text("color"),
            isActive = json.boolOr("is_active", true),
            stagesCount = json.int("stages_count") ?: 0,
            dealsCount = json.int("deals_count") ?: 0,
            dealsAmountSum = json.double("deals_amount_sum") ?: 0.0,
        )
    }
}

/** `FunnelPage` (`GET funnels`, `GET funnels/{id}`). */
data class FunnelPage(
    val funnels: List<FunnelSummary>,
    val activeFunnel: FunnelBoard?,
    val availableUsers: List<FunnelUser>,
    val availableGroups: List<FunnelGroup>,
    val canManage: Boolean,
    val canCreateDeals: Boolean,
    val stageTypes: List<ValueLabel>,
    val fieldTypes: List<ValueLabel>,
) {
    companion object {
        fun fromJson(json: JsonObject): FunnelPage {
            val can = json.obj("can")
            val options = json.obj("funnelOptions")
            return FunnelPage(
                funnels = json.objects("funnels").map { FunnelSummary.fromJson(it) },
                activeFunnel = json.obj("activeFunnel")?.let { FunnelBoard.fromJson(it) },
                availableUsers = json.objects("availableUsers").mapNotNull { FunnelUser.fromJson(it) },
                availableGroups = json.objects("availableGroups").map { FunnelGroup.fromJson(it) },
                canManage = can.boolOr("manageFunnels", false),
                canCreateDeals = can.boolOr("createDeals", false),
                stageTypes = ValueLabel.list(options.objects("stageTypes")),
                fieldTypes = ValueLabel.list(options.objects("fieldTypes")),
            )
        }
    }
}

/** Funnel of the deal page (`funnel`). */
data class FunnelRef(
    val id: Long,
    val name: String,
    val color: String?,
    val isActive: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject?): FunnelRef = FunnelRef(
            id = json.long("id") ?: 0L,
            name = json.str("name").orEmpty(),
            color = json.text("color"),
            isActive = json.boolOr("is_active", true),
        )
    }
}

/** `GET funnels/{funnel}/deals/{deal}`: the deal with the context needed to show and edit it. */
data class DealPage(
    val deal: Deal,
    val funnel: FunnelRef,
    val stages: List<StageRef>,
    val dealFields: List<DealField>,
    val availableUsers: List<FunnelUser>,
) {
    companion object {
        fun fromJson(json: JsonObject): DealPage = DealPage(
            deal = Deal.fromJson(json.obj("deal") ?: json),
            funnel = FunnelRef.fromJson(json.obj("funnel")),
            stages = json.objects("stages").map { StageRef.fromJson(it) }.sortedBy { it.sortOrder },
            dealFields = json.objects("deal_fields").map { DealField.fromJson(it) },
            availableUsers = json.objects("available_users").mapNotNull { FunnelUser.fromJson(it) },
        )
    }
}

/** Text of a decimal for an input field: `12500.5` → "12500.5", `1.5E7` → "15000000" (no exponent). */
fun decimalInputText(value: Double?): String? {
    if (value == null || value.isNaN() || value.isInfinite()) return null
    return BigDecimal.valueOf(value).stripTrailingZeros().toPlainString()
}

/**
 * Normalizes a decimal typed by the user for the API: whitespace removed, comma → dot
 * ("12 500,50" → "12500.50"); null when blank. Deal amounts must be sent as strings: the
 * backend ignores non-string `amount` values.
 */
fun normalizeDecimalInput(text: String?): String? =
    text.orEmpty().filter { !it.isWhitespace() }.replace(',', '.').ifBlank { null }
