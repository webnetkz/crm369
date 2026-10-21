package com.appswebnetkz.crm369.feature.knowledge

import android.content.Context
import android.net.Uri
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.int
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.strings
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.json.toMultipartFields
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.UploadPart
import kotlinx.serialization.json.JsonObject

/** User group a knowledge base is visible to. */
data class KnowledgeGroup(
    val id: Long,
    val name: String,
) {
    companion object {
        fun fromJson(json: JsonObject): KnowledgeGroup = KnowledgeGroup(
            id = json.long("id") ?: 0L,
            name = json.text("display_name") ?: json.str("name").orEmpty(),
        )
    }
}

/** Item of `bases`. */
data class KnowledgeBaseSummary(
    val id: Long,
    val title: String,
    val slug: String,
    val description: String?,
    val isPublished: Boolean,
    val articleCount: Int,
    val updatedAt: String?,
    val groups: List<KnowledgeGroup>,
) {
    companion object {
        fun fromJson(json: JsonObject): KnowledgeBaseSummary = KnowledgeBaseSummary(
            id = json.long("id") ?: 0L,
            title = json.str("title").orEmpty(),
            slug = json.str("slug").orEmpty(),
            description = json.text("description"),
            isPublished = json.boolOr("is_published", true),
            articleCount = json.int("article_count") ?: 0,
            updatedAt = json.text("updated_at"),
            groups = json.objects("groups").map { KnowledgeGroup.fromJson(it) },
        )
    }
}

/** Node of the article tree (`activeBase.articles`). */
data class KnowledgeArticleNode(
    val id: Long,
    val title: String,
    val slug: String,
    val excerpt: String?,
    val isPublished: Boolean,
    val sortOrder: Int,
    val children: List<KnowledgeArticleNode>,
) {
    companion object {
        fun fromJson(json: JsonObject): KnowledgeArticleNode = KnowledgeArticleNode(
            id = json.long("id") ?: 0L,
            title = json.str("title").orEmpty(),
            slug = json.str("slug").orEmpty(),
            excerpt = json.text("excerpt"),
            isPublished = json.boolOr("is_published", true),
            sortOrder = json.int("sort_order") ?: 0,
            children = json.objects("children").map { fromJson(it) },
        )
    }
}

/** `activeBase`: a base with its article tree. */
data class KnowledgeBaseDetail(
    val id: Long,
    val title: String,
    val slug: String,
    val description: String?,
    val isPublished: Boolean,
    val groups: List<KnowledgeGroup>,
    val articles: List<KnowledgeArticleNode>,
) {
    companion object {
        fun fromJson(json: JsonObject): KnowledgeBaseDetail = KnowledgeBaseDetail(
            id = json.long("id") ?: 0L,
            title = json.str("title").orEmpty(),
            slug = json.str("slug").orEmpty(),
            description = json.text("description"),
            isPublished = json.boolOr("is_published", true),
            groups = json.objects("groups").map { KnowledgeGroup.fromJson(it) },
            articles = json.objects("articles").map { KnowledgeArticleNode.fromJson(it) },
        )
    }
}

/** Content block of an article; `content` and list items are sanitized inline HTML. */
data class KnowledgeBlock(
    val type: String,
    val content: String?,
    val headingLevel: Int?,
    val items: List<String>,
    val ordered: Boolean,
    val imagePath: String?,
    val imageUrl: String?,
    val caption: String?,
) {
    companion object {
        const val TYPE_PARAGRAPH = "paragraph"
        const val TYPE_HEADING = "heading"
        const val TYPE_LIST = "list"
        const val TYPE_IMAGE = "image"

        fun fromJson(json: JsonObject): KnowledgeBlock = KnowledgeBlock(
            type = json.text("type") ?: TYPE_PARAGRAPH,
            content = json.text("content"),
            headingLevel = json.int("heading_level"),
            items = json.strings("items"),
            ordered = json.boolOr("ordered", false),
            imagePath = json.text("image_path"),
            imageUrl = json.text("image_url"),
            caption = json.text("caption"),
        )
    }
}

/** `activeArticle`. */
data class KnowledgeArticle(
    val id: Long,
    val baseId: Long,
    val parentId: Long?,
    val title: String,
    val slug: String,
    val excerpt: String?,
    val sortOrder: Int,
    val isPublished: Boolean,
    val blocks: List<KnowledgeBlock>,
    val updatedAt: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): KnowledgeArticle = KnowledgeArticle(
            id = json.long("id") ?: 0L,
            baseId = json.long("knowledge_base_id") ?: 0L,
            parentId = json.long("parent_id"),
            title = json.str("title").orEmpty(),
            slug = json.str("slug").orEmpty(),
            excerpt = json.text("excerpt"),
            sortOrder = json.int("sort_order") ?: 0,
            isPublished = json.boolOr("is_published", true),
            blocks = json.objects("blocks").map { KnowledgeBlock.fromJson(it) },
            updatedAt = json.text("updated_at"),
        )
    }
}

/** Response of `GET knowledge-bases[/{id}[/articles/{aid}]]`; `groups` lives next to `data`. */
data class KnowledgePage(
    val bases: List<KnowledgeBaseSummary>,
    val activeBase: KnowledgeBaseDetail?,
    val activeArticle: KnowledgeArticle?,
    val canManage: Boolean,
    val groups: List<KnowledgeGroup>,
) {
    companion object {
        fun fromResponse(response: JsonObject): KnowledgePage {
            val data = response.dataObject()
            val groups = response.objects("groups").ifEmpty { data.objects("groups") }
            return KnowledgePage(
                bases = data.objects("bases").map { KnowledgeBaseSummary.fromJson(it) },
                activeBase = data.obj("activeBase")?.let { KnowledgeBaseDetail.fromJson(it) },
                activeArticle = data.obj("activeArticle")?.let { KnowledgeArticle.fromJson(it) },
                canManage = data.obj("can").boolOr("manage", false),
                groups = groups.map { KnowledgeGroup.fromJson(it) },
            )
        }
    }
}

/** All knowledge base API calls (`/api/mobile/v1/knowledge-bases`). */
class KnowledgeRepository(private val api: ApiClient) {

    suspend fun index(): KnowledgePage = KnowledgePage.fromResponse(api.get("knowledge-bases"))

    suspend fun base(baseId: Long): KnowledgePage = KnowledgePage.fromResponse(api.get("knowledge-bases/$baseId"))

    suspend fun article(baseId: Long, articleId: Long): KnowledgePage =
        KnowledgePage.fromResponse(api.get("knowledge-bases/$baseId/articles/$articleId"))

    /** Returns the id of the created base. */
    suspend fun createBase(body: JsonObject): Long =
        api.post("knowledge-bases", body).dataObject().long("id") ?: 0L

    /** Full update: title, slug, description, is_published and user_group_ids. */
    suspend fun updateBase(baseId: Long, body: JsonObject) {
        api.patch("knowledge-bases/$baseId", body)
    }

    suspend fun deleteBase(baseId: Long) {
        api.delete("knowledge-bases/$baseId")
    }

    /**
     * Creates ([articleId] = null) or replaces an article. [images] maps block indexes to new image
     * files (`blocks[i][image_file]`); with images the request is multipart (`_method=PATCH` for updates).
     * Returns the id of the saved article.
     */
    suspend fun saveArticle(
        context: Context,
        baseId: Long,
        articleId: Long?,
        body: JsonObject,
        images: List<Pair<Int, Uri>>,
    ): Long {
        val path = if (articleId == null) "knowledge-bases/$baseId/articles" else "knowledge-bases/$baseId/articles/$articleId"
        val response = if (images.isNotEmpty()) {
            api.multipart(
                path,
                body.toMultipartFields(),
                images.map { (index, uri) -> UploadPart.fromUri(context, "blocks[$index][image_file]", uri) },
                methodOverride = if (articleId == null) null else "PATCH",
            )
        } else if (articleId == null) {
            api.post(path, body)
        } else {
            api.patch(path, body)
        }
        return response.dataObject().long("id") ?: articleId ?: 0L
    }

    suspend fun deleteArticle(baseId: Long, articleId: Long) {
        api.delete("knowledge-bases/$baseId/articles/$articleId")
    }
}

/** Tree helpers. */
internal object KnowledgeTree {

    fun findNode(nodes: List<KnowledgeArticleNode>, id: Long): KnowledgeArticleNode? {
        nodes.forEach { node ->
            if (node.id == id) return node
            val nested = findNode(node.children, id)
            if (nested != null) return nested
        }
        return null
    }

    /** Depth-first list of (node, depth), skipping the subtree of [excludeId]. */
    fun flattenWithDepth(nodes: List<KnowledgeArticleNode>, excludeId: Long? = null, depth: Int = 0): List<Pair<KnowledgeArticleNode, Int>> {
        val result = mutableListOf<Pair<KnowledgeArticleNode, Int>>()
        nodes.forEach { node ->
            if (node.id != excludeId) {
                result.add(node to depth)
                result.addAll(flattenWithDepth(node.children, excludeId, depth + 1))
            }
        }
        return result
    }
}
