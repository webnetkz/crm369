package com.appswebnetkz.crm369.feature.procurement

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/**
 * Routes of the procurement module. Every screen reads `GET procurement` (one call returns the
 * whole module state), so detail and form screens only need the record ids.
 */
object ProcurementRoutes {
    const val ROOT = "procurement"
    const val NEW_REQUEST = "procurement/requests/new"
    const val REQUEST = "procurement/requests/{requestId}"
    const val NEW_QUOTATION = "procurement/requests/{requestId}/items/{itemId}/quotation"
    const val NEW_ORDER = "procurement/requests/{requestId}/order"
    const val ORDER = "procurement/orders/{orderId}"
    const val NEW_RECEIPT = "procurement/orders/{orderId}/receipt"
    const val RECEIPT = "procurement/receipts/{receiptId}"
    const val NEW_RETURN = "procurement/receipts/{receiptId}/return"
    const val RETURN = "procurement/returns/{returnId}"
    const val NEW_SUPPLIER = "procurement/suppliers/new"
    const val SUPPLIER = "procurement/suppliers/{supplierId}"
    const val EDIT_SUPPLIER = "procurement/suppliers/{supplierId}/edit"

    fun purchaseRequest(requestId: Long): String = "procurement/requests/$requestId"
    fun newQuotation(requestId: Long, itemId: Long): String = "procurement/requests/$requestId/items/$itemId/quotation"
    fun newOrder(requestId: Long): String = "procurement/requests/$requestId/order"
    fun order(orderId: Long): String = "procurement/orders/$orderId"
    fun newReceipt(orderId: Long): String = "procurement/orders/$orderId/receipt"
    fun receipt(receiptId: Long): String = "procurement/receipts/$receiptId"
    fun newReturn(receiptId: Long): String = "procurement/receipts/$receiptId/return"
    fun purchaseReturn(returnId: Long): String = "procurement/returns/$returnId"
    fun supplier(supplierId: Long): String = "procurement/suppliers/$supplierId"
    fun editSupplier(supplierId: Long): String = "procurement/suppliers/$supplierId/edit"
}

fun NavGraphBuilder.procurementGraph() {
    composable(ProcurementRoutes.ROOT) { ProcurementScreen() }
    composable(ProcurementRoutes.NEW_REQUEST) { ProcurementRequestFormScreen() }
    composable(
        route = ProcurementRoutes.REQUEST,
        arguments = listOf(navArgument("requestId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementRequestScreen(requestId = entry.longArg("requestId"))
    }
    composable(
        route = ProcurementRoutes.NEW_QUOTATION,
        arguments = listOf(
            navArgument("requestId") { type = NavType.LongType },
            navArgument("itemId") { type = NavType.LongType },
        ),
    ) { entry ->
        ProcurementQuotationFormScreen(requestId = entry.longArg("requestId"), itemId = entry.longArg("itemId"))
    }
    composable(
        route = ProcurementRoutes.NEW_ORDER,
        arguments = listOf(navArgument("requestId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementOrderFormScreen(requestId = entry.longArg("requestId"))
    }
    composable(
        route = ProcurementRoutes.ORDER,
        arguments = listOf(navArgument("orderId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementOrderScreen(orderId = entry.longArg("orderId"))
    }
    composable(
        route = ProcurementRoutes.NEW_RECEIPT,
        arguments = listOf(navArgument("orderId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementReceiptFormScreen(orderId = entry.longArg("orderId"))
    }
    composable(
        route = ProcurementRoutes.RECEIPT,
        arguments = listOf(navArgument("receiptId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementReceiptScreen(receiptId = entry.longArg("receiptId"))
    }
    composable(
        route = ProcurementRoutes.NEW_RETURN,
        arguments = listOf(navArgument("receiptId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementReturnFormScreen(receiptId = entry.longArg("receiptId"))
    }
    composable(
        route = ProcurementRoutes.RETURN,
        arguments = listOf(navArgument("returnId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementReturnScreen(returnId = entry.longArg("returnId"))
    }
    composable(ProcurementRoutes.NEW_SUPPLIER) { ProcurementSupplierFormScreen(supplierId = null) }
    composable(
        route = ProcurementRoutes.SUPPLIER,
        arguments = listOf(navArgument("supplierId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementSupplierScreen(supplierId = entry.longArg("supplierId"))
    }
    composable(
        route = ProcurementRoutes.EDIT_SUPPLIER,
        arguments = listOf(navArgument("supplierId") { type = NavType.LongType }),
    ) { entry ->
        ProcurementSupplierFormScreen(supplierId = entry.longArg("supplierId"))
    }
}
