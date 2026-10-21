package com.appswebnetkz.crm369.feature.conferences

import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Option
import kotlinx.serialization.json.JsonObject

/** `ConfUser` / `creator`. */
data class ConferenceUser(
    val id: Long,
    val name: String,
    val email: String?,
    val avatarUrl: String?,
) {
    fun toOption(): Option = Option(value = id.toString(), label = name, subtitle = email)

    companion object {
        fun fromJson(json: JsonObject?): ConferenceUser? {
            if (json == null) return null
            val id = json.long("id") ?: return null
            val email = json.text("email")
            return ConferenceUser(
                id = id,
                name = Formatters.fullName(json.str("name"), json.str("last_name")).ifBlank { email ?: id.toString() },
                email = email,
                avatarUrl = json.text("avatar"),
            )
        }
    }
}

/** `ConfItem`. */
data class ConferenceItem(
    val id: Long,
    val title: String,
    val description: String?,
    val startsAt: String?,
    val endedAt: String?,
    val status: String,
    val allowExternalGuests: Boolean,
    val externalJoinUrl: String?,
    val invitedUsersCount: Int,
    val creator: ConferenceUser?,
) {
    val isEnded: Boolean get() = status == STATUS_ENDED

    companion object {
        const val STATUS_LIVE = "live"
        const val STATUS_SCHEDULED = "scheduled"
        const val STATUS_ENDED = "ended"

        fun fromJson(json: JsonObject): ConferenceItem = ConferenceItem(
            id = json.long("id") ?: 0L,
            title = json.str("title").orEmpty(),
            description = json.text("description"),
            startsAt = json.text("starts_at"),
            endedAt = json.text("ended_at"),
            status = json.str("status") ?: STATUS_SCHEDULED,
            allowExternalGuests = json.boolOr("allow_external_guests", false),
            externalJoinUrl = json.text("external_join_url"),
            invitedUsersCount = json.int("invited_users_count") ?: 0,
            creator = ConferenceUser.fromJson(json.obj("creator")),
        )
    }
}

/** `invited_users[]` of `ConfDetail`. */
data class ConferenceInvitation(
    val id: Long,
    val invitedAt: String?,
    val joinedAt: String?,
    val lastOpenedAt: String?,
    val user: ConferenceUser?,
) {
    companion object {
        fun fromJson(json: JsonObject): ConferenceInvitation = ConferenceInvitation(
            id = json.long("id") ?: 0L,
            invitedAt = json.text("invited_at"),
            joinedAt = json.text("joined_at"),
            lastOpenedAt = json.text("last_opened_at"),
            user = ConferenceUser.fromJson(json.obj("user")),
        )
    }
}

/** `ConfDetail`: [ConferenceItem] + room data, permissions, invitations and links. */
data class ConferenceDetail(
    val conference: ConferenceItem,
    val providerLabel: String?,
    val roomName: String?,
    val canManage: Boolean,
    val canInvite: Boolean,
    val invitations: List<ConferenceInvitation>,
    val webPath: String,
    val publicUrl: String?,
) {
    /** Guest link (only when external guests are allowed). */
    val guestLink: String?
        get() = if (conference.allowExternalGuests) publicUrl ?: conference.externalJoinUrl else null

    companion object {
        fun fromJson(json: JsonObject): ConferenceDetail {
            val conference = ConferenceItem.fromJson(json)
            val can = json.obj("can")
            return ConferenceDetail(
                conference = conference,
                providerLabel = json.text("provider_label"),
                roomName = json.text("room_name"),
                canManage = can.boolOr("manage", false),
                canInvite = can.boolOr("invite", false),
                invitations = json.objects("invited_users").map { ConferenceInvitation.fromJson(it) },
                webPath = json.text("web_path") ?: "/conferences/${conference.id}",
                publicUrl = json.text("public_url"),
            )
        }
    }
}

/** `GET conferences`: groups current / upcoming / past, users that can be invited, provider. */
data class ConferencesOverview(
    val current: List<ConferenceItem>,
    val upcoming: List<ConferenceItem>,
    val past: List<ConferenceItem>,
    val availableUsers: List<ConferenceUser>,
    val providerLabel: String?,
) {
    val isEmpty: Boolean get() = current.isEmpty() && upcoming.isEmpty() && past.isEmpty()

    companion object {
        fun fromJson(data: JsonObject): ConferencesOverview {
            val all = data.objects("conferences").map { ConferenceItem.fromJson(it) }
            val groups = data.obj("conferenceGroups")
            return ConferencesOverview(
                current = if (groups != null) {
                    groups.objects("current").map { ConferenceItem.fromJson(it) }
                } else {
                    all.filter { it.status == ConferenceItem.STATUS_LIVE }
                },
                upcoming = if (groups != null) {
                    groups.objects("upcoming").map { ConferenceItem.fromJson(it) }
                } else {
                    all.filter { it.status == ConferenceItem.STATUS_SCHEDULED }
                },
                past = if (groups != null) {
                    groups.objects("past").map { ConferenceItem.fromJson(it) }
                } else {
                    all.filter { it.status == ConferenceItem.STATUS_ENDED }
                },
                availableUsers = data.objects("availableUsers").mapNotNull { ConferenceUser.fromJson(it) },
                providerLabel = data.obj("provider").text("label"),
            )
        }
    }
}

/** Data of the detail screen: the conference and the users that can be invited. */
data class ConferenceDetailData(
    val detail: ConferenceDetail,
    val availableUsers: List<ConferenceUser>,
)

data class ConferenceInviteResult(
    val invitedCount: Int,
    val detail: ConferenceDetail,
)

/** Conference API calls (`/api/mobile/v1/conferences`). */
class ConferencesRepository(private val api: ApiClient) {

    suspend fun overview(): ConferencesOverview = ConferencesOverview.fromJson(api.get("conferences").dataObject())

    suspend fun conference(id: Long): ConferenceDetailData {
        val data = api.get("conferences/$id").dataObject()
        return ConferenceDetailData(
            detail = ConferenceDetail.fromJson(data.obj("conference") ?: data),
            availableUsers = data.objects("availableUsers").mapNotNull { ConferenceUser.fromJson(it) },
        )
    }

    /** `POST conferences` (JSON: title, description, starts_at UTC or null, allow_external_guests, invited_user_ids). */
    suspend fun create(body: JsonObject): ConferenceDetail =
        ConferenceDetail.fromJson(api.post("conferences", body).dataObject())

    suspend fun invite(id: Long, userIds: List<Long>): ConferenceInviteResult {
        val response = api.post("conferences/$id/invitations", jsonObjectOf("invited_user_ids" to userIds))
        return ConferenceInviteResult(
            invitedCount = response.int("invited_count") ?: userIds.size,
            detail = ConferenceDetail.fromJson(response.dataObject()),
        )
    }

    suspend fun end(id: Long): ConferenceDetail = ConferenceDetail.fromJson(api.patch("conferences/$id/end").dataObject())
}
