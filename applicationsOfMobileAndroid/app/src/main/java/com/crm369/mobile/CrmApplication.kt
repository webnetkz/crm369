package com.appswebnetkz.crm369

import android.app.Application

class CrmApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        NotificationChannels.ensure(this)
        FirebaseBootstrap.initialize(this)
    }
}
