package com.appswebnetkz.crm369.ui.web

import android.Manifest
import android.annotation.SuppressLint
import android.app.DownloadManager
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.webkit.CookieManager
import android.webkit.PermissionRequest
import android.webkit.URLUtil
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.appswebnetkz.crm369.R
import com.appswebnetkz.crm369.core.network.ApiClient
import com.appswebnetkz.crm369.core.network.ApiException
import com.appswebnetkz.crm369.ui.components.CrmScaffold
import com.appswebnetkz.crm369.ui.components.ErrorState
import com.appswebnetkz.crm369.ui.components.LoadingState
import com.appswebnetkz.crm369.ui.navigation.LocalAppContainer
import com.appswebnetkz.crm369.ui.navigation.LocalMessenger
import com.appswebnetkz.crm369.ui.navigation.LocalNavigator
import com.appswebnetkz.crm369.ui.navigation.rememberApi
import com.appswebnetkz.crm369.ui.theme.AppIcons
import kotlinx.coroutines.CancellationException

/**
 * Opens a page of the portal (administration, conference room, any page without a native
 * screen) in an in-app browser. The current user is signed in automatically with a single-use
 * link from `POST /api/mobile/v1/web-session`.
 */
@Composable
fun WebPortalScreen(
    path: String,
    title: String?,
    modifier: Modifier = Modifier,
) {
    val container = LocalAppContainer.current
    val navigator = LocalNavigator.current
    val api = rememberApi()
    var startUrl by remember(path) { mutableStateOf<String?>(null) }
    var error by remember(path) { mutableStateOf<Throwable?>(null) }
    var attempt by remember(path) { mutableIntStateOf(0) }
    var pageTitle by remember { mutableStateOf<String?>(null) }
    var progress by remember { mutableIntStateOf(0) }
    var canGoBack by remember { mutableStateOf(false) }
    var webView by remember { mutableStateOf<WebView?>(null) }

    LaunchedEffect(path, attempt) {
        error = null
        startUrl = try {
            container.session.openWebSession(path)
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: ApiException) {
            if (exception.isNotFound) api.absoluteUrl(path) else {
                error = exception
                null
            }
        } catch (exception: Exception) {
            error = exception
            null
        }
    }

    BackHandler(enabled = canGoBack) {
        webView?.goBack()
    }

    CrmScaffold(
        title = title?.takeIf { it.isNotBlank() } ?: pageTitle ?: stringResource(R.string.web_title_default),
        modifier = modifier,
        actions = {
            IconButton(onClick = { webView?.reload() }) {
                Icon(AppIcons.Refresh, contentDescription = stringResource(R.string.action_refresh))
            }
            IconButton(onClick = { navigator.openUrl(webView?.url ?: api.absoluteUrl(path)) }) {
                Icon(AppIcons.Public, contentDescription = stringResource(R.string.web_open_in_browser))
            }
        },
    ) { padding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            val currentError = error
            val url = startUrl
            when {
                currentError != null -> ErrorState(error = currentError, onRetry = { attempt += 1 })
                url == null -> LoadingState()
                else -> PortalWebView(
                    url = url,
                    api = api,
                    onWebViewCreated = { created -> webView = created },
                    onProgress = { value -> progress = value },
                    onTitle = { value -> pageTitle = value },
                    onHistoryChanged = { back -> canGoBack = back },
                )
            }
            if (startUrl != null && progress in 1..99) {
                LinearProgressIndicator(
                    progress = { progress / 100f },
                    modifier = Modifier
                        .fillMaxWidth()
                        .align(Alignment.TopCenter),
                )
            }
        }
    }
}

@SuppressLint("SetJavaScriptEnabled")
@Composable
private fun PortalWebView(
    url: String,
    api: ApiClient,
    onWebViewCreated: (WebView) -> Unit,
    onProgress: (Int) -> Unit,
    onTitle: (String?) -> Unit,
    onHistoryChanged: (Boolean) -> Unit,
) {
    val context = LocalContext.current
    val messenger = LocalMessenger.current
    val downloadStarted = stringResource(R.string.web_download_started)

    var filePathCallback by remember { mutableStateOf<ValueCallback<Array<Uri>>?>(null) }
    var pendingPermissionRequest by remember { mutableStateOf<PermissionRequest?>(null) }

    val fileChooserLauncher = rememberLauncherForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        filePathCallback?.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(result.resultCode, result.data))
        filePathCallback = null
    }

    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { _ ->
        val request = pendingPermissionRequest
        pendingPermissionRequest = null
        if (request != null) {
            grantWebPermissions(context, request)
        }
    }

    var view by remember { mutableStateOf<WebView?>(null) }

    DisposableEffect(Unit) {
        onDispose {
            view?.apply {
                stopLoading()
                destroy()
            }
        }
    }

    AndroidView(
        modifier = Modifier.fillMaxSize(),
        factory = { viewContext ->
            WebView(viewContext).apply {
                settings.javaScriptEnabled = true
                settings.domStorageEnabled = true
                settings.mediaPlaybackRequiresUserGesture = false
                settings.loadWithOverviewMode = true
                settings.useWideViewPort = true
                settings.setSupportZoom(true)
                settings.builtInZoomControls = true
                settings.displayZoomControls = false
                settings.allowFileAccess = false
                settings.userAgentString = settings.userAgentString + " " + ApiClient.USER_AGENT

                CookieManager.getInstance().setAcceptCookie(true)
                CookieManager.getInstance().setAcceptThirdPartyCookies(this, true)

                webViewClient = object : WebViewClient() {
                    override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                        val target = request?.url ?: return false
                        val scheme = target.scheme?.lowercase()
                        if (scheme == "http" || scheme == "https") {
                            if (api.isPortalUrl(target.toString())) return false
                        }
                        return openExternally(viewContext, target)
                    }

                    override fun doUpdateVisitedHistory(view: WebView?, url: String?, isReload: Boolean) {
                        super.doUpdateVisitedHistory(view, url, isReload)
                        onHistoryChanged(view?.canGoBack() == true)
                    }

                    override fun onPageFinished(view: WebView?, url: String?) {
                        super.onPageFinished(view, url)
                        CookieManager.getInstance().flush()
                        onHistoryChanged(view?.canGoBack() == true)
                    }
                }

                webChromeClient = object : WebChromeClient() {
                    override fun onProgressChanged(view: WebView?, newProgress: Int) {
                        onProgress(newProgress)
                    }

                    override fun onReceivedTitle(view: WebView?, title: String?) {
                        onTitle(title?.takeIf { it.isNotBlank() && !it.startsWith("http") })
                    }

                    override fun onPermissionRequest(request: PermissionRequest?) {
                        if (request == null) return
                        val needed = requiredAndroidPermissions(request)
                        val missing = needed.filter { permission ->
                            ContextCompat.checkSelfPermission(viewContext, permission) != PackageManager.PERMISSION_GRANTED
                        }
                        if (missing.isEmpty()) {
                            grantWebPermissions(viewContext, request)
                        } else {
                            pendingPermissionRequest = request
                            permissionLauncher.launch(missing.toTypedArray())
                        }
                    }

                    override fun onShowFileChooser(
                        webView: WebView?,
                        filePathCallback2: ValueCallback<Array<Uri>>?,
                        fileChooserParams: WebChromeClient.FileChooserParams?,
                    ): Boolean {
                        filePathCallback?.onReceiveValue(null)
                        filePathCallback = filePathCallback2
                        val intent = fileChooserParams?.createIntent() ?: return false
                        return try {
                            fileChooserLauncher.launch(intent)
                            true
                        } catch (_: ActivityNotFoundException) {
                            filePathCallback = null
                            false
                        }
                    }
                }

                setDownloadListener { downloadUrl, userAgent, contentDisposition, mimeType, _ ->
                    // DownloadManager throws for non-HTTP(S) URLs (blob:/data: exports) and bad destinations.
                    try {
                        enqueueDownload(viewContext, downloadUrl, userAgent, contentDisposition, mimeType)
                        messenger.show(downloadStarted)
                    } catch (exception: Exception) {
                        messenger.showError(exception)
                    }
                }

                view = this
                onWebViewCreated(this)
            }
        },
        update = { webView ->
            if (webView.tag != url) {
                webView.tag = url
                webView.loadUrl(url)
            }
        },
    )
}

private fun requiredAndroidPermissions(request: PermissionRequest): List<String> {
    val permissions = mutableListOf<String>()
    request.resources.forEach { resource ->
        when (resource) {
            PermissionRequest.RESOURCE_VIDEO_CAPTURE -> permissions.add(Manifest.permission.CAMERA)
            PermissionRequest.RESOURCE_AUDIO_CAPTURE -> {
                permissions.add(Manifest.permission.RECORD_AUDIO)
                permissions.add(Manifest.permission.MODIFY_AUDIO_SETTINGS)
            }
        }
    }
    return permissions.distinct()
}

private fun grantWebPermissions(context: Context, request: PermissionRequest) {
    val granted = request.resources.filter { resource ->
        when (resource) {
            PermissionRequest.RESOURCE_VIDEO_CAPTURE ->
                ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED
            PermissionRequest.RESOURCE_AUDIO_CAPTURE ->
                ContextCompat.checkSelfPermission(context, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED
            else -> false
        }
    }
    if (granted.isEmpty()) {
        request.deny()
    } else {
        request.grant(granted.toTypedArray())
    }
}

private fun openExternally(context: Context, uri: Uri): Boolean = try {
    context.startActivity(Intent(Intent.ACTION_VIEW, uri).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    true
} catch (_: ActivityNotFoundException) {
    true
}

private fun enqueueDownload(
    context: Context,
    url: String,
    userAgent: String?,
    contentDisposition: String?,
    mimeType: String?,
) {
    val fileName = URLUtil.guessFileName(url, contentDisposition, mimeType)
    val request = DownloadManager.Request(Uri.parse(url))
        .setTitle(fileName)
        .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
        // Android 10+: the public Downloads folder needs no storage permission.
        request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, fileName)
    } else {
        request.setDestinationInExternalFilesDir(context, Environment.DIRECTORY_DOWNLOADS, fileName)
    }
    if (!mimeType.isNullOrBlank()) request.setMimeType(mimeType)
    CookieManager.getInstance().getCookie(url)?.let { cookies -> request.addRequestHeader("Cookie", cookies) }
    if (!userAgent.isNullOrBlank()) request.addRequestHeader("User-Agent", userAgent)
    val manager = context.getSystemService(Context.DOWNLOAD_SERVICE) as? DownloadManager ?: return
    manager.enqueue(request)
}
