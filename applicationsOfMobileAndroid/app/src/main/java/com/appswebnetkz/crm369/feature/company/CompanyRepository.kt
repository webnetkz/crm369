package com.appswebnetkz.crm369.feature.company

import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.jsonObjectOf
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.ApiException

/** Company structure API calls (`/api/mobile/v1/company-structure`). Models: `CompanyModels.kt`. */
class CompanyRepository(private val api: ApiClient) {

    suspend fun structure(): CompanyStructure = CompanyStructure.fromJson(api.get("company-structure"))

    suspend fun user(userId: Long): CompanyUserDetail =
        CompanyUserDetail.fromJson(api.get("company-structure/users/$userId"))

    /** `POST chats/direct` → id of the direct conversation with [userId]. */
    suspend fun openDirectChat(userId: Long): Long =
        api.post("chats/direct", jsonObjectOf("user_id" to userId)).dataObject().long("conversation_id")
            ?: throw ApiException(status = 404, message = "Not Found")
}
