package com.appswebnetkz.crm369.ui.components

import android.annotation.SuppressLint
import android.content.Intent
import android.graphics.Color as AndroidColor
import android.net.Uri
import android.text.method.LinkMovementMethod
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.TextView
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.text.HtmlCompat

/**
 * Short rich text (comments, descriptions) rendered with a TextView. Links are clickable.
 * Use [HtmlArticleView] for long articles with images and tables.
 */
@Composable
fun HtmlText(
    html: String,
    modifier: Modifier = Modifier,
    color: Color = MaterialTheme.colorScheme.onSurface,
    linkColor: Color = MaterialTheme.colorScheme.primary,
    textSizeSp: Float = 15f,
) {
    val textColor = color.toArgb()
    val links = linkColor.toArgb()
    AndroidView(
        modifier = modifier.fillMaxWidth(),
        factory = { context ->
            TextView(context).apply {
                movementMethod = LinkMovementMethod.getInstance()
                setLineSpacing(0f, 1.15f)
            }
        },
        update = { view ->
            view.setTextColor(textColor)
            view.setLinkTextColor(links)
            view.textSize = textSizeSp
            view.text = HtmlCompat.fromHtml(html, HtmlCompat.FROM_HTML_MODE_COMPACT).trimEnd()
        },
    )
}

/** Plain text of an HTML fragment (for list previews). */
fun htmlToPlainText(html: String?): String {
    if (html.isNullOrBlank()) return ""
    return HtmlCompat.fromHtml(html, HtmlCompat.FROM_HTML_MODE_COMPACT).toString()
        .replace('\u00A0', ' ')
        .replace(Regex("\\n{3,}"), "\n\n")
        .trim()
}

/**
 * Full screen article rendered in a WebView (news, knowledge base). Relative URLs of images and
 * links are resolved against [baseUrl]. JavaScript is disabled; links open in the browser.
 */
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun HtmlArticleView(
    html: String,
    baseUrl: String,
    modifier: Modifier = Modifier,
    title: String? = null,
    subtitle: String? = null,
) {
    val colors = MaterialTheme.colorScheme
    val textColor = cssColor(colors.onSurface)
    val mutedColor = cssColor(colors.onSurfaceVariant)
    val linkColor = cssColor(colors.primary)
    val borderColor = cssColor(colors.outlineVariant)
    val background = cssColor(colors.surface)
    val document = buildString {
        append("<!DOCTYPE html><html><head><meta charset=\"utf-8\">")
        append("<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">")
        append("<style>")
        append("body{margin:0;padding:16px;font-family:sans-serif;font-size:16px;line-height:1.55;")
        append("color:$textColor;background:$background;word-wrap:break-word;}")
        append("h1{font-size:24px;line-height:1.3;margin:0 0 6px 0;}")
        append(".meta{color:$mutedColor;font-size:13px;margin-bottom:16px;}")
        append("a{color:$linkColor;}img,video,iframe{max-width:100%;height:auto;border-radius:8px;}")
        append("table{border-collapse:collapse;width:100%;display:block;overflow-x:auto;}")
        append("td,th{border:1px solid $borderColor;padding:6px 8px;}")
        append("pre,code{white-space:pre-wrap;background:rgba(127,127,127,0.12);border-radius:6px;padding:2px 4px;}")
        append("blockquote{margin:0;padding-left:12px;border-left:3px solid $borderColor;color:$mutedColor;}")
        append("</style></head><body>")
        if (!title.isNullOrBlank()) {
            append("<h1>")
            append(escapeHtml(title))
            append("</h1>")
        }
        if (!subtitle.isNullOrBlank()) {
            append("<div class=\"meta\">")
            append(escapeHtml(subtitle))
            append("</div>")
        }
        append(html)
        append("</body></html>")
    }
    AndroidView(
        modifier = modifier.fillMaxSize(),
        factory = { context ->
            WebView(context).apply {
                setBackgroundColor(AndroidColor.TRANSPARENT)
                settings.javaScriptEnabled = false
                settings.domStorageEnabled = false
                settings.allowFileAccess = false
                settings.builtInZoomControls = true
                settings.displayZoomControls = false
                webViewClient = object : WebViewClient() {
                    override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                        val intent = Intent(Intent.ACTION_VIEW, request.url).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        return try {
                            view.context.startActivity(intent)
                            true
                        } catch (_: Exception) {
                            false
                        }
                    }
                }
            }
        },
        update = { view ->
            val signature = document.hashCode()
            if (view.tag != signature) {
                view.tag = signature
                view.loadDataWithBaseURL(baseUrl.trimEnd('/') + "/", document, "text/html", "utf-8", null)
            }
        },
    )
}

private fun cssColor(color: Color): String {
    val argb = color.toArgb()
    val red = AndroidColor.red(argb)
    val green = AndroidColor.green(argb)
    val blue = AndroidColor.blue(argb)
    val alpha = AndroidColor.alpha(argb) / 255f
    return "rgba($red,$green,$blue,$alpha)"
}

private fun escapeHtml(value: String): String = value
    .replace("&", "&amp;")
    .replace("<", "&lt;")
    .replace(">", "&gt;")
    .replace("\"", "&quot;")

/** Opens [uri] in an external application; returns false when nothing can handle it. */
fun openExternal(view: android.view.View, uri: Uri): Boolean = try {
    view.context.startActivity(Intent(Intent.ACTION_VIEW, uri).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    true
} catch (_: Exception) {
    false
}
