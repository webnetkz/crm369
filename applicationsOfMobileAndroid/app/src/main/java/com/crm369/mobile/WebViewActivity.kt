package com.appswebnetkz.crm369

import android.Manifest
import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.view.Menu
import android.view.MenuItem
import android.webkit.CookieManager
import android.webkit.PermissionRequest
import android.webkit.URLUtil
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.SslErrorHandler
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import android.net.http.SslError
import androidx.activity.addCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.updatePadding
import com.google.android.material.appbar.MaterialToolbar
import java.net.URI

class WebViewActivity : AppCompatActivity() {
    private lateinit var domainPreferences: DomainPreferences
    private lateinit var notificationSyncStateStore: NotificationSyncStateStore
    private lateinit var notificationSessionCoordinator: MobileNotificationSessionCoordinator
    private lateinit var webView: WebView
    private lateinit var toolbar: MaterialToolbar
    private lateinit var baseUrl: String
    private var pendingMediaPermissionRequest: PermissionRequest? = null
    private var fileChooserCallback: ValueCallback<Array<Uri>>? = null
    private val notificationPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { }
    private val mediaPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions(),
    ) { grants ->
        val permissionRequest = pendingMediaPermissionRequest
        pendingMediaPermissionRequest = null

        if (permissionRequest == null) {
            return@registerForActivityResult
        }

        val requiredPermissions = requiredAndroidPermissions(permissionRequest.resources)
        if (requiredPermissions.all { grants[it] == true }) {
            permissionRequest.grant(permissionRequest.resources)
        } else {
            permissionRequest.deny()
        }
    }
    private val fileChooserLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult(),
    ) { result ->
        val callback = fileChooserCallback
        fileChooserCallback = null
        callback?.onReceiveValue(
            WebChromeClient.FileChooserParams.parseResult(result.resultCode, result.data),
        )
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_web_view)

        domainPreferences = DomainPreferences(this)
        notificationSyncStateStore = NotificationSyncStateStore(this)
        notificationSessionCoordinator = MobileNotificationSessionCoordinator(
            stateStore = object : MobileNotificationSessionCoordinator.NotificationSyncStateHandler {
                override fun isEnabled(baseUrl: String): Boolean {
                    return notificationSyncStateStore.isEnabled(baseUrl)
                }

                override fun enable(baseUrl: String) {
                    notificationSyncStateStore.enable(baseUrl)
                }

                override fun disable(baseUrl: String) {
                    notificationSyncStateStore.disable(baseUrl)
                }
            },
            scheduler = object : MobileNotificationSessionCoordinator.NotificationScheduler {
                override fun scheduleImmediate() {
                    NotificationSyncScheduler.scheduleImmediate(applicationContext)
                }

                override fun schedulePeriodic() {
                    NotificationSyncScheduler.schedulePeriodic(applicationContext)
                }

                override fun cancelAll() {
                    NotificationSyncScheduler.cancelAll(applicationContext)
                }
            },
            cookiePersister = MobileNotificationSessionCoordinator.CookiePersister {
                CookieManager.getInstance().flush()
            },
        )
        webView = findViewById(R.id.webView)
        toolbar = findViewById(R.id.toolbar)

        setSupportActionBar(toolbar)
        supportActionBar?.setDisplayShowTitleEnabled(false)
        toolbar.title = null
        toolbar.subtitle = null

        baseUrl = intent.getStringExtra(EXTRA_BASE_URL) ?: domainPreferences.getBaseUrl() ?: run {
            openDomainSetup()
            return
        }

        configureWebView()
        ensureMobileNotifications()

        onBackPressedDispatcher.addCallback(this) {
            if (webView.canGoBack()) {
                webView.goBack()
            } else {
                finish()
            }
        }

        if (savedInstanceState == null) {
            loadResolvedUrl(intent, shouldResetSession = false)
        } else {
            webView.restoreState(savedInstanceState)
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)

        loadResolvedUrl(intent, shouldResetSession = true)
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.web_view_menu, menu)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            R.id.actionScanQr -> {
                webView.loadUrl(MobileRouteResolver.qrScannerUrl(baseUrl))
                true
            }
            R.id.actionRefresh -> {
                webView.reload()
                true
            }
            R.id.actionChangeDomain -> {
                openDomainSetup()
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        webView.saveState(outState)
        super.onSaveInstanceState(outState)
    }

    override fun onPause() {
        if (::notificationSessionCoordinator.isInitialized && ::baseUrl.isInitialized) {
            notificationSessionCoordinator.handleAppBackgrounded(baseUrl)
        }

        super.onPause()
    }

    override fun onDestroy() {
        webView.destroy()
        super.onDestroy()
    }

    private fun configureWebView() {
        configureWindowInsets()

        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        with(webView.settings) {
            javaScriptEnabled = true
            domStorageEnabled = true
            loadWithOverviewMode = true
            useWideViewPort = true
            userAgentString = "$userAgentString CRM369MobileApp"
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onPermissionRequest(request: PermissionRequest?) {
                val permissionRequest = request ?: return
                val requestHost = permissionRequest.origin.host?.lowercase()
                val configuredHost = runCatching { URI(baseUrl).host?.lowercase() }.getOrNull()

                val requiredPermissions = requiredAndroidPermissions(permissionRequest.resources)
                if (
                    requestHost == null ||
                    requestHost != configuredHost ||
                    requiredPermissions.isEmpty() ||
                    permissionRequest.resources.any { it !in SUPPORTED_MEDIA_RESOURCES }
                ) {
                    permissionRequest.deny()
                    return
                }

                runOnUiThread {
                    val missingPermissions = requiredPermissions.filter {
                        ContextCompat.checkSelfPermission(this@WebViewActivity, it) !=
                            PackageManager.PERMISSION_GRANTED
                    }

                    if (missingPermissions.isEmpty()) {
                        permissionRequest.grant(permissionRequest.resources)
                    } else {
                        pendingMediaPermissionRequest?.deny()
                        pendingMediaPermissionRequest = permissionRequest
                        mediaPermissionLauncher.launch(missingPermissions.toTypedArray())
                    }
                }
            }

            override fun onPermissionRequestCanceled(request: PermissionRequest?) {
                if (pendingMediaPermissionRequest == request) {
                    pendingMediaPermissionRequest = null
                }
            }

            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?,
            ): Boolean {
                val callback = filePathCallback ?: return false
                val params = fileChooserParams ?: return false

                fileChooserCallback?.onReceiveValue(null)
                fileChooserCallback = callback

                return try {
                    fileChooserLauncher.launch(params.createIntent())
                    true
                } catch (_: Exception) {
                    fileChooserCallback = null
                    callback.onReceiveValue(null)
                    false
                }
            }
        }

        webView.setDownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
            enqueueDownload(url, userAgent, contentDisposition, mimeType)
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(
                view: WebView?,
                request: WebResourceRequest?,
            ): Boolean {
                val uri = request?.url ?: return false

                return if (uri.scheme == "http" || uri.scheme == "https") {
                    false
                } else {
                    startActivity(Intent(Intent.ACTION_VIEW, uri))
                    true
                }
            }

            override fun onReceivedSslError(
                view: WebView?,
                handler: SslErrorHandler?,
                error: SslError?,
            ) {
                val host = runCatching {
                    URI(error?.url ?: "").host?.lowercase()
                }.getOrNull()

                if (host != null && LocalDevelopmentHost.isLocalDevelopmentHost(host)) {
                    handler?.proceed()
                    return
                }

                handler?.cancel()
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                notificationSessionCoordinator.handlePageFinished(baseUrl, url)
            }
        }
    }

    private fun ensureMobileNotifications() {
        NotificationChannels.ensure(this)
        NotificationSyncScheduler.schedulePeriodic(this)
        requestNotificationPermissionIfNeeded()
    }

    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return
        }

        if (
            ContextCompat.checkSelfPermission(
                this,
                Manifest.permission.POST_NOTIFICATIONS,
            ) == PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
    }

    private fun loadResolvedUrl(intent: Intent, shouldResetSession: Boolean) {
        val navigation = WebViewNavigationResolver.resolve(
            currentBaseUrl = baseUrl,
            intentBaseUrl = intent.getStringExtra(EXTRA_BASE_URL),
            intentInitialUrl = intent.getStringExtra(EXTRA_INITIAL_URL),
            shouldResetSession = shouldResetSession,
        )

        baseUrl = navigation.baseUrl
        if (navigation.shouldResetSession) {
            resetWebViewSession()
        }

        webView.loadUrl(navigation.initialUrl)
    }

    private fun resetWebViewSession() {
        val cookieManager = CookieManager.getInstance()

        webView.stopLoading()
        webView.clearHistory()
        webView.clearCache(true)
        webView.clearFormData()

        cookieManager.removeAllCookies(null)
        cookieManager.flush()
    }

    private fun requiredAndroidPermissions(resources: Array<String>): List<String> {
        return resources.mapNotNull { resource ->
            when (resource) {
                PermissionRequest.RESOURCE_VIDEO_CAPTURE -> Manifest.permission.CAMERA
                PermissionRequest.RESOURCE_AUDIO_CAPTURE -> Manifest.permission.RECORD_AUDIO
                else -> null
            }
        }.distinct()
    }

    private fun enqueueDownload(
        url: String,
        userAgent: String?,
        contentDisposition: String?,
        mimeType: String?,
    ) {
        val uri = runCatching { Uri.parse(url) }.getOrNull() ?: return
        if (uri.scheme !in setOf("http", "https")) {
            return
        }

        val fileName = URLUtil.guessFileName(url, contentDisposition, mimeType)
        val request = DownloadManager.Request(uri)
            .setTitle(fileName)
            .setMimeType(mimeType)
            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            .setDestinationInExternalFilesDir(this, Environment.DIRECTORY_DOWNLOADS, fileName)

        CookieManager.getInstance().getCookie(url)?.takeIf { it.isNotBlank() }?.let { cookie ->
            request.addRequestHeader("Cookie", cookie)
        }
        userAgent?.takeIf { it.isNotBlank() }?.let { value ->
            request.addRequestHeader("User-Agent", value)
        }

        val downloadManager = getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
        downloadManager.enqueue(request)
        Toast.makeText(this, getString(R.string.download_started), Toast.LENGTH_SHORT).show()
    }

    private fun openDomainSetup() {
        notificationSyncStateStore.disable(baseUrl)
        NotificationSyncScheduler.cancelAll(applicationContext)
        startActivity(DomainSetupActivity.newIntentForEdit(this))
        finish()
    }

    companion object {
        private const val EXTRA_BASE_URL = "base_url"
        private const val EXTRA_INITIAL_URL = "initial_url"
        private val SUPPORTED_MEDIA_RESOURCES = setOf(
            PermissionRequest.RESOURCE_VIDEO_CAPTURE,
            PermissionRequest.RESOURCE_AUDIO_CAPTURE,
        )

        fun newIntent(context: Context, baseUrl: String, initialUrl: String = baseUrl): Intent {
            return Intent(context, WebViewActivity::class.java)
                .putExtra(EXTRA_BASE_URL, baseUrl)
                .putExtra(EXTRA_INITIAL_URL, initialUrl)
        }
    }

    private fun configureWindowInsets() {
        val basePaddingTop = toolbar.paddingTop
        val basePaddingBottom = webView.paddingBottom

        ViewCompat.setOnApplyWindowInsetsListener(toolbar) { view, windowInsets ->
            val statusBarInsets = windowInsets.getInsets(WindowInsetsCompat.Type.statusBars())
            val navigationBarInsets = windowInsets.getInsets(WindowInsetsCompat.Type.navigationBars())

            view.updatePadding(
                top = WebViewToolbarChrome.topPadding(basePaddingTop, statusBarInsets.top),
            )
            webView.updatePadding(
                bottom = WebViewToolbarChrome.bottomPadding(
                    basePaddingBottom,
                    navigationBarInsets.bottom,
                ),
            )

            windowInsets
        }

        ViewCompat.requestApplyInsets(toolbar)
    }
}
