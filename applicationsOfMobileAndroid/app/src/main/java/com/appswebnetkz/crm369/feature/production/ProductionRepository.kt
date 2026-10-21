package com.appswebnetkz.crm369.feature.production

import com.appswebnetkz.crm369.core.json.asStr
import com.appswebnetkz.crm369.core.json.arr
import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.obj
import com.appswebnetkz.crm369.core.json.objects
import com.appswebnetkz.crm369.core.json.str
import com.appswebnetkz.crm369.core.json.text
import com.appswebnetkz.crm369.core.links.RouteArgs
import com.appswebnetkz.crm369.core.network.ApiClient
import kotlinx.serialization.json.JsonObject

data class ProductionSectionItem(val key: String, val title: String, val summary: String?)

data class ProductionMetric(val label: String, val value: String, val caption: String?)

data class ProductionCard(val title: String, val description: String?)

data class ProductionRecord(val title: String, val badge: String?, val description: String?)

/** `activeSectionContent` of the production page. */
data class ProductionSectionContent(
    val key: String,
    val title: String,
    val summary: String?,
    val heroTitle: String?,
    val heroDescription: String?,
    val metrics: List<ProductionMetric>,
    val cards: List<ProductionCard>,
    val recordsTitle: String?,
    val recordsDescription: String?,
    val records: List<ProductionRecord>,
    val focusTitle: String?,
    val focusPoints: List<String>,
    val nextStepsTitle: String?,
    val nextSteps: List<String>,
) {
    companion object {
        fun fromJson(json: JsonObject?): ProductionSectionContent = ProductionSectionContent(
            key = json.str("key").orEmpty(),
            title = json.str("title").orEmpty(),
            summary = json.text("summary"),
            heroTitle = json.text("hero_title"),
            heroDescription = json.text("hero_description"),
            metrics = json.objects("metrics").map { metric ->
                ProductionMetric(
                    label = metric.str("label").orEmpty(),
                    value = metric.str("value").orEmpty(),
                    caption = metric.text("caption"),
                )
            },
            cards = json.objects("cards").map { card ->
                ProductionCard(title = card.str("title").orEmpty(), description = card.text("description"))
            },
            recordsTitle = json.text("records_title"),
            recordsDescription = json.text("records_description"),
            records = json.objects("records").map { record ->
                ProductionRecord(
                    title = record.str("title").orEmpty(),
                    badge = record.text("badge"),
                    description = record.text("description"),
                )
            },
            focusTitle = json.text("focus_title"),
            focusPoints = textList(json, "focus_points"),
            nextStepsTitle = json.text("next_steps_title"),
            nextSteps = textList(json, "next_steps"),
        )

        private fun textList(json: JsonObject?, key: String): List<String> =
            json.arr(key).orEmpty().mapNotNull { element -> element.asStr()?.takeIf { it.isNotBlank() } }
    }
}

/** Page labels (`labels`). */
data class ProductionLabels(
    val title: String?,
    val description: String?,
    val eyebrow: String?,
    val navigationTitle: String?,
    val cardsTitle: String?,
    val recordsLabel: String?,
    val focusLabel: String?,
    val nextStepsLabel: String?,
) {
    companion object {
        fun fromJson(json: JsonObject?): ProductionLabels = ProductionLabels(
            title = json.text("title"),
            description = json.text("description"),
            eyebrow = json.text("eyebrow"),
            navigationTitle = json.text("navigation_title"),
            cardsTitle = json.text("cards_title"),
            recordsLabel = json.text("records_label"),
            focusLabel = json.text("focus_label"),
            nextStepsLabel = json.text("next_steps_label"),
        )
    }
}

/** `GET production[/{section}]`. */
data class ProductionPage(
    val activeSection: String,
    val sections: List<ProductionSectionItem>,
    val labels: ProductionLabels,
    val content: ProductionSectionContent,
) {
    companion object {
        fun fromJson(json: JsonObject): ProductionPage {
            val content = ProductionSectionContent.fromJson(json.obj("activeSectionContent"))
            val items = json.objects("sectionItems").mapNotNull { item ->
                val key = item.text("key") ?: return@mapNotNull null
                ProductionSectionItem(key = key, title = item.text("title") ?: key, summary = item.text("summary"))
            }
            return ProductionPage(
                activeSection = json.text("activeSection") ?: content.key,
                sections = items,
                labels = ProductionLabels.fromJson(json.obj("labels")),
                content = content,
            )
        }
    }
}

/** Production API (`/api/mobile/v1/production`). */
class ProductionRepository(private val api: ApiClient) {

    /** The default section when [section] is null; an unknown section answers 404. */
    suspend fun page(section: String?): ProductionPage {
        val response = if (section.isNullOrBlank()) {
            api.get("production")
        } else {
            api.get("production/" + RouteArgs.encode(section))
        }
        return ProductionPage.fromJson(response.dataObject())
    }
}
