package com.appswebnetkz.crm369

import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Test

class NativeListParserTest {
    @Test
    fun parsesNestedNativeModulePayload() {
        val response = JSONObject(
            """{"data":{"projects":[{"id":7,"name":"Запуск","description":"Мобильное приложение"}]}}""",
        )

        val items = NativeListParser.parse(response, NativeModule.Projects)

        assertEquals(1, items.size)
        assertEquals("7", items.single().id)
        assertEquals("Запуск", items.single().title)
        assertEquals("Мобильное приложение", items.single().subtitle)
    }

    @Test
    fun parsesChatConversationPayload() {
        val response = JSONObject(
            """{"conversations":[{"id":12,"title":"Общий чат","excerpt":"Привет","unreadCount":2}]}""",
        )

        val item = NativeListParser.parse(response, NativeModule.Chats).single()

        assertEquals("Общий чат", item.title)
        assertEquals("Привет", item.subtitle)
    }
}
