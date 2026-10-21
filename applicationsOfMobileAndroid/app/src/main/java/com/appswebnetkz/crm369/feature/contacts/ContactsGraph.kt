package com.appswebnetkz.crm369.feature.contacts

import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.ui.navigation.longArg

/** Routes of the contacts module. */
object ContactsRoutes {
    const val LIST = "contacts"
    const val NEW = "contacts/new"
    const val DETAIL = "contacts/{contactId}"
    const val EDIT = "contacts/{contactId}/edit"

    fun detail(contactId: Long): String = "contacts/$contactId"
    fun edit(contactId: Long): String = "contacts/$contactId/edit"
}

fun NavGraphBuilder.contactsGraph() {
    composable(ContactsRoutes.LIST) { ContactsListScreen() }
    composable(ContactsRoutes.NEW) { ContactFormScreen(contactId = null) }
    composable(
        route = ContactsRoutes.DETAIL,
        arguments = listOf(navArgument("contactId") { type = NavType.LongType }),
    ) { entry ->
        ContactDetailScreen(contactId = entry.longArg("contactId"))
    }
    composable(
        route = ContactsRoutes.EDIT,
        arguments = listOf(navArgument("contactId") { type = NavType.LongType }),
    ) { entry ->
        ContactFormScreen(contactId = entry.longArg("contactId"))
    }
}
