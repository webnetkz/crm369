package com.appswebnetkz.crm369

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.view.inputmethod.EditorInfo
import android.widget.Button
import androidx.appcompat.app.AppCompatActivity
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout

class DomainSetupActivity : AppCompatActivity() {
    private lateinit var domainPreferences: DomainPreferences
    private lateinit var domainInputLayout: TextInputLayout
    private lateinit var domainInput: TextInputEditText

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)

        domainPreferences = DomainPreferences(this)

        if (!intent.getBooleanExtra(EXTRA_FORCE_EDIT, false)) {
            domainPreferences.getBaseUrl()?.let {
                AppNavigator.openAuthenticatedArea(this)
                finish()
                return
            }
        }

        setContentView(R.layout.activity_domain_setup)

        domainInputLayout = findViewById(R.id.domainInputLayout)
        domainInput = findViewById(R.id.domainInput)
        val continueButton: Button = findViewById(R.id.continueButton)

        domainInput.setText(domainPreferences.getBaseUrl().orEmpty())

        continueButton.setOnClickListener {
            submitDomain()
        }

        domainInput.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                submitDomain()
                true
            } else {
                false
            }
        }
    }

    private fun submitDomain() {
        domainInputLayout.error = null

        val normalizedBaseUrl = DomainUrlNormalizer.normalize(
            domainInput.text?.toString().orEmpty(),
            runningOnEmulator = DeviceEnvironment.isEmulator(),
        )

        if (normalizedBaseUrl == null) {
            domainInputLayout.error = getString(R.string.domain_error_invalid)
            return
        }

        val serverChanged = domainPreferences.getBaseUrl() != normalizedBaseUrl
        domainPreferences.saveBaseUrl(normalizedBaseUrl)
        if (serverChanged) {
            SecureSessionStore(this).clear()
        }
        AppNavigator.openAuthenticatedArea(this)
        finish()
    }

    companion object {
        const val EXTRA_FORCE_EDIT = "force_edit"

        fun newIntentForEdit(context: Context): Intent {
            return Intent(context, DomainSetupActivity::class.java)
                .putExtra(EXTRA_FORCE_EDIT, true)
        }
    }
}
