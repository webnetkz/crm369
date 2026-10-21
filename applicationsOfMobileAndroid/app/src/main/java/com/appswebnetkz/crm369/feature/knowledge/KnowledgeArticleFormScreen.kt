@file:OptIn(ExperimentalMaterial3Api::class)

package com.appswebnetkz.crm369.feature.knowledge

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.StringRes
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.AssistChip
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedCard
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshots.SnapshotStateList
import androidx.compose.runtime.snapshots.SnapshotStateMap
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.ui.components.Option
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import java.util.concurrent.atomic.AtomicLong
import kotlinx.coroutines.CancellationException

/** Editable copy of one content block (`uid` is a local key for the editor only). */
internal data class BlockDraft(
    val uid: Long,
    val type: String,
    val content: String = "",
    val headingLevel: Int = 2,
    val itemsText: String = "",
    val ordered: Boolean = false,
    val imagePath: String? = null,
    val imageUrl: String? = null,
    val newImage: Uri? = null,
    val caption: String = "",
) {
    /** List items: one per line, blank lines ignored. */
    val listItems: List<String>
        get() = itemsText.lines().map { it.trim() }.filter { it.isNotEmpty() }

    /** Block of the request body. New images are sent separately as `blocks[i][image_file]`. */
    fun toPayload(): Map<String, Any?> = when (type) {
        KnowledgeBlock.TYPE_HEADING -> mapOf("type" to type, "content" to content.trim(), "heading_level" to headingLevel)
        KnowledgeBlock.TYPE_LIST -> mapOf("type" to type, "items" to listItems, "ordered" to ordered)
        KnowledgeBlock.TYPE_IMAGE -> buildMap<String, Any?> {
            put("type", type)
            if (newImage == null && imagePath != null) put("image_path", imagePath)
            put("caption", caption.trim().ifBlank { null })
        }
        else -> mapOf("type" to KnowledgeBlock.TYPE_PARAGRAPH, "content" to KnowledgeText.paragraphToHtml(content))
    }

    companion object {
        fun from(block: KnowledgeBlock, uid: Long): BlockDraft = BlockDraft(
            uid = uid,
            type = block.type,
            content = if (block.type == KnowledgeBlock.TYPE_HEADING) {
                block.content.orEmpty()
            } else {
                KnowledgeText.paragraphToEditable(block.content.orEmpty())
            },
            headingLevel = (block.headingLevel ?: 2).coerceIn(1, 3),
            itemsText = block.items.joinToString("\n"),
            ordered = block.ordered,
            imagePath = block.imagePath,
            imageUrl = block.imageUrl,
            caption = block.caption.orEmpty(),
        )
    }
}

/** Paragraph content is inline HTML; line breaks are edited as new lines. */
internal object KnowledgeText {
    private val lineBreak = Regex("(?i)<br\\s*/?>")

    fun paragraphToEditable(html: String): String = html.replace(lineBreak, "\n")

    fun paragraphToHtml(text: String): String = text.replace("\r\n", "\n").trim().replace("\n", "<br>")
}

private data class BlockMessages(
    val paragraph: String,
    val heading: String,
    val list: String,
    val image: String,
)

/**
 * Create ([articleId] = null, optionally under [parentId]) or edit an article with the block
 * editor. The update replaces the article: every block is sent again, existing images keep their
 * `image_path`, new ones are uploaded as `blocks[i][image_file]`.
 */
@Composable
fun KnowledgeArticleFormScreen(baseId: Long, articleId: Long?, parentId: Long?) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { KnowledgeRepository(api) }
    val state = remember(baseId, articleId) { FormState(mapOf("is_published" to true, "sort_order" to "0")) }
    val blocks = remember(baseId, articleId) { mutableStateListOf<BlockDraft>() }
    val uids = remember(baseId, articleId) { AtomicLong(1L) }
    var tree by remember(baseId, articleId) { mutableStateOf<List<KnowledgeArticleNode>>(emptyList()) }
    var originalSlug by remember(baseId, articleId) { mutableStateOf<String?>(null) }
    var loaded by remember(baseId, articleId) { mutableStateOf(false) }
    var pickingUid by remember { mutableStateOf<Long?>(null) }
    val imagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        val target = pickingUid
        pickingUid = null
        if (uri != null && target != null) {
            val index = blocks.indexOfFirst { it.uid == target }
            if (index >= 0) {
                blocks[index] = blocks[index].copy(newImage = uri)
            }
        }
    }

    LaunchedEffect(baseId, articleId) {
        try {
            val page = if (articleId == null) repository.base(baseId) else repository.article(baseId, articleId)
            val nodes = page.activeBase?.articles.orEmpty()
            tree = nodes
            val article = page.activeArticle
            if (articleId != null) {
                if (article != null && article.id == articleId) {
                    state.setAll(
                        mapOf(
                            "title" to article.title,
                            "slug" to article.slug,
                            "excerpt" to article.excerpt,
                            "parent_id" to article.parentId?.toString(),
                            "sort_order" to article.sortOrder.toString(),
                            "is_published" to article.isPublished,
                        ),
                    )
                    originalSlug = article.slug
                    blocks.clear()
                    article.blocks.forEach { block -> blocks.add(BlockDraft.from(block, uids.getAndIncrement())) }
                }
            } else {
                val siblings = if (parentId == null) nodes else KnowledgeTree.findNode(nodes, parentId)?.children.orEmpty()
                val nextOrder = (siblings.maxOfOrNull { it.sortOrder } ?: 0) + 1
                state.setAll(mapOf("parent_id" to parentId?.toString(), "sort_order" to nextOrder.toString()))
                if (blocks.isEmpty()) {
                    blocks.add(BlockDraft(uid = uids.getAndIncrement(), type = KnowledgeBlock.TYPE_PARAGRAPH))
                }
            }
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            messenger.showError(exception)
        } finally {
            loaded = true
        }
    }

    val parentOptions = KnowledgeTree.flattenWithDepth(tree, excludeId = articleId).map { (node, depth) ->
        Option(value = node.id.toString(), label = "— ".repeat(depth) + node.title)
    }
    val specs = listOf(
        FieldSpec.Text("title", stringResource(R.string.knowledge_article_title), required = true, maxLength = 255),
        FieldSpec.Text("slug", stringResource(R.string.knowledge_slug), hint = stringResource(R.string.knowledge_slug_hint), maxLength = 255),
        FieldSpec.Multiline("excerpt", stringResource(R.string.knowledge_article_excerpt), minLines = 2, maxLength = 2000),
        FieldSpec.Select(
            "parent_id",
            stringResource(R.string.knowledge_article_parent),
            options = parentOptions,
            hint = stringResource(R.string.knowledge_article_parent_hint),
            numeric = true,
        ),
        FieldSpec.Number("sort_order", stringResource(R.string.knowledge_sort_order), required = true),
        FieldSpec.Switch("is_published", stringResource(R.string.knowledge_published)),
    )
    val savedMessage = stringResource(if (articleId == null) R.string.knowledge_article_created else R.string.knowledge_article_updated)
    val noBlocksMessage = stringResource(R.string.knowledge_validation_blocks)
    val invalidBlocksMessage = stringResource(R.string.knowledge_validation_blocks_invalid)
    val messages = BlockMessages(
        paragraph = stringResource(R.string.knowledge_validation_paragraph),
        heading = stringResource(R.string.knowledge_validation_heading),
        list = stringResource(R.string.knowledge_validation_list),
        image = stringResource(R.string.knowledge_validation_image),
    )

    FormScreen(
        title = stringResource(if (articleId == null) R.string.knowledge_create_article else R.string.knowledge_edit_article),
        specs = specs,
        state = state,
        isLoading = !loaded,
        submitLabel = stringResource(R.string.knowledge_save_article),
        onSubmit = {
            clearBlockErrors(state.errors)
            val problems = blockErrors(blocks, messages)
            if (blocks.isEmpty() || problems.isNotEmpty()) {
                problems.forEach { (key, message) -> state.errors[key] = message }
                state.generalError = if (blocks.isEmpty()) noBlocksMessage else invalidBlocksMessage
            } else {
                state.generalError = null
                val images = mutableListOf<Pair<Int, Uri>>()
                val payload = blocks.mapIndexed { index, block ->
                    val image = block.newImage
                    if (block.type == KnowledgeBlock.TYPE_IMAGE && image != null) {
                        images.add(index to image)
                    }
                    block.toPayload()
                }
                val slug = state.string("slug").trim().ifBlank { originalSlug.orEmpty() }.ifBlank { null }
                val body = state.toJson(specs, extra = mapOf("blocks" to payload, "slug" to slug))
                val savedId = repository.saveArticle(context, baseId, articleId, body, images)
                messenger.show(savedMessage)
                if (articleId == null && savedId > 0L) {
                    navigator.replace(KnowledgeRoutes.article(baseId, savedId))
                } else {
                    navigator.back()
                }
            }
        },
        onSuccess = {},
        footer = {
            BlocksEditor(
                blocks = blocks,
                errors = state.errors,
                api = api,
                onAdd = { type ->
                    val draft = BlockDraft(uid = uids.getAndIncrement(), type = type)
                    blocks.add(draft)
                    if (type == KnowledgeBlock.TYPE_IMAGE) {
                        pickingUid = draft.uid
                        imagePicker.launch("image/*")
                    }
                },
                onPickImage = { uid ->
                    pickingUid = uid
                    imagePicker.launch("image/*")
                },
            )
        },
    )
}

private fun clearBlockErrors(errors: SnapshotStateMap<String, String>) {
    errors.keys.filter { it == "blocks" || it.startsWith("blocks.") }.forEach { key -> errors.remove(key) }
}

/** Local validation (same rules as the portal): field key → message. */
private fun blockErrors(blocks: List<BlockDraft>, messages: BlockMessages): Map<String, String> {
    val result = LinkedHashMap<String, String>()
    blocks.forEachIndexed { index, block ->
        val problem: Pair<String, String>? = when (block.type) {
            KnowledgeBlock.TYPE_HEADING -> if (block.content.isBlank()) "content" to messages.heading else null
            KnowledgeBlock.TYPE_LIST -> if (block.listItems.isEmpty()) "items" to messages.list else null
            KnowledgeBlock.TYPE_IMAGE -> if (block.newImage == null && block.imagePath == null) "image_file" to messages.image else null
            else -> if (block.content.isBlank()) "content" to messages.paragraph else null
        }
        if (problem != null) {
            result["blocks.$index.${problem.first}"] = problem.second
        }
    }
    return result
}

private fun moveBlock(blocks: SnapshotStateList<BlockDraft>, uid: Long, offset: Int) {
    val from = blocks.indexOfFirst { it.uid == uid }
    val to = from + offset
    if (from < 0 || to < 0 || to > blocks.lastIndex) return
    val moved = blocks.removeAt(from)
    blocks.add(to, moved)
}

@Composable
private fun BlocksEditor(
    blocks: SnapshotStateList<BlockDraft>,
    errors: SnapshotStateMap<String, String>,
    api: ApiClient,
    onAdd: (String) -> Unit,
    onPickImage: (Long) -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(text = stringResource(R.string.knowledge_content_blocks), style = MaterialTheme.typography.titleMedium)
        val listError = errors["blocks"]
        if (listError != null) {
            Text(text = listError, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
        }
        blocks.forEachIndexed { index, block ->
            key(block.uid) {
                val prefix = "blocks.$index."
                BlockCard(
                    block = block,
                    isFirst = index == 0,
                    isLast = index == blocks.lastIndex,
                    error = errors.entries.firstOrNull { it.key.startsWith(prefix) }?.value,
                    api = api,
                    onChange = { updated ->
                        val position = blocks.indexOfFirst { it.uid == updated.uid }
                        if (position >= 0) {
                            blocks[position] = updated
                            errors.keys.filter { it.startsWith("blocks.$position.") }.forEach { errorKey -> errors.remove(errorKey) }
                        }
                    },
                    onMove = { offset -> moveBlock(blocks, block.uid, offset) },
                    onDelete = {
                        val position = blocks.indexOfFirst { it.uid == block.uid }
                        if (position >= 0) {
                            blocks.removeAt(position)
                        }
                    },
                    onPickImage = { onPickImage(block.uid) },
                )
            }
        }
        Text(text = stringResource(R.string.knowledge_add_block), style = MaterialTheme.typography.labelLarge)
        Row(
            modifier = Modifier.horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            AddBlockChip(label = R.string.knowledge_add_paragraph, icon = blockIcon(KnowledgeBlock.TYPE_PARAGRAPH)) {
                onAdd(KnowledgeBlock.TYPE_PARAGRAPH)
            }
            AddBlockChip(label = R.string.knowledge_add_heading, icon = blockIcon(KnowledgeBlock.TYPE_HEADING)) {
                onAdd(KnowledgeBlock.TYPE_HEADING)
            }
            AddBlockChip(label = R.string.knowledge_add_list, icon = blockIcon(KnowledgeBlock.TYPE_LIST)) {
                onAdd(KnowledgeBlock.TYPE_LIST)
            }
            AddBlockChip(label = R.string.knowledge_add_image, icon = blockIcon(KnowledgeBlock.TYPE_IMAGE)) {
                onAdd(KnowledgeBlock.TYPE_IMAGE)
            }
        }
    }
}

@Composable
private fun AddBlockChip(@StringRes label: Int, icon: ImageVector, onClick: () -> Unit) {
    AssistChip(
        onClick = onClick,
        label = { Text(stringResource(label)) },
        leadingIcon = { Icon(icon, contentDescription = null, modifier = Modifier.size(18.dp)) },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BlockCard(
    block: BlockDraft,
    isFirst: Boolean,
    isLast: Boolean,
    error: String?,
    api: ApiClient,
    onChange: (BlockDraft) -> Unit,
    onMove: (Int) -> Unit,
    onDelete: () -> Unit,
    onPickImage: () -> Unit,
) {
    OutlinedCard(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = blockIcon(block.type),
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(20.dp),
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    text = stringResource(blockTitle(block.type)),
                    style = MaterialTheme.typography.titleSmall,
                    modifier = Modifier.weight(1f),
                )
                IconButton(onClick = { onMove(-1) }, enabled = !isFirst) {
                    Icon(KnowledgeIcons.MoveUp, contentDescription = stringResource(R.string.knowledge_move_up))
                }
                IconButton(onClick = { onMove(1) }, enabled = !isLast) {
                    Icon(KnowledgeIcons.MoveDown, contentDescription = stringResource(R.string.knowledge_move_down))
                }
                IconButton(onClick = onDelete) {
                    Icon(AppIcons.Delete, contentDescription = stringResource(R.string.knowledge_remove_block))
                }
            }
            when (block.type) {
                KnowledgeBlock.TYPE_HEADING -> {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        (1..3).forEach { level ->
                            FilterChip(
                                selected = block.headingLevel == level,
                                onClick = { onChange(block.copy(headingLevel = level)) },
                                label = { Text(stringResource(R.string.knowledge_heading_level_value, level)) },
                            )
                        }
                    }
                    OutlinedTextField(
                        value = block.content,
                        onValueChange = { value -> onChange(block.copy(content = value)) },
                        label = { Text(stringResource(R.string.knowledge_heading_placeholder)) },
                        singleLine = true,
                        isError = error != null,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
                KnowledgeBlock.TYPE_LIST -> {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        FilterChip(
                            selected = !block.ordered,
                            onClick = { onChange(block.copy(ordered = false)) },
                            label = { Text(stringResource(R.string.knowledge_unordered_list)) },
                        )
                        FilterChip(
                            selected = block.ordered,
                            onClick = { onChange(block.copy(ordered = true)) },
                            label = { Text(stringResource(R.string.knowledge_ordered_list)) },
                        )
                    }
                    OutlinedTextField(
                        value = block.itemsText,
                        onValueChange = { value -> onChange(block.copy(itemsText = value)) },
                        label = { Text(stringResource(R.string.knowledge_list_items)) },
                        supportingText = { Text(stringResource(R.string.knowledge_list_items_hint)) },
                        minLines = 3,
                        isError = error != null,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
                KnowledgeBlock.TYPE_IMAGE -> {
                    val existing = block.imageUrl
                    val preview: Any? = block.newImage ?: if (existing != null) api.absoluteUrl(existing) else null
                    if (preview != null) {
                        AsyncImage(
                            model = preview,
                            contentDescription = null,
                            contentScale = ContentScale.Crop,
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(160.dp)
                                .clip(MaterialTheme.shapes.small),
                        )
                    }
                    OutlinedButton(onClick = onPickImage) {
                        Icon(AppIcons.Image, contentDescription = null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.size(8.dp))
                        Text(stringResource(if (preview == null) R.string.knowledge_choose_image else R.string.knowledge_replace_image))
                    }
                    OutlinedTextField(
                        value = block.caption,
                        onValueChange = { value -> onChange(block.copy(caption = value)) },
                        label = { Text(stringResource(R.string.knowledge_image_caption)) },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
                else -> {
                    OutlinedTextField(
                        value = block.content,
                        onValueChange = { value -> onChange(block.copy(content = value)) },
                        label = { Text(stringResource(R.string.knowledge_paragraph)) },
                        placeholder = { Text(stringResource(R.string.knowledge_paragraph_placeholder)) },
                        minLines = 3,
                        isError = error != null,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
            if (error != null) {
                Text(text = error, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }
        }
    }
}

private fun blockIcon(type: String): ImageVector = when (type) {
    KnowledgeBlock.TYPE_HEADING -> AppIcons.Tag
    KnowledgeBlock.TYPE_LIST -> AppIcons.ListView
    KnowledgeBlock.TYPE_IMAGE -> AppIcons.Image
    else -> AppIcons.Document
}

@StringRes
private fun blockTitle(type: String): Int = when (type) {
    KnowledgeBlock.TYPE_HEADING -> R.string.knowledge_add_heading
    KnowledgeBlock.TYPE_LIST -> R.string.knowledge_add_list
    KnowledgeBlock.TYPE_IMAGE -> R.string.knowledge_add_image
    else -> R.string.knowledge_add_paragraph
}
