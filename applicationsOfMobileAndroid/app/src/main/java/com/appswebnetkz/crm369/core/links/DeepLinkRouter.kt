package com.appswebnetkz.crm369.core.links

import java.net.URI

/** Where a CRM link (notification action, push `action_path`, chat link) should open. */
sealed class DeepLinkTarget {
    /** A native screen route of the application. */
    data class App(val route: String) : DeepLinkTarget()

    /** A portal page without a native screen; opened in the in-app browser with the web session bridge. */
    data class Web(val path: String) : DeepLinkTarget()

    /** A link to another site; opened in the system browser. */
    data class External(val url: String) : DeepLinkTarget()
}

/**
 * Maps web paths of the CRM369 portal (e.g. `/chats?conversation=5`, `/projects/tasks/12`)
 * to native application routes. Pure Kotlin so it can be unit tested on the JVM.
 *
 * The produced routes are the canonical routes registered by the feature navigation graphs.
 */
object DeepLinkRouter {

    fun resolve(pathOrUrl: String, portalBaseUrl: String? = null): DeepLinkTarget {
        val value = pathOrUrl.trim()
        if (value.isEmpty()) return DeepLinkTarget.App("main")

        val lower = value.lowercase()
        if (lower.startsWith("mailto:") || lower.startsWith("tel:")) {
            return DeepLinkTarget.External(value)
        }

        val path: String
        val query: String?
        if (lower.startsWith("http://") || lower.startsWith("https://")) {
            val uri = try {
                URI(value)
            } catch (_: Exception) {
                return DeepLinkTarget.External(value)
            }
            val portalHost = portalBaseUrl?.let { base ->
                try {
                    URI(base).host?.lowercase()
                } catch (_: Exception) {
                    null
                }
            }
            val linkHost = uri.host?.lowercase()
            if (portalHost == null || linkHost == null || linkHost != portalHost) {
                return DeepLinkTarget.External(value)
            }
            path = uri.rawPath.orEmpty()
            query = uri.rawQuery
        } else {
            val withoutFragment = value.substringBefore('#')
            path = withoutFragment.substringBefore('?')
            query = if (withoutFragment.contains('?')) withoutFragment.substringAfter('?') else null
        }

        return resolvePath(if (path.startsWith("/")) path else "/$path", query)
    }

    private fun resolvePath(path: String, query: String?): DeepLinkTarget {
        val segments = path.trim('/').split('/').filter { it.isNotEmpty() }.map { RouteArgs.decode(it) }
        val params = parseQuery(query)
        val webPath = if (query.isNullOrEmpty()) path else "$path?$query"

        fun s(index: Int): String? = segments.getOrNull(index)
        fun id(index: Int): Long? = s(index)?.toLongOrNull()

        if (segments.isEmpty()) return DeepLinkTarget.App("main")

        return when (s(0)) {
            "dashboard" -> DeepLinkTarget.App("main")
            "notifications" -> DeepLinkTarget.App("notifications")
            "chats" -> {
                val conversation = params["conversation"]?.toLongOrNull()
                when {
                    conversation != null -> DeepLinkTarget.App("chats/$conversation")
                    s(1) == null -> DeepLinkTarget.App("chats")
                    else -> DeepLinkTarget.Web(webPath)
                }
            }
            "tasks" -> when {
                id(1) != null -> DeepLinkTarget.App("tasks/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("tasks")
                else -> DeepLinkTarget.Web(webPath)
            }
            "projects" -> {
                val queryTask = params["task"]?.toLongOrNull()
                when {
                    s(1) == "tasks" && id(2) != null -> DeepLinkTarget.App("tasks/${id(2)}")
                    id(1) != null && s(2) == "tasks" && id(3) != null -> DeepLinkTarget.App("tasks/${id(3)}")
                    queryTask != null -> DeepLinkTarget.App("tasks/$queryTask")
                    id(1) != null && s(2) == null -> DeepLinkTarget.App("projects/${id(1)}")
                    s(1) == null -> DeepLinkTarget.App("projects")
                    else -> DeepLinkTarget.Web(webPath)
                }
            }
            "funnels" -> when {
                id(1) != null -> DeepLinkTarget.App("funnels/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("funnels")
                else -> DeepLinkTarget.Web(webPath)
            }
            "contacts" -> when {
                id(1) != null -> DeepLinkTarget.App("contacts/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("contacts")
                else -> DeepLinkTarget.Web(webPath)
            }
            "calendar" -> DeepLinkTarget.App("calendar")
            "company-structure" -> DeepLinkTarget.App("company")
            "news" -> when {
                s(1) != null && s(2) == null -> DeepLinkTarget.App("news/${RouteArgs.encode(s(1)!!)}")
                s(1) == null -> DeepLinkTarget.App("news")
                else -> DeepLinkTarget.Web(webPath)
            }
            "knowledge-bases" -> when {
                id(1) != null && s(2) == "articles" && id(3) != null ->
                    DeepLinkTarget.App("knowledge/${id(1)}/articles/${id(3)}")
                id(1) != null && s(2) == null -> DeepLinkTarget.App("knowledge/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("knowledge")
                else -> DeepLinkTarget.Web(webPath)
            }
            "files" -> {
                val directory = params["directory"]?.toLongOrNull() ?: params["directory_id"]?.toLongOrNull()
                if (s(1) == null) {
                    DeepLinkTarget.App(if (directory != null) "files?directoryId=$directory" else "files")
                } else {
                    DeepLinkTarget.Web(webPath)
                }
            }
            "edo" -> when {
                s(1) == "public" -> DeepLinkTarget.Web(webPath)
                id(1) != null && s(2) == null -> DeepLinkTarget.App("edo/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("edo")
                else -> DeepLinkTarget.Web(webPath)
            }
            "procurement" -> if (s(1) == null) DeepLinkTarget.App("procurement") else DeepLinkTarget.Web(webPath)
            "warehouses" -> when {
                id(1) != null -> DeepLinkTarget.App("warehouses/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("warehouses")
                else -> DeepLinkTarget.Web(webPath)
            }
            "equipment" -> when {
                id(1) != null && s(2) == null -> DeepLinkTarget.App("equipment/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("equipment")
                else -> DeepLinkTarget.Web(webPath)
            }
            "tsd" -> DeepLinkTarget.App("tsd")
            "qr" -> DeepLinkTarget.App("qr")
            "directories" -> when {
                id(1) != null && s(2) == null -> DeepLinkTarget.App("directories/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("directories")
                else -> DeepLinkTarget.Web(webPath)
            }
            "forms" -> when {
                s(1) == "public" -> DeepLinkTarget.Web(webPath)
                id(1) != null && s(2) == null -> DeepLinkTarget.App("forms/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("forms")
                else -> DeepLinkTarget.Web(webPath)
            }
            "production" -> when {
                s(1) != null && s(2) == null -> DeepLinkTarget.App("production/${RouteArgs.encode(s(1)!!)}")
                s(1) == null -> DeepLinkTarget.App("production")
                else -> DeepLinkTarget.Web(webPath)
            }
            "conferences" -> when {
                s(1) == "public" -> DeepLinkTarget.Web(webPath)
                id(1) != null && s(2) == null -> DeepLinkTarget.App("conferences/${id(1)}")
                s(1) == null -> DeepLinkTarget.App("conferences")
                else -> DeepLinkTarget.Web(webPath)
            }
            else -> DeepLinkTarget.Web(webPath)
        }
    }

    private fun parseQuery(query: String?): Map<String, String> {
        if (query.isNullOrBlank()) return emptyMap()
        return query.split('&')
            .filter { it.isNotEmpty() }
            .associate { pair ->
                val key = RouteArgs.decode(pair.substringBefore('='))
                val value = if (pair.contains('=')) RouteArgs.decode(pair.substringAfter('=')) else ""
                key to value
            }
    }
}
