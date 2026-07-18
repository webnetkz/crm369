import Foundation

struct MobileNotificationFeed: Equatable {
    let userID: Int
    let notificationsUnreadCount: Int
    let chatUnreadCount: Int
    let entries: [MobileNotificationEntry]
}

struct MobileNotificationEntry: Equatable, Decodable {
    let key: String
    let title: String
    let message: String
    let actionPath: String?
    let createdAt: String?

    enum CodingKeys: String, CodingKey {
        case key
        case title
        case message
        case actionPath = "action_path"
        case createdAt = "created_at"
    }
}

enum MobileNotificationFeedParser {
    static func parse(_ data: Data) throws -> MobileNotificationFeed {
        let payload = try JSONDecoder().decode(Payload.self, from: data)

        return MobileNotificationFeed(
            userID: payload.meta.userID,
            notificationsUnreadCount: payload.meta.notificationsUnreadCount,
            chatUnreadCount: payload.meta.chatUnreadCount,
            entries: payload.data.notifications + payload.data.chats
        )
    }

    private struct Payload: Decodable {
        let data: FeedData
        let meta: Metadata
    }

    private struct FeedData: Decodable {
        let notifications: [MobileNotificationEntry]
        let chats: [MobileNotificationEntry]
    }

    private struct Metadata: Decodable {
        let userID: Int
        let notificationsUnreadCount: Int
        let chatUnreadCount: Int

        enum CodingKeys: String, CodingKey {
            case userID = "user_id"
            case notificationsUnreadCount = "notifications_unread_count"
            case chatUnreadCount = "chat_unread_count"
        }
    }
}
