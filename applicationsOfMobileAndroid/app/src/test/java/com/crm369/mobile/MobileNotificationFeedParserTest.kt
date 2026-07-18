package com.appswebnetkz.crm369

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class MobileNotificationFeedParserTest {
    @Test
    fun `it parses notification feed entries from backend payload`() {
        val feed = MobileNotificationFeedParser.parse(
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
            """.trimIndent(),
        )

        assertEquals(42, feed.userId)
        assertEquals(1, feed.notificationsUnreadCount)
        assertEquals(3, feed.chatUnreadCount)
        assertEquals(2, feed.entries.size)
        assertEquals("notification:abc", feed.entries[0].key)
        assertEquals("/settings/security", feed.entries[0].actionPath)
        assertEquals("chat:12:77", feed.entries[1].key)
        assertEquals("/chats?conversation=12", feed.entries[1].actionPath)
    }

    @Test
    fun `it keeps empty action path as null`() {
        val feed = MobileNotificationFeedParser.parse(
            """
            {
              "data": {
                "notifications": [
                  {
                    "key": "notification:abc",
                    "title": "Plain notice",
                    "message": "Message only",
                    "action_path": ""
                  }
                ],
                "chats": []
              },
              "meta": {
                "user_id": 7,
                "notifications_unread_count": 1,
                "chat_unread_count": 0
              }
            }
            """.trimIndent(),
        )

        assertNull(feed.entries.first().actionPath)
    }
}
