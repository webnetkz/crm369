package com.appswebnetkz.crm369.core.network

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class FileNamesTest {

    @Test
    fun extendedNameWinsOverAsciiFallback() {
        val header = "attachment; filename=\"Otcet 2026.txt\"; filename*=utf-8''%D0%9E%D1%82%D1%87%D1%91%D1%82%202026.txt"
        assertEquals("Отчёт 2026.txt", FileNames.fromContentDisposition(header))
    }

    @Test
    fun plainNames() {
        assertEquals("supply-agreement.pdf", FileNames.fromContentDisposition("attachment; filename=supply-agreement.pdf"))
        assertEquals("a b.pdf", FileNames.fromContentDisposition("inline; filename=\"a b.pdf\""))
        assertEquals("c+d.txt", FileNames.fromContentDisposition("attachment; filename*=UTF-8''c+d.txt"))
        assertNull(FileNames.fromContentDisposition("attachment"))
        assertNull(FileNames.fromContentDisposition(null))
    }

    @Test
    fun sanitizeRemovesForbiddenCharacters() {
        assertEquals("a_b_c.txt", FileNames.sanitize("a/b\\c.txt"))
        assertEquals("report_1_.pdf", FileNames.sanitize("report:1?.pdf"))
        assertEquals("file", FileNames.sanitize(" .. "))
        assertEquals(150, FileNames.sanitize("x".repeat(400)).length)
    }
}
