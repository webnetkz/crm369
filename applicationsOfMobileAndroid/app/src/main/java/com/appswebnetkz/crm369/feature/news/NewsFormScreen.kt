package com.appswebnetkz.crm369.feature.news

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.ui.form.FieldSpec
import com.appswebnetkz.crm369.ui.form.FormScreen
import com.appswebnetkz.crm369.ui.form.FormState
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException

/**
 * Create ([slug] = null) or edit a news item. Updates replace the whole record, so the current
 * slug is always sent; an image switches the request to multipart (`image_file`).
 */
@Composable
fun NewsFormScreen(slug: String?) {
    val api = rememberApi()
    val context = LocalContext.current
    val navigator = LocalNavigator.current
    val messenger = LocalMessenger.current
    val repository = remember(api) { NewsRepository(api) }
    val state = remember(slug) { FormState(mapOf("is_published" to true)) }
    var loaded by remember(slug) { mutableStateOf(slug == null) }
    var imageUrl by remember(slug) { mutableStateOf<String?>(null) }
    var newImage by remember(slug) { mutableStateOf<Uri?>(null) }
    var removeImage by remember(slug) { mutableStateOf(false) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            newImage = uri
            removeImage = false
        }
    }

    LaunchedEffect(slug) {
        if (slug == null) return@LaunchedEffect
        try {
            val detail = repository.detail(slug).active
            if (detail != null) {
                state.setAll(
                    mapOf(
                        "title" to detail.item.title,
                        "slug" to detail.item.slug,
                        "excerpt" to detail.item.excerpt,
                        "content" to detail.content,
                        "is_published" to detail.item.isPublished,
                    ),
                )
                imageUrl = detail.item.imageUrl
            }
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: Exception) {
            messenger.showError(exception)
        } finally {
            loaded = true
        }
    }

    val specs = listOf(
        FieldSpec.Text("title", stringResource(R.string.news_form_title), required = true, maxLength = 255),
        FieldSpec.Text(
            "slug",
            stringResource(R.string.news_form_slug),
            hint = stringResource(if (slug == null) R.string.news_form_slug_hint else R.string.news_form_slug_edit_hint),
            maxLength = 255,
        ),
        FieldSpec.Multiline("excerpt", stringResource(R.string.news_form_excerpt), minLines = 2, maxLength = 1000),
        FieldSpec.Multiline(
            "content",
            stringResource(R.string.news_form_content),
            required = true,
            hint = stringResource(R.string.news_form_content_hint),
            minLines = 8,
            maxLength = 30000,
        ),
        FieldSpec.Switch("is_published", stringResource(R.string.news_form_publish)),
    )
    val savedMessage = stringResource(if (slug == null) R.string.news_created else R.string.news_updated)

    FormScreen(
        title = stringResource(if (slug == null) R.string.news_new else R.string.news_edit),
        specs = specs,
        state = state,
        isLoading = !loaded,
        successMessage = savedMessage,
        onSubmit = {
            val image = newImage
            if (slug == null) {
                val body = state.toJson(specs, extra = mapOf("remove_image" to false))
                val created = repository.create(context, body, image)
                navigator.replace(NewsRoutes.detail(created.item.slug))
            } else {
                val currentSlug = state.string("slug").trim().ifBlank { slug }
                val body = state.toJson(
                    specs,
                    extra = mapOf(
                        "slug" to currentSlug,
                        "remove_image" to (removeImage && image == null),
                    ),
                )
                val updated = repository.update(context, slug, body, image)
                val newSlug = updated.item.slug.ifBlank { currentSlug }
                if (!navigator.back() || newSlug != slug) {
                    navigator.replace(NewsRoutes.detail(newSlug))
                }
            }
        },
        onSuccess = {},
        header = {
            val existing = imageUrl
            val preview: Any? = newImage ?: if (existing != null && !removeImage) api.absoluteUrl(existing) else null
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                Text(stringResource(R.string.news_form_image), style = MaterialTheme.typography.titleSmall)
                if (preview != null) {
                    AsyncImage(
                        model = preview,
                        contentDescription = null,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(180.dp)
                            .clip(MaterialTheme.shapes.medium),
                    )
                }
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    OutlinedButton(onClick = { picker.launch("image/*") }) {
                        Icon(AppIcons.Image, contentDescription = null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.size(8.dp))
                        Text(stringResource(if (preview == null) R.string.news_form_choose_image else R.string.news_form_replace_image))
                    }
                    if (preview != null) {
                        TextButton(
                            onClick = {
                                if (newImage != null) {
                                    newImage = null
                                } else {
                                    removeImage = true
                                }
                            },
                        ) {
                            Text(stringResource(R.string.news_form_remove_image))
                        }
                    }
                }
            }
        },
    )
}
