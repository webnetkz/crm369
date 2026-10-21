package com.appswebnetkz.crm369.core.network

import com.appswebnetkz.crm369.core.json.EmptyJsonObject
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.strings
import com.appswebnetkz.crm369.core.json.text
import java.io.IOException
import kotlinx.serialization.json.JsonObject

/**
 * An HTTP error returned by the CRM369 server.
 *
 * [errors] contains Laravel validation errors (HTTP 422) keyed by field name, e.g.
 * `name`, `company_requisites.iin` or `custom_fields.budget`.
 * [code] is the machine readable `code` field some endpoints return (for example
 * `email_not_verified` or `two_factor_setup_required` on sign in).
 */
class ApiException(
    val status: Int,
    override val message: String,
    val errors: Map<String, List<String>> = emptyMap(),
    val code: String? = null,
    val body: JsonObject = EmptyJsonObject,
) : Exception(message) {

    val isUnauthorized: Boolean get() = status == 401
    val isForbidden: Boolean get() = status == 403
    val isNotFound: Boolean get() = status == 404
    val isValidation: Boolean get() = status == 422
    val isRateLimited: Boolean get() = status == 429
    val isServerError: Boolean get() = status >= 500

    fun firstError(field: String): String? = errors[field]?.firstOrNull()

    /** The first validation message of any field, or the server message. */
    fun firstMessage(): String = errors.values.firstOrNull()?.firstOrNull() ?: message

    companion object {
        fun from(status: Int, body: JsonObject): ApiException {
            val errorsObject = body.obj("errors")
            val errors = errorsObject?.keys?.associateWith { key -> errorsObject.strings(key) }
                ?.filterValues { it.isNotEmpty() }
                ?: emptyMap()
            return ApiException(
                status = status,
                message = body.text("message") ?: "",
                errors = errors,
                code = body.text("code"),
                body = body,
            )
        }
    }
}

/** The server could not be reached (no connection, DNS failure, TLS error, timeout). */
class NetworkException(cause: Throwable) : IOException(cause.message, cause)
