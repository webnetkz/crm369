package com.appswebnetkz.crm369

import android.os.Build

object DeviceEnvironment {
    fun isEmulator(): Boolean {
        return Build.FINGERPRINT.startsWith("generic")
            || Build.FINGERPRINT.lowercase().contains("emulator")
            || Build.MODEL.contains("Emulator")
            || Build.MODEL.contains("Android SDK built for")
            || Build.MANUFACTURER.contains("Genymotion")
            || Build.HARDWARE == "goldfish"
            || Build.HARDWARE == "ranchu"
            || Build.PRODUCT.contains("sdk")
    }
}
