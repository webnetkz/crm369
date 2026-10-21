package com.appswebnetkz.crm369.core.links

import org.junit.Assert.assertEquals
import org.junit.Test

class DeepLinkRouterTest {

    private val portal = "https://crm.company.kz"

    private fun app(route: String) = DeepLinkTarget.App(route)

    private fun web(path: String) = DeepLinkTarget.Web(path)

    private fun resolve(value: String) = DeepLinkRouter.resolve(value, portal)

    @Test
    fun emptyAndDashboardOpenMain() {
        assertEquals(app("main"), resolve(""))
        assertEquals(app("main"), resolve("/"))
        assertEquals(app("main"), resolve("/dashboard"))
    }

    @Test
    fun notificationsAndChats() {
        assertEquals(app("notifications"), resolve("/notifications"))
        assertEquals(app("chats"), resolve("/chats"))
        assertEquals(app("chats/15"), resolve("/chats?conversation=15"))
        assertEquals(web("/chats/settings"), resolve("/chats/settings"))
    }

    @Test
    fun tasksAndProjects() {
        assertEquals(app("tasks"), resolve("/tasks"))
        assertEquals(app("tasks/42"), resolve("/tasks/42"))
        assertEquals(app("tasks/7"), resolve("/projects/tasks/7"))
        assertEquals(app("tasks/9"), resolve("/projects/3/tasks/9"))
        assertEquals(app("tasks/11"), resolve("/projects?task=11"))
        assertEquals(app("projects/3"), resolve("/projects/3"))
        assertEquals(app("projects"), resolve("/projects"))
        assertEquals(web("/projects/3/settings"), resolve("/projects/3/settings"))
    }

    @Test
    fun crmModules() {
        assertEquals(app("funnels/2"), resolve("/funnels/2"))
        assertEquals(app("contacts/5"), resolve("/contacts/5"))
        assertEquals(app("contacts/5"), resolve("contacts/5"))
        assertEquals(app("contacts/5"), resolve("/contacts/5#comments"))
        assertEquals(app("contacts"), resolve("/contacts?search=john"))
        assertEquals(web("/contacts/export"), resolve("/contacts/export"))
        assertEquals(app("calendar"), resolve("/calendar?date=2026-10-21"))
        assertEquals(app("company"), resolve("/company-structure"))
    }

    @Test
    fun contentModules() {
        assertEquals(app("news"), resolve("/news"))
        assertEquals(app("news/first-post"), resolve("/news/first-post"))
        assertEquals(
            app("news/%D0%BD%D0%BE%D0%B2%D0%BE%D1%81%D1%82%D1%8C"),
            resolve("/news/%D0%BD%D0%BE%D0%B2%D0%BE%D1%81%D1%82%D1%8C"),
        )
        assertEquals(app("knowledge"), resolve("/knowledge-bases"))
        assertEquals(app("knowledge/4"), resolve("/knowledge-bases/4"))
        assertEquals(app("knowledge/4/articles/8"), resolve("/knowledge-bases/4/articles/8"))
        assertEquals(app("files"), resolve("/files"))
        assertEquals(app("files?directoryId=12"), resolve("/files?directory=12"))
    }

    @Test
    fun operationsModules() {
        assertEquals(app("edo/10"), resolve("/edo/10"))
        assertEquals(app("procurement"), resolve("/procurement"))
        assertEquals(app("warehouses/6"), resolve("/warehouses/6"))
        assertEquals(app("equipment/3"), resolve("/equipment/3"))
        assertEquals(app("tsd"), resolve("/tsd"))
        assertEquals(app("qr"), resolve("/qr"))
        assertEquals(app("directories/3"), resolve("/directories/3"))
        assertEquals(app("forms/2"), resolve("/forms/2"))
        assertEquals(app("production"), resolve("/production"))
        assertEquals(app("production/machines"), resolve("/production/machines"))
        assertEquals(app("conferences/3"), resolve("/conferences/3"))
    }

    @Test
    fun publicAndUnknownPagesOpenInWebView() {
        assertEquals(web("/edo/public/abc"), resolve("/edo/public/abc"))
        assertEquals(web("/forms/public/xyz?embed=1"), resolve("/forms/public/xyz?embed=1"))
        assertEquals(web("/conferences/public/room"), resolve("/conferences/public/room"))
        assertEquals(web("/settings/users"), resolve("/settings/users"))
    }

    @Test
    fun absoluteUrlsOfThePortalAreRouted() {
        assertEquals(app("contacts/5"), resolve("https://crm.company.kz/contacts/5"))
        assertEquals(app("chats/3"), resolve("https://CRM.company.kz/chats?conversation=3"))
        assertEquals(web("/settings/users"), resolve("https://crm.company.kz/settings/users"))
    }

    @Test
    fun foreignLinksAreExternal() {
        assertEquals(DeepLinkTarget.External("https://example.com/page"), resolve("https://example.com/page"))
        assertEquals(DeepLinkTarget.External("mailto:john.doe@example.com"), resolve("mailto:john.doe@example.com"))
        assertEquals(DeepLinkTarget.External("tel:+77000000000"), resolve("tel:+77000000000"))
        assertEquals(
            DeepLinkTarget.External("https://crm.company.kz/contacts/5"),
            DeepLinkRouter.resolve("https://crm.company.kz/contacts/5", portalBaseUrl = null),
        )
    }

    @Test
    fun routeArgsRoundTrip() {
        val value = "a b+c/д?&=%"
        val encoded = RouteArgs.encode(value)
        assertEquals("a%20b%2Bc%2F%D0%B4%3F%26%3D%25", encoded)
        assertEquals(value, RouteArgs.decode(encoded))
        assertEquals("keep-._~AZ09", RouteArgs.encode("keep-._~AZ09"))
        assertEquals("100%", RouteArgs.decode("100%"))
        assertEquals("%zz", RouteArgs.decode("%zz"))
    }
}
