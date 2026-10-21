package com.appswebnetkz.crm369.ui.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.appswebnetkz.crm369.feature.calendar.calendarGraph
import com.appswebnetkz.crm369.feature.chats.chatsGraph
import com.appswebnetkz.crm369.feature.company.companyGraph
import com.appswebnetkz.crm369.feature.conferences.conferencesGraph
import com.appswebnetkz.crm369.feature.contacts.contactsGraph
import com.appswebnetkz.crm369.feature.directories.directoriesGraph
import com.appswebnetkz.crm369.feature.edo.edoGraph
import com.appswebnetkz.crm369.feature.equipment.equipmentGraph
import com.appswebnetkz.crm369.feature.files.filesGraph
import com.appswebnetkz.crm369.feature.forms.formsGraph
import com.appswebnetkz.crm369.feature.funnels.funnelsGraph
import com.appswebnetkz.crm369.feature.knowledge.knowledgeGraph
import com.appswebnetkz.crm369.feature.news.newsGraph
import com.appswebnetkz.crm369.feature.procurement.procurementGraph
import com.appswebnetkz.crm369.feature.production.productionGraph
import com.appswebnetkz.crm369.feature.tasks.tasksGraph
import com.appswebnetkz.crm369.feature.tsd.tsdGraph
import com.appswebnetkz.crm369.feature.warehouses.warehousesGraph
import com.appswebnetkz.crm369.ui.main.MainScreen
import com.appswebnetkz.crm369.ui.notifications.NotificationsScreen
import com.appswebnetkz.crm369.ui.onboarding.AccountsScreen
import com.appswebnetkz.crm369.ui.onboarding.LoginScreen
import com.appswebnetkz.crm369.ui.onboarding.PortalAddressScreen
import com.appswebnetkz.crm369.ui.profile.ChangePasswordScreen
import com.appswebnetkz.crm369.ui.profile.EditProfileScreen
import com.appswebnetkz.crm369.ui.profile.ProfileScreen
import com.appswebnetkz.crm369.ui.profile.SettingsScreen
import com.appswebnetkz.crm369.ui.web.WebPortalScreen

/** The single navigation graph of the application (core screens + every feature graph). */
@Composable
fun AppNavHost(navController: NavHostController, startDestination: String) {
    NavHost(navController = navController, startDestination = startDestination) {
        composable(AppRoutes.PORTAL) { PortalAddressScreen() }
        composable(
            route = AppRoutes.LOGIN,
            arguments = listOf(
                navArgument("baseUrl") {
                    type = NavType.StringType
                    nullable = true
                    defaultValue = null
                },
                navArgument("email") {
                    type = NavType.StringType
                    nullable = true
                    defaultValue = null
                },
            ),
        ) { entry ->
            val baseUrl = entry.stringArg("baseUrl")
            if (baseUrl == null) {
                PortalAddressScreen()
            } else {
                LoginScreen(baseUrl = baseUrl, initialEmail = entry.stringArg("email"))
            }
        }
        composable(AppRoutes.ACCOUNTS) { AccountsScreen() }
        composable(
            route = AppRoutes.MAIN,
            arguments = listOf(
                navArgument("tab") {
                    type = NavType.StringType
                    nullable = true
                    defaultValue = null
                },
            ),
        ) { entry ->
            MainScreen(initialTab = entry.stringArg("tab"))
        }
        composable(AppRoutes.NOTIFICATIONS) { NotificationsScreen() }
        composable(AppRoutes.PROFILE) { ProfileScreen() }
        composable(AppRoutes.PROFILE_EDIT) { EditProfileScreen() }
        composable(AppRoutes.PROFILE_PASSWORD) { ChangePasswordScreen() }
        composable(AppRoutes.SETTINGS) { SettingsScreen() }
        composable(
            route = AppRoutes.WEB,
            arguments = listOf(
                navArgument("path") {
                    type = NavType.StringType
                    nullable = true
                    defaultValue = null
                },
                navArgument("title") {
                    type = NavType.StringType
                    nullable = true
                    defaultValue = null
                },
            ),
        ) { entry ->
            WebPortalScreen(
                path = entry.stringArg("path") ?: "/dashboard",
                title = entry.stringArg("title"),
            )
        }

        chatsGraph()
        tasksGraph()
        funnelsGraph()
        contactsGraph()
        calendarGraph()
        companyGraph()
        newsGraph()
        knowledgeGraph()
        filesGraph()
        edoGraph()
        procurementGraph()
        warehousesGraph()
        equipmentGraph()
        tsdGraph()
        directoriesGraph()
        formsGraph()
        productionGraph()
        conferencesGraph()
    }
}
