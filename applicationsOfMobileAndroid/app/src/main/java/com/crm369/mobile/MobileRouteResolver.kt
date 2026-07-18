package com.appswebnetkz.crm369

object MobileRouteResolver {
    fun qrScannerUrl(baseUrl: String): String {
        return "${baseUrl.trimEnd('/')}/qr"
    }
}
