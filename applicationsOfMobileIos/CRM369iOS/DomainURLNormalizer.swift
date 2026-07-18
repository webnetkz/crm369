import Foundation

enum DomainURLNormalizer {
    static func normalize(_ rawValue: String) -> URL? {
        let trimmedValue = rawValue.trimmingCharacters(in: .whitespacesAndNewlines)

        guard !trimmedValue.isEmpty else {
            return nil
        }

        let valueWithScheme: String

        if trimmedValue.contains("://") {
            valueWithScheme = trimmedValue
        } else {
            let authority = trimmedValue
                .split(separator: "/", maxSplits: 1)
                .first
                .map(String.init) ?? ""
            let provisionalHost = authority
                .split(separator: ":", maxSplits: 1)
                .first
                .map { String($0).lowercased() }
            let scheme = prefersHTTP(host: provisionalHost) ? "http" : "https"
            valueWithScheme = "\(scheme)://\(trimmedValue)"
        }

        guard var components = URLComponents(string: valueWithScheme),
              let scheme = components.scheme?.lowercased(),
              ["http", "https"].contains(scheme),
              let host = components.host?.lowercased(),
              !host.isEmpty,
              components.user == nil,
              components.password == nil else {
            return nil
        }

        components.scheme = scheme
        components.host = host
        components.path = ""
        components.query = nil
        components.fragment = nil

        return components.url
    }

    private static func prefersHTTP(host: String?) -> Bool {
        guard let host else {
            return false
        }

        return host == "localhost"
            || host == "127.0.0.1"
            || host == "0.0.0.0"
            || host == "::1"
            || host.hasSuffix(".test")
    }
}
