import BackgroundTasks
import Foundation
import UserNotifications

final class NotificationSyncService {
    static let shared = NotificationSyncService()
    static let backgroundTaskIdentifier = "com.appswebnetkz.crm369.notifications"

    private let queue = DispatchQueue(label: "com.appswebnetkz.crm369.notification-sync")
    private let stateStore = MobileNotificationStateStore()
    private var activeTask: URLSessionDataTask?

    private init() {}

    func requestAuthorization() {
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { _, _ in }
    }

    func scheduleBackgroundRefresh() {
        let request = BGAppRefreshTaskRequest(identifier: Self.backgroundTaskIdentifier)
        request.earliestBeginDate = Date(timeIntervalSinceNow: 15 * 60)

        do {
            try BGTaskScheduler.shared.submit(request)
        } catch {
            return
        }
    }

    func handle(backgroundTask: BGAppRefreshTask, baseURL: URL?) {
        scheduleBackgroundRefresh()

        backgroundTask.expirationHandler = { [weak self] in
            self?.activeTask?.cancel()
        }

        guard let baseURL else {
            backgroundTask.setTaskCompleted(success: true)
            return
        }

        sync(baseURL: baseURL) { isSuccessful in
            backgroundTask.setTaskCompleted(success: isSuccessful)
        }
    }

    func sync(baseURL: URL, completion: ((Bool) -> Void)? = nil) {
        queue.async { [weak self] in
            self?.performSync(baseURL: baseURL, completion: completion)
        }
    }

    private func performSync(baseURL: URL, completion: ((Bool) -> Void)?) {
        let feedURL = baseURL.appendingPathComponent("mobile/notifications/feed")
        var request = URLRequest(url: feedURL)
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("CRM369MobileApp iOS", forHTTPHeaderField: "User-Agent")

        let cookieHeaders = HTTPCookie.requestHeaderFields(
            with: HTTPCookieStorage.shared.cookies(for: baseURL) ?? []
        )
        cookieHeaders.forEach { request.setValue($0.value, forHTTPHeaderField: $0.key) }

        guard cookieHeaders["Cookie"] != nil else {
            complete(completion, success: true)
            return
        }

        activeTask = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            self?.queue.async {
                defer { self?.activeTask = nil }

                guard error == nil,
                      let response = response as? HTTPURLResponse else {
                    self?.complete(completion, success: false)
                    return
                }

                guard response.statusCode == 200, let data else {
                    self?.complete(completion, success: !(500...599).contains(response.statusCode))
                    return
                }

                guard let feed = try? MobileNotificationFeedParser.parse(data) else {
                    self?.complete(completion, success: true)
                    return
                }

                self?.deliverNewEntries(baseURL: baseURL, feed: feed, completion: completion)
            }
        }
        activeTask?.resume()
    }

    private func deliverNewEntries(
        baseURL: URL,
        feed: MobileNotificationFeed,
        completion: ((Bool) -> Void)?
    ) {
        let entries = stateStore.newEntries(baseURL: baseURL, feed: feed)

        guard !entries.isEmpty else {
            complete(completion, success: true)
            return
        }

        let group = DispatchGroup()
        let center = UNUserNotificationCenter.current()

        entries.forEach { entry in
            group.enter()

            let content = UNMutableNotificationContent()
            content.title = entry.title
            content.body = entry.message.isEmpty ? entry.title : entry.message
            content.sound = .default
            content.categoryIdentifier = entry.key.hasPrefix("chat:") ? "CRM369_CHAT" : "CRM369_GENERAL"
            content.userInfo = [
                "base_url": baseURL.absoluteString,
                "action_path": entry.actionPath ?? "",
            ]

            center.add(UNNotificationRequest(identifier: entry.key, content: content, trigger: nil)) { _ in
                group.leave()
            }
        }

        group.notify(queue: queue) { [weak self] in
            self?.stateStore.markDelivered(
                baseURL: baseURL,
                feed: feed,
                keys: entries.map(\.key)
            )
            self?.complete(completion, success: true)
        }
    }

    private func complete(_ completion: ((Bool) -> Void)?, success: Bool) {
        guard let completion else {
            return
        }

        DispatchQueue.main.async {
            completion(success)
        }
    }
}
