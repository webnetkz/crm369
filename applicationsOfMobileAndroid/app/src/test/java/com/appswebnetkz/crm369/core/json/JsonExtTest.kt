package com.appswebnetkz.crm369.core.json

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertSame
import org.junit.Assert.assertTrue
import org.junit.Test

class JsonExtTest {

    private fun json(text: String): JsonObject = parseJsonObject(text)

    @Test
    fun parseHandlesEmptyInvalidAndArrayBodies() {
        assertTrue(parseJsonObject("").isEmpty())
        assertTrue(parseJsonObject("not json").isEmpty())
        assertTrue(parseJsonObject("42").isEmpty())
        assertEquals(2, parseJsonObject("[1, 2]").arr("data")?.size)
    }

    @Test
    fun stringReaders() {
        val value = json("""{"a":"x","n":5,"b":true,"z":null,"blank":"  "}""")
        assertEquals("x", value.str("a"))
        assertEquals("5", value.str("n"))
        assertEquals("true", value.str("b"))
        assertNull(value.str("z"))
        assertNull(value.str("missing"))
        assertEquals("  ", value.str("blank"))
        assertNull(value.text("blank"))
        assertEquals("fallback", value.strOr("blank", "fallback"))
        assertEquals("x", value.strOr("a", "fallback"))
        assertNull((null as JsonObject?).str("a"))
    }

    @Test
    fun numberReaders() {
        val value = json("""{"s":"12","d":12.7,"i":3,"bad":"abc","comma":"3,5","obj":{}}""")
        assertEquals(12L, value.long("s"))
        assertEquals(12L, value.long("d"))
        assertEquals(3, value.int("i"))
        assertNull(value.long("bad"))
        assertNull(value.long("obj"))
        assertEquals(3.5, value.double("comma")!!, 0.0001)
        assertEquals(12.7, value.double("d")!!, 0.0001)
    }

    @Test
    fun booleanReaders() {
        val value = json("""{"t":true,"one":1,"yes":"yes","zero":"0","weird":"maybe","f":false}""")
        assertEquals(true, value.bool("t"))
        assertEquals(true, value.bool("one"))
        assertEquals(true, value.bool("yes"))
        assertEquals(false, value.bool("zero"))
        assertEquals(false, value.bool("f"))
        assertNull(value.bool("weird"))
        assertTrue(value.boolOr("missing", true))
        assertFalse(value.boolOr("missing"))
    }

    @Test
    fun nestedReaders() {
        val value = json("""{"data":{"user":{"name":"John Doe","roles":["admin",1,null]}}}""")
        assertEquals("John Doe", value.pathStr("data", "user", "name"))
        assertNull(value.path("data", "missing", "name"))
        assertEquals(listOf("admin", "1"), value.pathObj("data", "user").strings("roles"))
        assertEquals("John Doe", value.dataObject().obj("user").str("name"))
    }

    @Test
    fun collectionReaders() {
        val value = json("""{"data":[{"id":1},2,{"id":3}],"ids":[1,"2",null,"x"]}""")
        assertEquals(listOf(1L, 3L), value.dataObjects().mapNotNull { it.long("id") })
        assertEquals(listOf(1L, 2L), value.longs("ids"))
        assertTrue(value.objects("missing").isEmpty())
    }

    @Test
    fun dataObjectFallsBackToRoot() {
        val root = json("""{"id":2}""")
        assertSame(root, root.dataObject())
        assertEquals(1L, json("""{"data":{"id":1}}""").dataObject().long("id"))
    }

    @Test
    fun builders() {
        val value = jsonObjectOf(
            "a" to 1,
            "b" to listOf("x", null),
            "c" to mapOf("d" to true),
            "e" to null,
        )
        assertEquals("""{"a":1,"b":["x",null],"c":{"d":true},"e":null}""", value.toString())

        val built = buildJsonObject {
            putValue("name", "John Doe")
            putValue("count", 3)
            putValue("active", null as Boolean?)
            putStrings("tags", listOf("a", "b"))
            putLongs("ids", listOf(1L, 2L))
        }
        assertEquals("""{"name":"John Doe","count":3,"active":null,"tags":["a","b"],"ids":[1,2]}""", built.toString())
        assertEquals(mapOf("name" to "John Doe", "count" to "3", "active" to null), built.toStringMap().filterKeys { it in setOf("name", "count", "active") })
    }

    @Test
    fun multipartFieldsUseLaravelBracketNotation() {
        val body = json(
            """
            {
              "name": "John Doe",
              "company_requisites": {"iin": "000000000000", "bank_name": null},
              "ids": [1, 2],
              "is_blacklisted": true,
              "archived": false,
              "literal": "true"
            }
            """.trimIndent(),
        )
        assertEquals(
            mapOf(
                "name" to "John Doe",
                "company_requisites[iin]" to "000000000000",
                "company_requisites[bank_name]" to "",
                "ids[0]" to "1",
                "ids[1]" to "2",
                "is_blacklisted" to "1",
                "archived" to "0",
                "literal" to "true",
            ),
            body.toMultipartFields(),
        )
    }
}
