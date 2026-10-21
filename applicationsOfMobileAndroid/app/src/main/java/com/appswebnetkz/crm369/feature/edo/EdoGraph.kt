package com.appswebnetkz.crm369.feature.edo

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/** Routes of the EDO module (electronic documents and signing links). */
object EdoRoutes {
    const val LIST = "edo"
    const val NEW = "edo/new"
    const val DOCUMENT = "edo/{documentId}"
    const val EDIT = "edo/{documentId}/edit"

    fun document(documentId: Long): String = "edo/$documentId"
    fun edit(documentId: Long): String = "edo/$documentId/edit"
}

fun NavGraphBuilder.edoGraph() {
    composable(EdoRoutes.LIST) { EdoListScreen() }
    composable(EdoRoutes.NEW) { EdoFormScreen(documentId = null) }
    composable(
        route = EdoRoutes.DOCUMENT,
        arguments = listOf(navArgument("documentId") { type = NavType.LongType }),
    ) { entry ->
        EdoDetailScreen(documentId = entry.longArg("documentId"))
    }
    composable(
        route = EdoRoutes.EDIT,
        arguments = listOf(navArgument("documentId") { type = NavType.LongType }),
    ) { entry ->
        EdoFormScreen(documentId = entry.longArg("documentId"))
    }
}
