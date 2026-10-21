package com.appswebnetkz.crm369.core.settings

import android.content.Context
import androidx.appcompat.app.AppCompatDelegate
import androidx.core.os.LocaleListCompat
import java.util.Locale
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

enum class ThemeMode(val key: String) {
    System("system"),
    Light("light"),
    Dark("dark"),
    ;

    companion object {
        fun fromKey(key: String?): ThemeMode = entries.firstOrNull { it.key == key } ?: System
    }
}

/** Local, per-device preferences of the application. */
class AppSettings(context: Context) {

    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    private val themeModeState = MutableStateFlow(ThemeMode.fromKey(preferences.getString(KEY_THEME, null)))
    val themeMode: StateFlow<ThemeMode> = themeModeState.asStateFlow()

    fun setThemeMode(mode: ThemeMode) {
        preferences.edit().putString(KEY_THEME, mode.key).apply()
        themeModeState.value = mode
    }

    private companion object {
        const val PREFERENCES_NAME = "crm369_settings"
        const val KEY_THEME = "theme_mode"
    }
}

/** Application language (per-app locale, Android 13 style, back-ported by AppCompat). */
object AppLanguage {
    val supported: List<String> = listOf("ru", "en")

    /** Language explicitly chosen in the application, or null when the system language is used. */
    fun selected(): String? {
        val locales = AppCompatDelegate.getApplicationLocales()
        if (locales.size() == 0) return null
        return locales.get(0)?.language
    }

    /** Effective language tag sent to the server in `Accept-Language`. */
    fun currentTag(): String {
        val locales = AppCompatDelegate.getApplicationLocales()
        val locale: Locale = (if (locales.size() > 0) locales.get(0) else null) ?: Locale.getDefault()
        return locale.toLanguageTag().ifBlank { "ru" }
    }

    /** Effective two-letter language code ("ru" or "en"). */
    fun currentLanguage(): String {
        val language = currentTag().substringBefore('-').lowercase()
        return if (language in supported) language else "ru"
    }

    /** Switches the application language; pass null to follow the system language. */
    fun apply(language: String?) {
        val locales = if (language.isNullOrBlank()) {
            LocaleListCompat.getEmptyLocaleList()
        } else {
            LocaleListCompat.forLanguageTags(language)
        }
        AppCompatDelegate.setApplicationLocales(locales)
    }
}
