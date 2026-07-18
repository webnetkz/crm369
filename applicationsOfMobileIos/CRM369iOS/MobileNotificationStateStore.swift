import Foundation

final class MobileNotificationStateStore {
    private let defaults: UserDefaults
    private let identityKey = "crm369.notifications.identity"
    private let deliveredKeysKey = "crm369.notifications.delivered_keys"
    private let maximumTrackedKeys = 200

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
    }

    func newEntries(baseURL: URL, feed: MobileNotificationFeed) -> [MobileNotificationEntry] {
        guard feed.userID > 0 else {
            return []
        }

        let feedIdentity = identity(baseURL: baseURL, userID: feed.userID)
        let deliveredKeys = defaults.string(forKey: identityKey) == feedIdentity
            ? Set(defaults.stringArray(forKey: deliveredKeysKey) ?? [])
            : []

        return feed.entries.filter { !deliveredKeys.contains($0.key) }
    }

    func markDelivered(baseURL: URL, feed: MobileNotificationFeed, keys: [String]) {
        guard feed.userID > 0, !keys.isEmpty else {
            return
        }

        let feedIdentity = identity(baseURL: baseURL, userID: feed.userID)
        let existingKeys = defaults.string(forKey: identityKey) == feedIdentity
            ? defaults.stringArray(forKey: deliveredKeysKey) ?? []
            : []
        let mergedKeys = Array(NSOrderedSet(array: keys + existingKeys))
            .compactMap { $0 as? String }
            .prefix(maximumTrackedKeys)

        defaults.set(feedIdentity, forKey: identityKey)
        defaults.set(Array(mergedKeys), forKey: deliveredKeysKey)
    }

    private func identity(baseURL: URL, userID: Int) -> String {
        "\(baseURL.absoluteString)|\(userID)"
    }
}
