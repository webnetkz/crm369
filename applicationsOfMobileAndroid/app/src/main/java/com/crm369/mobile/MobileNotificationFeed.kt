package com.appswebnetkz.crm369

data class MobileNotificationFeed(
    val userId: Int,
    val notificationsUnreadCount: Int,
    val chatUnreadCount: Int,
    val entries: List<MobileNotificationEntry>,
)

data class MobileNotificationEntry(
    val key: String,
    val title: String,
    val message: String,
    val actionPath: String?,
    val createdAt: String?,
)
