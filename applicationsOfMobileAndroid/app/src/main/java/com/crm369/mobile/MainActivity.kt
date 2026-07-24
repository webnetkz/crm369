package com.appswebnetkz.crm369

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.view.View
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.google.android.material.button.MaterialButton
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.codescanner.GmsBarcodeScannerOptions
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import org.json.JSONObject

class MainActivity : AppCompatActivity() {
    private lateinit var sessionStore: SecureSessionStore
    private lateinit var profileText: TextView
    private lateinit var pushStatusText: TextView
    private val notificationPermission = registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) {
            PushRegistrationManager.sync(this)
            pushStatusText.visibility = View.GONE
        } else {
            showPushStatus(getString(R.string.notification_permission_denied))
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        sessionStore = SecureSessionStore(this)
        if (!sessionStore.hasSession()) {
            AppNavigator.openLogin(this)
            finish()
            return
        }

        setContentView(R.layout.activity_main)
        profileText = findViewById(R.id.profileText)
        pushStatusText = findViewById(R.id.pushStatusText)
        profileText.text = listOf(sessionStore.userName(), sessionStore.userEmail())
            .filter(String::isNotBlank)
            .joinToString(" · ")
        bindModules()
        findViewById<MaterialButton>(R.id.scannerButton).setOnClickListener { startScanner() }
        findViewById<MaterialButton>(R.id.logoutButton).setOnClickListener { logout() }
        findViewById<MaterialButton>(R.id.changeDomainButton).setOnClickListener {
            startActivity(DomainSetupActivity.newIntentForEdit(this))
        }

        loadProfile()
        configurePush()
        handleNotificationIntent(intent)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleNotificationIntent(intent)
    }

    private fun bindModules() {
        mapOf(
            R.id.notificationsButton to NativeModule.Notifications,
            R.id.chatsButton to NativeModule.Chats,
            R.id.projectsButton to NativeModule.Projects,
            R.id.contactsButton to NativeModule.Contacts,
            R.id.calendarButton to NativeModule.Calendar,
            R.id.knowledgeButton to NativeModule.Knowledge,
            R.id.companyButton to NativeModule.Company,
            R.id.warehousesButton to NativeModule.Warehouses,
            R.id.equipmentButton to NativeModule.Equipment,
        ).forEach { (viewId, module) ->
            findViewById<MaterialButton>(viewId).setOnClickListener {
                startActivity(CollectionActivity.newIntent(this, module))
            }
        }
    }

    private fun loadProfile() {
        NativeApiClient(this).get("/api/mobile/v1/me") { result ->
            result.onSuccess { response ->
                val user = response.optJSONObject("data") ?: return@onSuccess
                val name = listOf(user.optString("name"), user.optString("last_name"))
                    .filter(String::isNotBlank)
                    .joinToString(" ")
                profileText.text = listOf(name, user.optString("email"), user.optString("position"))
                    .filter(String::isNotBlank)
                    .joinToString(" · ")
            }.onFailure(::handleFailure)
        }
    }

    private fun configurePush() {
        if (!FirebaseBootstrap.initialize(this)) {
            showPushStatus(getString(R.string.firebase_not_configured))
            return
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            showPushStatus(getString(R.string.push_permission_reason))
            notificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
            return
        }
        PushRegistrationManager.sync(this) { showPushStatus(getString(R.string.firebase_not_configured)) }
    }

    private fun startScanner() {
        val options = GmsBarcodeScannerOptions.Builder()
            .setBarcodeFormats(
                Barcode.FORMAT_QR_CODE,
                Barcode.FORMAT_AZTEC,
                Barcode.FORMAT_DATA_MATRIX,
                Barcode.FORMAT_CODE_128,
            )
            .enableAutoZoom()
            .build()

        GmsBarcodeScanning.getClient(this, options).startScan()
            .addOnSuccessListener { barcode ->
                barcode.rawValue?.takeIf(String::isNotBlank)?.let(::submitScan)
            }
            .addOnFailureListener { error ->
                AlertDialog.Builder(this)
                    .setMessage(error.message ?: getString(R.string.scanner_failed))
                    .setPositiveButton(android.R.string.ok, null)
                    .show()
            }
    }

    private fun submitScan(value: String) {
        val body = JSONObject()
            .put("qr_code", value)
            .put("device_name", "${Build.MANUFACTURER} ${Build.MODEL}".trim())
            .put("context", "CRM369 Android ${BuildConfig.VERSION_NAME}")
        NativeApiClient(this).post("/api/mobile/v1/tsd/scans", body) { result ->
            result.onSuccess { response ->
                val resolved = response.optJSONObject("resolved")
                val detail = resolved?.optString("title")?.takeIf(String::isNotBlank)
                    ?: resolved?.optString("path")?.takeIf(String::isNotBlank)
                    ?: value
                AlertDialog.Builder(this)
                    .setTitle(R.string.scan_saved)
                    .setMessage(detail)
                    .setPositiveButton(android.R.string.ok, null)
                    .show()
            }.onFailure { error ->
                val message = if (error is ApiException && error.statusCode == 403) {
                    getString(R.string.scanner_not_allowed)
                } else {
                    error.message ?: getString(R.string.network_error)
                }
                AlertDialog.Builder(this).setMessage(message).setPositiveButton(android.R.string.ok, null).show()
            }
        }
    }

    private fun logout() {
        NativeApiClient(this).delete("/api/mobile/v1/logout") {
            sessionStore.clear()
            AppNavigator.openLogin(this)
        }
    }

    private fun handleNotificationIntent(intent: Intent) {
        val type = intent.getStringExtra(EXTRA_NOTIFICATION_TYPE)
        val entityId = intent.getStringExtra(EXTRA_ENTITY_ID)
        if (type == "chat_message" && !entityId.isNullOrBlank()) {
            startActivity(ChatActivity.newIntent(this, entityId))
            intent.removeExtra(EXTRA_NOTIFICATION_TYPE)
            return
        }

        val actionPath = intent.getStringExtra(EXTRA_ACTION_PATH).orEmpty()
        val module = when {
            actionPath.contains("/chats") -> NativeModule.Chats
            actionPath.contains("/projects") || actionPath.contains("/tasks") -> NativeModule.Projects
            actionPath.contains("/contacts") -> NativeModule.Contacts
            actionPath.contains("/calendar") -> NativeModule.Calendar
            actionPath.contains("/knowledge") -> NativeModule.Knowledge
            actionPath.contains("/warehouses") -> NativeModule.Warehouses
            actionPath.contains("/equipment") -> NativeModule.Equipment
            type != null -> NativeModule.Notifications
            else -> null
        }
        module?.let { startActivity(CollectionActivity.newIntent(this, it)) }
        intent.removeExtra(EXTRA_NOTIFICATION_TYPE)
    }

    private fun handleFailure(error: Throwable) {
        if (error is ApiException && error.statusCode == 401) {
            sessionStore.clear()
            AppNavigator.openLogin(this, sessionExpired = true)
        }
    }

    private fun showPushStatus(message: String) {
        pushStatusText.text = message
        pushStatusText.visibility = View.VISIBLE
    }

    companion object {
        const val EXTRA_NOTIFICATION_TYPE = "type"
        const val EXTRA_ENTITY_ID = "entity_id"
        const val EXTRA_ACTION_PATH = "action_path"

        fun notificationIntent(
            context: Context,
            type: String?,
            entityId: String?,
            actionPath: String?,
        ): Intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra(EXTRA_NOTIFICATION_TYPE, type)
            putExtra(EXTRA_ENTITY_ID, entityId)
            putExtra(EXTRA_ACTION_PATH, actionPath)
        }
    }
}
