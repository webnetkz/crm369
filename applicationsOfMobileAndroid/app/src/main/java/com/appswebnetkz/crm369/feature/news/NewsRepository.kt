package com.appswebnetkz.crm369.feature.news

import android.content.Context
import android.net.Uri
import com.appswebnetkz.crm369.core.json.boolOr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.long
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.json.toMultipartFields
import com.appswebnetkz.crm369.core.links.RouteArgs
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.UploadPart
import com.appswebnetkz.crm369.core.util.Formatters
import kotlinx.serialization.json.JsonObject

/** `NewsItem` (feed card). `image_url` is absolute. */
data class NewsItem(
    val id: Long,
    val title: String,
    val slug: String,
    val excerpt: String?,
    val imageUrl: String?,
    val isPublished: Boolean,
    val publishedAt: String?,
    val updatedAt: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): NewsItem = NewsItem(
            id = json.long("id") ?: 0L,
            title = json.str("title").orEmpty(),
            slug = json.str("slug").orEmpty(),
            excerpt = json.text("excerpt"),
            imageUrl = json.text("image_url"),
            isPublished = json.boolOr("is_published", true),
            publishedAt = json.text("published_at"),
            updatedAt = json.text("updated_at"),
        )
    }
}

/** `NewsDetail` = [NewsItem] + content + author. */
data class NewsDetail(
    val item: NewsItem,
    val content: String,
    val authorName: String?,
) {
    companion object {
        fun fromJson(json: JsonObject): NewsDetail {
            val author = json.obj("author")
            return NewsDetail(
                item = NewsItem.fromJson(json),
                content = json.str("content").orEmpty(),
                authorName = author?.let { Formatters.fullName(it.str("name"), it.str("last_name")) }?.takeIf { it.isNotBlank() },
            )
        }
    }
}

/** `NewsPage` returned by `GET news` and `GET news/{slug}`. */
data class NewsPage(
    val items: List<NewsItem>,
    val active: NewsDetail?,
    val canManage: Boolean,
) {
    companion object {
        fun fromJson(json: JsonObject): NewsPage = NewsPage(
            items = json.objects("newsItems").map { NewsItem.fromJson(it) },
            active = json.obj("activeNews")?.let { NewsDetail.fromJson(it) },
            canManage = json.obj("can").boolOr("manage", false),
        )
    }
}

/** All news API calls (`/api/mobile/v1/news`). The route key is the slug. */
class NewsRepository(private val api: ApiClient) {

    suspend fun page(): NewsPage = NewsPage.fromJson(api.get("news").dataObject())

    suspend fun detail(slug: String): NewsPage = NewsPage.fromJson(api.get(newsPath(slug)).dataObject())

    /** Creates a news item; [image] (content Uri) switches the request to multipart. */
    suspend fun create(context: Context, body: JsonObject, image: Uri?): NewsDetail {
        val response = if (image != null) {
            api.multipart("news", body.toMultipartFields(), listOf(UploadPart.fromUri(context, "image_file", image)))
        } else {
            api.post("news", body)
        }
        return NewsDetail.fromJson(response.dataObject())
    }

    /** Full update (the body must contain the current slug); multipart updates use `_method=PATCH`. */
    suspend fun update(context: Context, slug: String, body: JsonObject, image: Uri?): NewsDetail {
        val response = if (image != null) {
            api.multipart(
                newsPath(slug),
                body.toMultipartFields(),
                listOf(UploadPart.fromUri(context, "image_file", image)),
                methodOverride = "PATCH",
            )
        } else {
            api.patch(newsPath(slug), body)
        }
        return NewsDetail.fromJson(response.dataObject())
    }

    suspend fun delete(slug: String) {
        api.delete(newsPath(slug))
    }

    private fun newsPath(slug: String): String = "news/" + RouteArgs.encode(slug)
}

/**
 * News content is stored as plain text by the portal (the web page renders paragraphs split by
 * empty lines). For the article view it is converted to HTML; content that already contains HTML
 * tags is shown as is.
 */
internal object NewsContent {
    private val tagPattern = Regex("</?[a-zA-Z][a-zA-Z0-9]*(\\s[^<>]*)?/?>")
    private val paragraphBreak = Regex("\\n[ \\t]*\\n+")

    fun isHtml(text: String): Boolean = tagPattern.containsMatchIn(text)

    /** Paragraphs (separated by empty lines) → `<p>`, single line breaks → `<br>`. */
    fun plainTextToHtml(text: String): String = text
        .replace("\r\n", "\n")
        .replace('\r', '\n')
        .trim()
        .split(paragraphBreak)
        .map { it.trim() }
        .filter { it.isNotEmpty() }
        .joinToString(separator = "") { paragraph -> "<p>" + escape(paragraph).replace("\n", "<br>") + "</p>" }

    fun toHtml(content: String): String = if (isHtml(content)) content else plainTextToHtml(content)

    fun escape(value: String): String = value
        .replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace("\"", "&quot;")
}
