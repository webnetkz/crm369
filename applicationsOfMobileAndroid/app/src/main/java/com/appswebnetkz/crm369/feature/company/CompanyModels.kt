package com.appswebnetkz.crm369.feature.company

import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import kotlinx.serialization.json.JsonObject

/*
 * Models of the company structure module. Pure Kotlin (no Android imports) so the parsing,
 * tree flattening and search can be unit tested.
 */

/** `Person` {id, name, last_name?, middle_name?, full_name, email, avatar?, avatar_scale, position?, is_active}. */
data class CompanyPerson(
    val id: Long,
    val fullName: String,
    val email: String?,
    val phone: String?,
    val position: String?,
    val avatarUrl: String?,
    val isActive: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject): CompanyPerson {
            val email = json.text("email")
            val joined = listOfNotNull(json.str("name"), json.str("middle_name"), json.str("last_name"))
                .map { it.trim() }
                .filter { it.isNotEmpty() }
                .joinToString(" ")
            return CompanyPerson(
                id = json.long("id") ?: 0L,
                fullName = json.text("full_name") ?: joined.ifEmpty { email.orEmpty() },
                email = email,
                phone = json.text("phone"),
                position = json.text("position"),
                avatarUrl = json.text("avatar"),
                isActive = json.boolOr("is_active", true),
            )
        }
    }
}

/** `Node` = Person + {manager_id?, manager?, subordinates_count, subordinates, children: Node[]}. */
data class CompanyNode(
    val person: CompanyPerson,
    val managerId: Long?,
    val manager: CompanyPerson?,
    val subordinatesCount: Int,
    val subordinates: List<CompanyPerson>,
    val children: List<CompanyNode>,
) {
    val id: Long get() = person.id

    companion object {
        /** Guards against malformed (cyclic or extremely deep) payloads. */
        const val MAX_DEPTH = 64

        fun fromJson(json: JsonObject, depth: Int = 0): CompanyNode {
            val subordinates = json.objects("subordinates").map { CompanyPerson.fromJson(it) }
            return CompanyNode(
                person = CompanyPerson.fromJson(json),
                managerId = json.long("manager_id"),
                manager = json.obj("manager")?.let { CompanyPerson.fromJson(it) },
                subordinatesCount = json.int("subordinates_count") ?: subordinates.size,
                subordinates = subordinates,
                children = if (depth >= MAX_DEPTH) emptyList() else json.objects("children").map { fromJson(it, depth + 1) },
            )
        }
    }
}

data class CompanyStats(val totalUsers: Int, val rootUsers: Int, val managers: Int)

/** A visible row of the org tree. */
data class CompanyTreeRow(val node: CompanyNode, val level: Int, val isExpanded: Boolean)

/** `GET company-structure` (not wrapped in `data`): `{stats, roots}`. */
data class CompanyStructure(val stats: CompanyStats, val roots: List<CompanyNode>) {

    /** Every node once, depth-first in tree order. */
    fun allNodes(): List<CompanyNode> {
        val result = ArrayList<CompanyNode>()
        val seen = HashSet<Long>()
        fun visit(node: CompanyNode) {
            if (!seen.add(node.id)) return
            result += node
            node.children.forEach { visit(it) }
        }
        roots.forEach { visit(it) }
        return result
    }

    /** Flat search over full name, position and email (case-insensitive). */
    fun search(query: String): List<CompanyNode> {
        val needle = query.trim()
        if (needle.isEmpty()) return emptyList()
        return allNodes().filter { node ->
            val person = node.person
            person.fullName.contains(needle, ignoreCase = true) ||
                person.position?.contains(needle, ignoreCase = true) == true ||
                person.email?.contains(needle, ignoreCase = true) == true
        }
    }

    /** Rows of the expandable tree: children are listed only under expanded nodes. */
    fun visibleRows(expanded: Set<Long>): List<CompanyTreeRow> {
        val result = ArrayList<CompanyTreeRow>()
        val seen = HashSet<Long>()
        fun visit(node: CompanyNode, level: Int) {
            if (!seen.add(node.id)) return
            val isExpanded = node.children.isNotEmpty() && node.id in expanded
            result += CompanyTreeRow(node = node, level = level, isExpanded = isExpanded)
            if (isExpanded) node.children.forEach { visit(it, level + 1) }
        }
        roots.forEach { visit(it, 0) }
        return result
    }

    companion object {
        fun fromJson(json: JsonObject): CompanyStructure {
            // The endpoint is not wrapped; accept a `data` wrapper too.
            val root = if (json.objects("roots").isEmpty()) json.obj("data") ?: json else json
            val stats = root.obj("stats")
            val roots = root.objects("roots").map { CompanyNode.fromJson(it) }
            return CompanyStructure(
                stats = CompanyStats(
                    totalUsers = stats.int("total_users") ?: 0,
                    rootUsers = stats.int("root_users") ?: roots.size,
                    managers = stats.int("managers") ?: 0,
                ),
                roots = roots,
            )
        }
    }
}

/** `GET company-structure/users/{id}` → `{data: Node, ancestors: Person[]}` (ancestors from the top). */
data class CompanyUserDetail(val node: CompanyNode, val ancestors: List<CompanyPerson>) {
    companion object {
        fun fromJson(json: JsonObject): CompanyUserDetail = CompanyUserDetail(
            node = CompanyNode.fromJson(json.obj("data") ?: json),
            ancestors = json.objects("ancestors").map { CompanyPerson.fromJson(it) },
        )
    }
}
