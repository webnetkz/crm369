package com.appswebnetkz.crm369.feature.equipment

import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.session.Bootstrap
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.ui.components.Option
import kotlinx.coroutines.CancellationException
import kotlinx.serialization.json.JsonObject

/**
 * Employees for the equipment pickers. The mobile API has no general users endpoint, so the list
 * comes from the first readable source: the company structure (`GET company-structure`, flattened),
 * the projects workspace (`availableUsers`) or the chat contacts (`GET chats`). Users already
 * referenced by the item are always included so the current values stay visible.
 */
class EquipmentUsers(private val api: ApiClient) {

    suspend fun load(bootstrap: Bootstrap?, referenced: List<EquipmentUser>): List<Option> {
        val users = LinkedHashMap<Long, Option>()
        for (source in sources(bootstrap)) {
            val loaded: Map<Long, Option> = try {
                when (source) {
                    Source.COMPANY_STRUCTURE -> companyStructure()
                    Source.PROJECTS -> projectUsers()
                    Source.CHATS -> chatContacts()
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                emptyMap()
            }
            if (loaded.isNotEmpty()) {
                users.putAll(loaded)
                break
            }
        }
        referenced.forEach { user ->
            if (!users.containsKey(user.id)) users[user.id] = user.toOption()
        }
        return users.values.sortedBy { it.label.lowercase() }
    }

    private fun sources(bootstrap: Bootstrap?): List<Source> = buildList {
        if (bootstrap == null || bootstrap.can("canAccessCompanyStructure")) add(Source.COMPANY_STRUCTURE)
        if (bootstrap == null || bootstrap.can("canAccessProjects")) add(Source.PROJECTS)
        if (bootstrap == null || bootstrap.can("canAccessChats")) add(Source.CHATS)
    }

    /** `GET company-structure` is not wrapped: `{stats, roots: Node[]}`; nodes nest `children` and `subordinates`. */
    private suspend fun companyStructure(): Map<Long, Option> {
        val result = LinkedHashMap<Long, Option>()
        api.get("company-structure").objects("roots").forEach { node -> addStructureNode(node, result, depth = 0) }
        return result
    }

    private fun addStructureNode(node: JsonObject, into: MutableMap<Long, Option>, depth: Int) {
        addPerson(node, into)
        node.objects("subordinates").forEach { person -> addPerson(person, into) }
        if (depth < MAX_DEPTH) {
            node.objects("children").forEach { child -> addStructureNode(child, into, depth + 1) }
        }
    }

    private fun addPerson(json: JsonObject, into: MutableMap<Long, Option>) {
        val id = json.long("id") ?: return
        if (into.containsKey(id) || !json.boolOr("is_active", true)) return
        val email = json.text("email")
        val name = (json.text("full_name") ?: Formatters.fullName(json.str("name"), json.str("last_name"), json.str("middle_name")))
            .ifBlank { email ?: id.toString() }
        into[id] = Option(value = id.toString(), label = name, subtitle = json.text("position") ?: email)
    }

    /** `GET projects/workspace` → `data.availableUsers` (UserMini). */
    private suspend fun projectUsers(): Map<Long, Option> =
        toOptions(api.get("projects/workspace").dataObject().objects("availableUsers"))

    /** `GET chats` (not wrapped) → `contacts` (ChatUser). */
    private suspend fun chatContacts(): Map<Long, Option> = toOptions(api.get("chats").objects("contacts"))

    private fun toOptions(users: List<JsonObject>): Map<Long, Option> {
        val result = LinkedHashMap<Long, Option>()
        users.forEach { json ->
            val id = json.long("id") ?: return@forEach
            val email = json.text("email")
            val name = Formatters.fullName(json.str("name"), json.str("last_name")).ifBlank { email ?: id.toString() }
            result[id] = Option(value = id.toString(), label = name, subtitle = email)
        }
        return result
    }

    private enum class Source { COMPANY_STRUCTURE, PROJECTS, CHATS }

    private companion object {
        const val MAX_DEPTH = 32
    }
}
