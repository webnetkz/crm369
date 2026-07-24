package com.appswebnetkz.crm369

import android.content.Intent
import android.os.Build
import android.os.Bundle
import android.view.View
import android.view.inputmethod.EditorInfo
import android.widget.Button
import android.widget.ProgressBar
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout
import org.json.JSONObject

class LoginActivity : AppCompatActivity() {
    private lateinit var emailLayout: TextInputLayout
    private lateinit var passwordLayout: TextInputLayout
    private lateinit var twoFactorLayout: TextInputLayout
    private lateinit var emailInput: TextInputEditText
    private lateinit var passwordInput: TextInputEditText
    private lateinit var twoFactorInput: TextInputEditText
    private lateinit var errorText: TextView
    private lateinit var progress: ProgressBar
    private lateinit var loginButton: Button
    private var challenge: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (SecureSessionStore(this).hasSession()) {
            AppNavigator.openAuthenticatedArea(this)
            finish()
            return
        }

        setContentView(R.layout.activity_login)
        bindViews()

        findViewById<TextView>(R.id.serverLabel).text = DomainPreferences(this).getBaseUrl().orEmpty()
        if (intent.getBooleanExtra(EXTRA_SESSION_EXPIRED, false)) {
            showError(getString(R.string.session_expired))
        }

        loginButton.setOnClickListener { submit() }
        findViewById<Button>(R.id.changeServerButton).setOnClickListener {
            startActivity(DomainSetupActivity.newIntentForEdit(this))
        }
        passwordInput.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                submit()
                true
            } else {
                false
            }
        }
        twoFactorInput.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                submit()
                true
            } else {
                false
            }
        }
    }

    private fun bindViews() {
        emailLayout = findViewById(R.id.emailLayout)
        passwordLayout = findViewById(R.id.passwordLayout)
        twoFactorLayout = findViewById(R.id.twoFactorLayout)
        emailInput = findViewById(R.id.emailInput)
        passwordInput = findViewById(R.id.passwordInput)
        twoFactorInput = findViewById(R.id.twoFactorInput)
        errorText = findViewById(R.id.errorText)
        progress = findViewById(R.id.loginProgress)
        loginButton = findViewById(R.id.loginButton)
    }

    private fun submit() {
        clearErrors()
        if (challenge == null) {
            submitCredentials()
        } else {
            submitTwoFactor()
        }
    }

    private fun submitCredentials() {
        val email = emailInput.text?.toString()?.trim().orEmpty()
        val password = passwordInput.text?.toString().orEmpty()

        if (email.isBlank()) {
            emailLayout.error = getString(R.string.required_field)
            return
        }
        if (password.isBlank()) {
            passwordLayout.error = getString(R.string.required_field)
            return
        }

        setLoading(true)
        NativeApiClient(this).post(
            "/api/mobile/v1/login",
            deviceContext()
                .put("email", email)
                .put("password", password),
            authenticated = false,
        ) { result ->
            setLoading(false)
            result.onSuccess { response ->
                if (response.optBoolean("two_factor_required")) {
                    challenge = response.optString("challenge").takeIf { it.isNotBlank() }
                    showTwoFactor()
                } else {
                    completeLogin(response)
                }
            }.onFailure(::handleFailure)
        }
    }

    private fun submitTwoFactor() {
        val enteredCode = twoFactorInput.text?.toString()?.trim().orEmpty()
        if (enteredCode.isBlank()) {
            twoFactorLayout.error = getString(R.string.required_field)
            return
        }

        val payload = JSONObject().put("challenge", challenge)
        if (enteredCode.length == 6 && enteredCode.all(Char::isDigit)) {
            payload.put("code", enteredCode)
        } else {
            payload.put("recovery_code", enteredCode)
        }

        setLoading(true)
        NativeApiClient(this).post(
            "/api/mobile/v1/two-factor-challenge",
            payload,
            authenticated = false,
        ) { result ->
            setLoading(false)
            result.onSuccess(::completeLogin).onFailure(::handleFailure)
        }
    }

    private fun completeLogin(response: JSONObject) {
        val data = response.optJSONObject("data") ?: run {
            showError(getString(R.string.invalid_server_response))
            return
        }
        val token = data.optString("token")
        val user = data.optJSONObject("user") ?: JSONObject()
        if (token.isBlank()) {
            showError(getString(R.string.invalid_server_response))
            return
        }

        val fullName = listOf(user.optString("name"), user.optString("last_name"))
            .filter(String::isNotBlank)
            .joinToString(" ")
        SecureSessionStore(this).save(token, fullName, user.optString("email"))
        PushRegistrationManager.sync(this)
        AppNavigator.openAuthenticatedArea(this)
        finish()
    }

    private fun showTwoFactor() {
        emailLayout.visibility = View.GONE
        passwordLayout.visibility = View.GONE
        twoFactorLayout.visibility = View.VISIBLE
        loginButton.setText(R.string.two_factor_action)
        twoFactorInput.requestFocus()
    }

    private fun handleFailure(error: Throwable) {
        showError(error.message ?: getString(R.string.network_error))
        if (error is ApiException && error.statusCode == 401) {
            challenge = null
            emailLayout.visibility = View.VISIBLE
            passwordLayout.visibility = View.VISIBLE
            twoFactorLayout.visibility = View.GONE
        }
    }

    private fun deviceContext(): JSONObject = JSONObject()
        .put("device_id", DeviceIdentity(this).id())
        .put("device_name", "${Build.MANUFACTURER} ${Build.MODEL}".trim())
        .put("app_version", BuildConfig.VERSION_NAME)

    private fun setLoading(isLoading: Boolean) {
        progress.visibility = if (isLoading) View.VISIBLE else View.GONE
        loginButton.isEnabled = !isLoading
    }

    private fun clearErrors() {
        emailLayout.error = null
        passwordLayout.error = null
        twoFactorLayout.error = null
        errorText.visibility = View.GONE
    }

    private fun showError(message: String) {
        errorText.text = message
        errorText.visibility = View.VISIBLE
    }

    companion object {
        const val EXTRA_SESSION_EXPIRED = "session_expired"
    }
}
