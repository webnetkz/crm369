import XCTest
@testable import CRM369iOS

final class MobileNotificationFeedParserTests: XCTestCase {
    func testParsesSystemAndChatNotifications() throws {
        let data = Data(
            """
            {
              "data": {
                "notifications": [
                  {
                    "key": "notification:abc",
                    "title": "Security notice",
                    "message": "Review your security settings.",
                    "action_path": "/settings/security",
                    "created_at": "2026-07-07T09:00:00Z"
                  }
                ],
                "chats": [
                  {
                    "key": "chat:12:77",
                    "title": "Jane Doe",
                    "message": "Unread mobile chat message",
                    "action_path": "/chats?conversation=12",
                    "created_at": "2026-07-07T09:05:00Z"
                  }
                ]
              },
              "meta": {
                "user_id": 42,
                "notifications_unread_count": 1,
                "chat_unread_count": 3
              }
            }
            """.utf8
        )

        let feed = try MobileNotificationFeedParser.parse(data)

        XCTAssertEqual(feed.userID, 42)
        XCTAssertEqual(feed.notificationsUnreadCount, 1)
        XCTAssertEqual(feed.chatUnreadCount, 3)
        XCTAssertEqual(feed.entries.map(\.key), ["notification:abc", "chat:12:77"])
        XCTAssertEqual(feed.entries.last?.actionPath, "/chats?conversation=12")
    }
}
