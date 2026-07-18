import UIKit
import WebKit

@MainActor
final class AppRouter {
    private let window: UIWindow
    private let domainPreferences: DomainPreferences
    private var webViewController: CRMWebViewController?

    init(window: UIWindow, domainPreferences: DomainPreferences) {
        self.window = window
        self.domainPreferences = domainPreferences
    }

    func start(initialURL: URL? = nil) {
        if let baseURL = domainPreferences.baseURL {
            showCRM(baseURL: baseURL, initialURL: initialURL ?? baseURL)
        } else {
            showDomainSetup()
        }

        window.makeKeyAndVisible()
    }

    func open(actionPath: String?, baseURLString: String?) {
        let baseURL = baseURLString.flatMap(URL.init(string:)) ?? domainPreferences.baseURL

        guard let baseURL else {
            showDomainSetup()
            return
        }

        let targetURL = actionPath.flatMap { URL(string: $0, relativeTo: baseURL)?.absoluteURL } ?? baseURL

        if let webViewController, webViewController.baseURL == baseURL {
            webViewController.load(targetURL)
        } else {
            domainPreferences.save(baseURL: baseURL)
            showCRM(baseURL: baseURL, initialURL: targetURL)
        }
    }

    func showDomainSetup() {
        webViewController = nil

        let controller = DomainSetupViewController(
            savedValue: domainPreferences.baseURL?.absoluteString ?? "",
            onSubmit: { [weak self] baseURL in
                self?.domainPreferences.save(baseURL: baseURL)
                self?.showCRM(baseURL: baseURL, initialURL: baseURL)
            }
        )

        window.rootViewController = controller
    }

    private func showCRM(baseURL: URL, initialURL: URL) {
        let controller = CRMWebViewController(
            baseURL: baseURL,
            initialURL: initialURL,
            onChangeDomain: { [weak self] in
                self?.domainPreferences.clear()
                self?.clearWebSessionAndShowDomainSetup()
            }
        )

        webViewController = controller
        window.rootViewController = UINavigationController(rootViewController: controller)
    }

    private func clearWebSessionAndShowDomainSetup() {
        let dataStore = WKWebsiteDataStore.default()
        dataStore.fetchDataRecords(ofTypes: WKWebsiteDataStore.allWebsiteDataTypes()) { [weak self] records in
            dataStore.removeData(ofTypes: WKWebsiteDataStore.allWebsiteDataTypes(), for: records) {
                Task { @MainActor [weak self] in
                    HTTPCookieStorage.shared.removeCookies(since: .distantPast)
                    self?.showDomainSetup()
                }
            }
        }
    }
}
