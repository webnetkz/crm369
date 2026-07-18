import UIKit
import WebKit

final class CRMWebViewController: UIViewController, WKNavigationDelegate, WKUIDelegate {
    let baseURL: URL

    private let initialURL: URL
    private let onChangeDomain: () -> Void
    private let webView: WKWebView

    init(baseURL: URL, initialURL: URL, onChangeDomain: @escaping () -> Void) {
        self.baseURL = baseURL
        self.initialURL = initialURL
        self.onChangeDomain = onChangeDomain

        let configuration = WKWebViewConfiguration()
        configuration.websiteDataStore = .default()
        configuration.allowsInlineMediaPlayback = true
        configuration.mediaTypesRequiringUserActionForPlayback = []
        configuration.defaultWebpagePreferences.allowsContentJavaScript = true
        configuration.applicationNameForUserAgent = "CRM369MobileApp iOS"

        webView = WKWebView(frame: .zero, configuration: configuration)
        super.init(nibName: nil, bundle: nil)
    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        title = "CRM369"
        view.backgroundColor = .systemBackground

        webView.navigationDelegate = self
        webView.uiDelegate = self
        webView.allowsBackForwardNavigationGestures = true
        webView.translatesAutoresizingMaskIntoConstraints = false

        view.addSubview(webView)
        NSLayoutConstraint.activate([
            webView.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            webView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            webView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            webView.bottomAnchor.constraint(equalTo: view.bottomAnchor),
        ])

        navigationItem.leftBarButtonItem = UIBarButtonItem(
            image: UIImage(systemName: "qrcode.viewfinder"),
            style: .plain,
            target: self,
            action: #selector(openQRScanner)
        )
        navigationItem.leftBarButtonItem?.accessibilityLabel = "Сканировать QR"

        navigationItem.rightBarButtonItems = [
            UIBarButtonItem(
                image: UIImage(systemName: "arrow.clockwise"),
                style: .plain,
                target: self,
                action: #selector(refreshPage)
            ),
            UIBarButtonItem(
                image: UIImage(systemName: "globe"),
                style: .plain,
                target: self,
                action: #selector(confirmDomainChange)
            ),
        ]
        navigationItem.rightBarButtonItems?.first?.accessibilityLabel = "Обновить"
        navigationItem.rightBarButtonItems?.last?.accessibilityLabel = "Сменить домен"

        load(initialURL)
    }

    func load(_ url: URL) {
        webView.load(URLRequest(url: url))
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation?) {
        synchronizeCookies {
            NotificationSyncService.shared.scheduleBackgroundRefresh()
            NotificationSyncService.shared.sync(baseURL: self.baseURL)
        }
    }

    func webView(
        _ webView: WKWebView,
        decidePolicyFor navigationAction: WKNavigationAction,
        decisionHandler: @escaping (WKNavigationActionPolicy) -> Void
    ) {
        guard let url = navigationAction.request.url else {
            decisionHandler(.cancel)
            return
        }

        if navigationAction.targetFrame == nil, ["http", "https"].contains(url.scheme?.lowercased() ?? "") {
            webView.load(navigationAction.request)
            decisionHandler(.cancel)
            return
        }

        if let scheme = url.scheme?.lowercased(), !["http", "https"].contains(scheme) {
            UIApplication.shared.open(url)
            decisionHandler(.cancel)
            return
        }

        decisionHandler(.allow)
    }

    @available(iOS 15.0, *)
    func webView(
        _ webView: WKWebView,
        requestMediaCapturePermissionFor origin: WKSecurityOrigin,
        initiatedByFrame frame: WKFrameInfo,
        type: WKMediaCaptureType,
        decisionHandler: @escaping (WKPermissionDecision) -> Void
    ) {
        let configuredHost = baseURL.host?.lowercased()
        let requestHost = origin.host.lowercased()

        decisionHandler(requestHost == configuredHost ? .grant : .deny)
    }

    func webView(
        _ webView: WKWebView,
        createWebViewWith configuration: WKWebViewConfiguration,
        for navigationAction: WKNavigationAction,
        windowFeatures: WKWindowFeatures
    ) -> WKWebView? {
        if let url = navigationAction.request.url {
            webView.load(URLRequest(url: url))
        }

        return nil
    }

    private func synchronizeCookies(completion: @escaping () -> Void) {
        webView.configuration.websiteDataStore.httpCookieStore.getAllCookies { cookies in
            cookies.forEach(HTTPCookieStorage.shared.setCookie)
            DispatchQueue.main.async(execute: completion)
        }
    }

    @objc private func openQRScanner() {
        let scanner = QRScannerViewController { [weak self] qrCode in
            self?.openQuickScanForm(qrCode: qrCode)
        }
        present(scanner, animated: true)
    }

    private func openQuickScanForm(qrCode: String) {
        guard var components = URLComponents(
            url: baseURL.appendingPathComponent("qr"),
            resolvingAgainstBaseURL: false
        ) else {
            return
        }

        components.queryItems = [URLQueryItem(name: "qr_code", value: qrCode)]

        if let url = components.url {
            load(url)
        }
    }

    @objc private func refreshPage() {
        webView.reload()
    }

    @objc private func confirmDomainChange() {
        let alert = UIAlertController(
            title: "Сменить домен?",
            message: "Текущая сессия будет удалена, после чего потребуется войти заново.",
            preferredStyle: .alert
        )
        alert.addAction(UIAlertAction(title: "Отмена", style: .cancel))
        alert.addAction(UIAlertAction(title: "Сменить", style: .destructive) { [weak self] _ in
            self?.onChangeDomain()
        })
        present(alert, animated: true)
    }
}
