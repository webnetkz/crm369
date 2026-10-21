package com.appswebnetkz.crm369.feature.contacts

import android.content.Context
import android.net.Uri
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
import com.appswebnetkz.crm369.core.json.toMultipartFields
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.UploadPart
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.components.PageResult
import kotlinx.serialization.json.JsonObject

/** Requisites of a contact (`company_requisites`). IIN is used for persons, the rest for companies. */
data class ContactRequisites(
    val iin: String?,
    val bin: String?,
    val legalAddress: String?,
    val actualAddress: String?,
    val bankName: String?,
    val bankBik: String?,
    val iban: String?,
    val kbe: String?,
) {
    val isEmpty: Boolean
        get() = listOf(iin, bin, legalAddress, actualAddress, bankName, bankBik, iban, kbe).all { it.isNullOrBlank() }

    companion object {
        fun fromJson(json: JsonObject?): ContactRequisites? {
            if (json == null) return null
            return ContactRequisites(
                iin = json.text("iin"),
                bin = json.text("bin"),
                legalAddress = json.text("legal_address"),
                actualAddress = json.text("actual_address"),
                bankName = json.text("bank_name"),
                bankBik = json.text("bank_bik"),
                iban = json.text("iban"),
                kbe = json.text("kbe"),
            ).takeUnless { it.isEmpty }
        }
    }
}

/** `ApiContactResource`. */
data class Contact(
    val id: Long,
    val type: String,
    val typeLabel: String,
    val name: String,
    val contactPerson: String?,
    val position: String?,
    val email: String?,
    val phone: String?,
    val notes: String?,
    val isBlacklisted: Boolean,
    val avatarUrl: String?,
    val requisites: ContactRequisites?,
    val createdAt: String?,
    val updatedAt: String?,
    val createdBy: String?,
    val updatedBy: String?,
) {
    val isCompany: Boolean get() = type == TYPE_COMPANY

    companion object {
        const val TYPE_PERSON = "person"
        const val TYPE_COMPANY = "company"

        fun fromJson(json: JsonObject): Contact = Contact(
            id = json.long("id") ?: 0L,
            type = json.str("type") ?: TYPE_PERSON,
            typeLabel = json.text("type_label") ?: json.str("type").orEmpty(),
            name = json.str("name").orEmpty(),
            contactPerson = json.text("contact_person"),
            position = json.text("position"),
            email = json.text("email"),
            phone = json.text("phone"),
            notes = json.text("notes"),
            isBlacklisted = json.boolOr("is_blacklisted", false),
            avatarUrl = json.text("avatar"),
            requisites = ContactRequisites.fromJson(json.obj("company_requisites")),
            createdAt = json.text("created_at"),
            updatedAt = json.text("updated_at"),
            createdBy = personName(json.obj("created_by")),
            updatedBy = personName(json.obj("updated_by")),
        )

        private fun personName(json: JsonObject?): String? =
            json?.let { Formatters.fullName(it.str("name"), it.str("last_name")) }?.takeIf { it.isNotBlank() }
    }
}

data class ContactComment(
    val id: Long,
    val content: String,
    val authorName: String?,
    val authorAvatar: String?,
    val createdAt: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): ContactComment {
            val author = json.obj("created_by") ?: json.obj("author")
            return ContactComment(
                id = json.long("id") ?: 0L,
                content = json.text("content") ?: json.str("body").orEmpty(),
                authorName = author?.let { Formatters.fullName(it.str("name"), it.str("last_name")) }?.takeIf { it.isNotBlank() },
                authorAvatar = author?.text("avatar"),
                createdAt = json.text("created_at"),
            )
        }
    }
}

/** List metadata returned together with the first page (`can`, `available_types`). */
data class ContactsListMeta(
    val canCreatePerson: Boolean,
    val canCreateCompany: Boolean,
    val types: List<Option>,
    val total: Int?,
)

/** All contact API calls (`/api/mobile/v1/contacts`). */
class ContactsRepository(private val api: ApiClient) {

    suspend fun page(
        page: Int,
        search: String,
        type: String,
        blacklist: String,
        onMeta: (ContactsListMeta) -> Unit = {},
    ): PageResult<Contact> {
        val response = api.get(
            "contacts",
            mapOf(
                "page" to page,
                "per_page" to PER_PAGE,
                "search" to search.takeIf { it.isNotBlank() },
                "type" to type,
                "blacklist" to blacklist,
            ),
        )
        val meta = response.obj("meta")
        val can = response.obj("can")
        onMeta(
            ContactsListMeta(
                canCreatePerson = can.boolOr("create_person", false),
                canCreateCompany = can.boolOr("create_company", false),
                types = response.objects("available_types").mapNotNull { item ->
                    val value = item.text("value") ?: return@mapNotNull null
                    Option(value = value, label = item.text("label") ?: value)
                },
                total = meta.int("total"),
            ),
        )
        return PageResult(
            items = response.dataObjects().map { Contact.fromJson(it) },
            hasMore = (meta.int("current_page") ?: 1) < (meta.int("last_page") ?: 1),
            total = meta.int("total"),
        )
    }

    suspend fun contact(id: Long): Contact = Contact.fromJson(api.get("contacts/$id").dataObject())

    suspend fun comments(id: Long): List<ContactComment> =
        api.get("contacts/$id/comments").dataObjects().map { ContactComment.fromJson(it) }

    suspend fun addComment(id: Long, content: String): ContactComment =
        ContactComment.fromJson(api.post("contacts/$id/comments", jsonObjectOf("content" to content)).dataObject())

    /** Creates a contact; [avatar] (content Uri) switches the request to multipart. */
    suspend fun create(context: Context, body: JsonObject, avatar: Uri?): Contact {
        val response = if (avatar != null) {
            api.multipart("contacts", body.toMultipartFields(), listOf(UploadPart.fromUri(context, "avatar", avatar)))
        } else {
            api.post("contacts", body)
        }
        return Contact.fromJson(response.dataObject())
    }

    suspend fun update(context: Context, id: Long, body: JsonObject, avatar: Uri?): Contact {
        val response = if (avatar != null) {
            api.multipart(
                "contacts/$id",
                body.toMultipartFields(),
                listOf(UploadPart.fromUri(context, "avatar", avatar)),
                methodOverride = "PATCH",
            )
        } else {
            api.patch("contacts/$id", body)
        }
        return Contact.fromJson(response.dataObject())
    }

    suspend fun delete(id: Long) {
        api.delete("contacts/$id")
    }

    companion object {
        const val PER_PAGE = 50
    }
}
