package com.appswebnetkz.crm369

object LocalDevelopmentHost {
    private val privateNetworkPatterns = listOf(
        Regex("""^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$"""),
        Regex("""^192\.168\.\d{1,3}\.\d{1,3}$"""),
        Regex("""^172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}$"""),
    )

    fun prefersHttp(host: String): Boolean {
        return isLoopbackHost(host) || isPrivateIpv4(host)
    }

    fun isLocalDevelopmentHost(host: String): Boolean {
        return isLoopbackHost(host)
            || isPrivateIpv4(host)
            || host.endsWith(".test")
            || host.endsWith(".local")
    }

    fun resolveForDevice(host: String, runningOnEmulator: Boolean): String {
        if (runningOnEmulator && isLoopbackHost(host)) {
            return EMULATOR_HOST_LOOPBACK_ALIAS
        }

        return host
    }

    private fun isLoopbackHost(host: String): Boolean {
        return host == "localhost"
            || host == "127.0.0.1"
            || host == "::1"
    }

    private fun isPrivateIpv4(host: String): Boolean {
        return privateNetworkPatterns.any { pattern -> pattern.matches(host) }
    }

    private const val EMULATOR_HOST_LOOPBACK_ALIAS = "10.0.2.2"
}
