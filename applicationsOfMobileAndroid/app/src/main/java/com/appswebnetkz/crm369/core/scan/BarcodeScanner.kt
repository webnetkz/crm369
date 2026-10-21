package com.appswebnetkz.crm369.core.scan

import android.content.Context
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.codescanner.GmsBarcodeScannerOptions
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlinx.coroutines.suspendCancellableCoroutine

/** The scanner UI could not be started (e.g. Google Play services are missing or outdated). */
class BarcodeScannerUnavailableException(cause: Throwable) : Exception(cause.message, cause)

/**
 * Opens the Google code scanner (camera UI provided by Google Play services, no camera
 * permission needed) and returns the scanned value, or null when the user cancelled.
 *
 * Hardware scanners of handheld terminals (TSD) that work as a keyboard ("keyboard wedge")
 * can be used with a regular text field instead.
 */
suspend fun scanBarcode(context: Context): String? = suspendCancellableCoroutine { continuation ->
    val options = GmsBarcodeScannerOptions.Builder()
        .setBarcodeFormats(Barcode.FORMAT_ALL_FORMATS)
        .build()
    val scanner = GmsBarcodeScanning.getClient(context, options)
    scanner.startScan()
        .addOnSuccessListener { barcode ->
            if (continuation.isActive) continuation.resume(barcode.rawValue ?: barcode.displayValue)
        }
        .addOnCanceledListener {
            if (continuation.isActive) continuation.resume(null)
        }
        .addOnFailureListener { exception ->
            if (continuation.isActive) continuation.resumeWithException(BarcodeScannerUnavailableException(exception))
        }
}
