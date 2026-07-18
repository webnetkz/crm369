import Foundation

final class DomainPreferences {
    private let defaults: UserDefaults
    private let baseURLKey = "crm369.base_url"

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
    }

    var baseURL: URL? {
        guard let value = defaults.string(forKey: baseURLKey) else {
            return nil
        }

        return URL(string: value)
    }

    func save(baseURL: URL) {
        defaults.set(baseURL.absoluteString, forKey: baseURLKey)
    }

    func clear() {
        defaults.removeObject(forKey: baseURLKey)
    }
}
