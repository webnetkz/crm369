package com.appswebnetkz.crm369

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

class SecureSessionStore(context: Context) {
    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    fun save(token: String, userName: String, userEmail: String) {
        preferences.edit()
            .putString(KEY_TOKEN, encrypt(token))
            .putString(KEY_USER_NAME, userName)
            .putString(KEY_USER_EMAIL, userEmail)
            .commit()
    }

    fun token(): String? {
        val encryptedToken = preferences.getString(KEY_TOKEN, null) ?: return null

        return runCatching { decrypt(encryptedToken) }
            .getOrElse {
                clear()
                null
            }
            ?.takeIf { it.isNotBlank() }
    }

    fun userName(): String = preferences.getString(KEY_USER_NAME, "").orEmpty()

    fun userEmail(): String = preferences.getString(KEY_USER_EMAIL, "").orEmpty()

    fun hasSession(): Boolean = token() != null

    fun clear() {
        preferences.edit().clear().commit()
    }

    private fun encrypt(value: String): String {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, encryptionKey())

        val iv = Base64.encodeToString(cipher.iv, Base64.NO_WRAP)
        val encrypted = Base64.encodeToString(cipher.doFinal(value.toByteArray(Charsets.UTF_8)), Base64.NO_WRAP)

        return "$iv:$encrypted"
    }

    private fun decrypt(value: String): String {
        val parts = value.split(':', limit = 2)
        require(parts.size == 2)

        val cipher = Cipher.getInstance(TRANSFORMATION)
        val iv = Base64.decode(parts[0], Base64.NO_WRAP)
        cipher.init(Cipher.DECRYPT_MODE, encryptionKey(), GCMParameterSpec(128, iv))

        return cipher.doFinal(Base64.decode(parts[1], Base64.NO_WRAP)).toString(Charsets.UTF_8)
    }

    private fun encryptionKey(): SecretKey {
        val keyStore = KeyStore.getInstance(KEYSTORE_PROVIDER).apply { load(null) }
        val existingKey = keyStore.getKey(KEY_ALIAS, null) as? SecretKey

        if (existingKey != null) {
            return existingKey
        }

        val keyGenerator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, KEYSTORE_PROVIDER)
        keyGenerator.init(
            KeyGenParameterSpec.Builder(
                KEY_ALIAS,
                KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
            )
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .build(),
        )

        return keyGenerator.generateKey()
    }

    companion object {
        private const val PREFERENCES_NAME = "crm369_secure_session"
        private const val KEY_TOKEN = "access_token"
        private const val KEY_USER_NAME = "user_name"
        private const val KEY_USER_EMAIL = "user_email"
        private const val KEYSTORE_PROVIDER = "AndroidKeyStore"
        private const val KEY_ALIAS = "crm369_mobile_session_key"
        private const val TRANSFORMATION = "AES/GCM/NoPadding"
    }
}
