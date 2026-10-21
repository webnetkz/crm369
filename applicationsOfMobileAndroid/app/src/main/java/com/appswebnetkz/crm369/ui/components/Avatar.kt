package com.appswebnetkz.crm369.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.SubcomposeAsyncImage
import com.appswebnetkz.crm369.core.util.Formatters
import com.appswebnetkz.crm369.core.util.ServerColors
import com.appswebnetkz.crm369.ui.navigation.rememberApi

/**
 * Round avatar: the picture at [imageUrl] (absolute or portal-relative, loaded with the session
 * token) or colored initials of [name] as a fallback.
 */
@Composable
fun Avatar(
    name: String?,
    imageUrl: String?,
    modifier: Modifier = Modifier,
    size: Dp = 40.dp,
    shape: Shape = CircleShape,
) {
    val api = rememberApi()
    val label = name?.takeIf { it.isNotBlank() } ?: "?"
    val background = ServerColors.forKey(label)
    Box(
        modifier = modifier
            .size(size)
            .clip(shape)
            .background(background),
        contentAlignment = Alignment.Center,
    ) {
        val initials: @Composable () -> Unit = {
            Text(
                text = Formatters.initials(label),
                color = Color.White,
                fontSize = (size.value * 0.38f).sp,
                maxLines = 1,
            )
        }
        if (imageUrl.isNullOrBlank()) {
            initials()
        } else {
            SubcomposeAsyncImage(
                model = api.absoluteUrl(imageUrl),
                contentDescription = name,
                contentScale = ContentScale.Crop,
                modifier = Modifier.size(size),
                loading = { initials() },
                error = { initials() },
            )
        }
    }
}

/** Square logo of a portal (falls back to the initials of the portal name). */
@Composable
fun PortalLogo(
    name: String,
    logoUrl: String?,
    modifier: Modifier = Modifier,
    size: Dp = 48.dp,
) {
    Avatar(
        name = name,
        imageUrl = logoUrl,
        modifier = modifier.background(MaterialTheme.colorScheme.surface, RoundedCornerShape(12.dp)),
        size = size,
        shape = RoundedCornerShape(12.dp),
    )
}
