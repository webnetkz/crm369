package com.appswebnetkz.crm369.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.ColorScheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import com.appswebnetkz.crm369.core.settings.ThemeMode

/** Brand colors of CRM369 (teal primary, gold accent from the logo). */
object BrandColors {
    val Teal = Color(0xFF0A6C74)
    val Gold = Color(0xFFC99A2E)
    val Navy = Color(0xFF14232B)
    val Success = Color(0xFF16A34A)
    val Warning = Color(0xFFD97706)
    val Danger = Color(0xFFDC2626)
    val Info = Color(0xFF2563EB)
}

private val LightColors: ColorScheme = lightColorScheme(
    primary = Color(0xFF0A6C74),
    onPrimary = Color(0xFFFFFFFF),
    primaryContainer = Color(0xFFA7EEF5),
    onPrimaryContainer = Color(0xFF002022),
    secondary = Color(0xFF4A6365),
    onSecondary = Color(0xFFFFFFFF),
    secondaryContainer = Color(0xFFCCE8EA),
    onSecondaryContainer = Color(0xFF051F21),
    tertiary = Color(0xFF7A5900),
    onTertiary = Color(0xFFFFFFFF),
    tertiaryContainer = Color(0xFFFFDEA0),
    onTertiaryContainer = Color(0xFF261900),
    error = Color(0xFFBA1A1A),
    onError = Color(0xFFFFFFFF),
    errorContainer = Color(0xFFFFDAD6),
    onErrorContainer = Color(0xFF410002),
    background = Color(0xFFF5FAFB),
    onBackground = Color(0xFF171D1E),
    surface = Color(0xFFF5FAFB),
    onSurface = Color(0xFF171D1E),
    surfaceVariant = Color(0xFFDAE4E5),
    onSurfaceVariant = Color(0xFF3F4849),
    outline = Color(0xFF6F7979),
    outlineVariant = Color(0xFFBEC8C9),
    inverseSurface = Color(0xFF2B3132),
    inverseOnSurface = Color(0xFFECF2F2),
    inversePrimary = Color(0xFF84D3DB),
    surfaceContainerLowest = Color(0xFFFFFFFF),
    surfaceContainerLow = Color(0xFFEFF5F5),
    surfaceContainer = Color(0xFFE9EFF0),
    surfaceContainerHigh = Color(0xFFE3E9EA),
    surfaceContainerHighest = Color(0xFFDEE4E4),
)

private val DarkColors: ColorScheme = darkColorScheme(
    primary = Color(0xFF84D3DB),
    onPrimary = Color(0xFF00363A),
    primaryContainer = Color(0xFF004F55),
    onPrimaryContainer = Color(0xFFA7EEF5),
    secondary = Color(0xFFB1CBCE),
    onSecondary = Color(0xFF1B3437),
    secondaryContainer = Color(0xFF324B4D),
    onSecondaryContainer = Color(0xFFCCE8EA),
    tertiary = Color(0xFFF2BF48),
    onTertiary = Color(0xFF402D00),
    tertiaryContainer = Color(0xFF5C4300),
    onTertiaryContainer = Color(0xFFFFDEA0),
    error = Color(0xFFFFB4AB),
    onError = Color(0xFF690005),
    errorContainer = Color(0xFF93000A),
    onErrorContainer = Color(0xFFFFDAD6),
    background = Color(0xFF0E1415),
    onBackground = Color(0xFFDEE4E4),
    surface = Color(0xFF0E1415),
    onSurface = Color(0xFFDEE4E4),
    surfaceVariant = Color(0xFF3F4849),
    onSurfaceVariant = Color(0xFFBEC8C9),
    outline = Color(0xFF899393),
    outlineVariant = Color(0xFF3F4849),
    inverseSurface = Color(0xFFDEE4E4),
    inverseOnSurface = Color(0xFF2B3132),
    inversePrimary = Color(0xFF0A6C74),
    surfaceContainerLowest = Color(0xFF090F10),
    surfaceContainerLow = Color(0xFF171D1E),
    surfaceContainer = Color(0xFF1B2122),
    surfaceContainerHigh = Color(0xFF252B2C),
    surfaceContainerHighest = Color(0xFF303637),
)

private val AppTypography = Typography(
    headlineSmall = TextStyle(fontSize = 24.sp, lineHeight = 32.sp, fontWeight = FontWeight.SemiBold),
    titleLarge = TextStyle(fontSize = 22.sp, lineHeight = 28.sp, fontWeight = FontWeight.SemiBold),
    titleMedium = TextStyle(fontSize = 16.sp, lineHeight = 24.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.15.sp),
    titleSmall = TextStyle(fontSize = 14.sp, lineHeight = 20.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.1.sp),
)

/** Extra semantic colors that Material 3 does not provide. */
@Immutable
data class ExtendedColors(
    val success: Color,
    val warning: Color,
    val info: Color,
    val accent: Color,
)

val LocalExtendedColors = staticCompositionLocalOf {
    ExtendedColors(success = BrandColors.Success, warning = BrandColors.Warning, info = BrandColors.Info, accent = BrandColors.Gold)
}

@Composable
fun Crm369Theme(
    themeMode: ThemeMode = ThemeMode.System,
    content: @Composable () -> Unit,
) {
    val darkTheme = when (themeMode) {
        ThemeMode.System -> isSystemInDarkTheme()
        ThemeMode.Light -> false
        ThemeMode.Dark -> true
    }
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        typography = AppTypography,
        content = content,
    )
}
