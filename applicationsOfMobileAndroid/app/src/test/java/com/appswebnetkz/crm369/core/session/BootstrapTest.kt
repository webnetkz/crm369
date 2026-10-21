package com.appswebnetkz.crm369.core.session

import com.appswebnetkz.crm369.core.json.dataObject
import com.appswebnetkz.crm369.core.json.parseJsonObject
import com.appswebnetkz.crm369.core.portal.FirebaseClientOptions
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class BootstrapTest {

    private val response = """
        {
          "data": {
            "user": {
              "id": 7, "name": "John", "last_name": "Doe", "middle_name": null,
              "email": "john.doe@example.com", "phone": "+77000000000", "position": "Manager",
              "avatar": "https://crm.company.kz/storage/avatars/7.png", "language": "ru",
              "is_super_admin": false, "group": {"id": 2, "name": "sales", "display_name": "Sales"}
            },
            "abilities": {"isSuperAdmin": false, "canAccessChats": true, "canAccessContacts": true, "canAccessFunnels": false},
            "portal": {
              "name": "Company CRM", "url": "https://crm.company.kz",
              "logo_url": "https://crm.company.kz/storage/logo.png", "default_language": "ru",
              "enabled_modules": ["chats", "contacts", "one-c"]
            },
            "menu": {
              "hiddenItems": ["news"],
              "customItems": [
                {"id": 3, "title": "Wiki", "icon": "book", "url": "https://wiki.example.com", "opensInNewTab": true},
                {"id": 4, "title": "Broken", "icon": null, "url": null, "opensInNewTab": false}
              ],
              "knowledgeBases": [{"id": 1, "title": "Handbook"}],
              "order": ["contacts", "chats"]
            },
            "counters": {"notifications": 5, "chats": 2},
            "push": {
              "enabled": true, "provider": "fcm",
              "firebase": {"project_id": "crm-demo", "application_id": "1:123:android:abc", "api_key": "public-client-key", "sender_id": "123"}
            },
            "server_time": "2026-10-21T08:00:00.000000Z"
          }
        }
    """.trimIndent()

    @Test
    fun parsesUserPortalCountersAndPush() {
        val bootstrap = Bootstrap.parse("account-1", parseJsonObject(response).dataObject())
        assertEquals("account-1", bootstrap.accountId)
        assertEquals(7L, bootstrap.user.id)
        assertEquals("John Doe", bootstrap.user.fullName)
        assertEquals("Sales", bootstrap.user.groupName)
        assertEquals("Company CRM", bootstrap.portalName)
        assertEquals("https://crm.company.kz/storage/logo.png", bootstrap.portalLogoUrl)
        assertEquals(5, bootstrap.unreadNotifications)
        assertEquals(2, bootstrap.unreadChats)
        assertEquals(FirebaseClientOptions("crm-demo", "1:123:android:abc", "public-client-key", "123"), bootstrap.push)
    }

    @Test
    fun abilitiesAndModules() {
        val bootstrap = Bootstrap.parse("account-1", parseJsonObject(response).dataObject())
        assertTrue(bootstrap.can("canAccessChats"))
        assertFalse(bootstrap.can("canAccessFunnels"))
        assertFalse(bootstrap.can("canAccessUnknown"))
        assertTrue(bootstrap.can(null))
        assertTrue(bootstrap.canAny("canAccessFunnels", "canAccessContacts"))
        assertFalse(bootstrap.isSuperAdmin)
        assertTrue(bootstrap.isModuleEnabled("one-c"))
        assertFalse(bootstrap.isModuleEnabled("edo"))
    }

    @Test
    fun personalMenu() {
        val menu = Bootstrap.parse("account-1", parseJsonObject(response).dataObject()).menu
        assertFalse(menu.isVisible("news"))
        assertTrue(menu.isVisible("contacts"))
        assertEquals(listOf("Wiki"), menu.customItems.map { it.title })
        assertTrue(menu.customItems.single().opensInNewTab)
        assertEquals(listOf(1L), menu.knowledgeBases.map { it.id })
        assertEquals(listOf("contacts", "chats"), menu.order)
    }

    @Test
    fun missingSectionsFallBackToDefaults() {
        val bootstrap = Bootstrap.parse("account-2", parseJsonObject("""{"data":{"user":{"id":1,"email":"john.doe@example.com"}}}""").dataObject())
        assertEquals("CRM369", bootstrap.portalName)
        assertEquals("john.doe@example.com", bootstrap.user.fullName)
        assertEquals(0, bootstrap.unreadNotifications)
        assertNull(bootstrap.push)
        assertEquals(MenuConfig.Empty, bootstrap.menu)
        assertFalse(bootstrap.can("canAccessChats"))
    }

    @Test
    fun accountJsonRoundTrip() {
        val account = Account(
            id = Account.idFor("https://crm.company.kz", 7),
            baseUrl = "https://crm.company.kz",
            portalName = "Company CRM",
            logoUrl = null,
            userId = 7,
            userName = "John Doe",
            email = "john.doe@example.com",
            avatarUrl = "https://crm.company.kz/storage/avatars/7.png",
        )
        assertEquals("https://crm.company.kz#7", account.id)
        assertEquals("crm.company.kz", account.host)
        assertEquals(account, Account.fromJson(account.toJson()))
    }

    @Test
    fun firebaseOptionsRequireAllFields() {
        val options = FirebaseClientOptions("p", "a", "k", "s")
        assertEquals(options, FirebaseClientOptions.fromJson(options.toJson()))
        assertNull(FirebaseClientOptions.fromJson(parseJsonObject("""{"project_id":"p","application_id":"a","api_key":""}""")))
        assertNull(FirebaseClientOptions.fromJson(null))
    }
}
