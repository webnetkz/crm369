package com.appswebnetkz.crm369.core.push

import com.appswebnetkz.crm369.Crm369Application
import com.appswebnetkz.crm369.R
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

/**
 * Receives CRM369 push messages. When the application is in the background, Android shows the
 * server notification itself and opens [com.appswebnetkz.crm369.MainActivity] with the
 * `OPEN_CRM369` action; in the foreground the notification is built here.
 */
class CrmMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        (application as? Crm369Application)?.container?.pushRegistrar?.onNewToken()
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        val data: Map<String, String> = message.data
        val title = message.notification?.title
            ?: data[PushKeys.TITLE]
            ?: getString(R.string.app_name)
        val body = message.notification?.body
            ?: data[PushKeys.BODY]
            ?: ""
        NotificationPresenter.show(this, title, body, data)
        (application as? Crm369Application)?.container?.onPushReceived(data)
    }
}
