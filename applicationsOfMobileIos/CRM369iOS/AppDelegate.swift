import BackgroundTasks
import UIKit
import UserNotifications

@main
final class AppDelegate: UIResponder, UIApplicationDelegate, UNUserNotificationCenterDelegate {
    private let domainPreferences = DomainPreferences()
    private var router: AppRouter?
    var window: UIWindow?

    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil
    ) -> Bool {
        let window = UIWindow(frame: UIScreen.main.bounds)
        let router = AppRouter(window: window, domainPreferences: domainPreferences)

        self.window = window
        self.router = router

        UNUserNotificationCenter.current().delegate = self
        NotificationSyncService.shared.requestAuthorization()

        BGTaskScheduler.shared.register(
            forTaskWithIdentifier: NotificationSyncService.backgroundTaskIdentifier,
            using: nil
        ) { [weak self] task in
            guard let refreshTask = task as? BGAppRefreshTask else {
                task.setTaskCompleted(success: false)
                return
            }

            NotificationSyncService.shared.handle(
                backgroundTask: refreshTask,
                baseURL: self?.domainPreferences.baseURL
            )
        }

        router.start()

        return true
    }

    func applicationDidEnterBackground(_ application: UIApplication) {
        NotificationSyncService.shared.scheduleBackgroundRefresh()
    }

    func application(
        _ application: UIApplication,
        performFetchWithCompletionHandler completionHandler: @escaping (UIBackgroundFetchResult) -> Void
    ) {
        guard let baseURL = domainPreferences.baseURL else {
            completionHandler(.noData)
            return
        }

        NotificationSyncService.shared.sync(baseURL: baseURL) { isSuccessful in
            completionHandler(isSuccessful ? .newData : .failed)
        }
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        completionHandler([.banner, .badge, .sound])
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        let userInfo = response.notification.request.content.userInfo
        router?.open(
            actionPath: userInfo["action_path"] as? String,
            baseURLString: userInfo["base_url"] as? String
        )
        completionHandler()
    }
}
