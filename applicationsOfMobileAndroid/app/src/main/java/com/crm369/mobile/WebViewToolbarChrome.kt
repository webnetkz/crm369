package com.appswebnetkz.crm369

object WebViewToolbarChrome {
    fun topPadding(basePaddingTop: Int, statusBarInsetTop: Int): Int {
        return basePaddingTop + statusBarInsetTop
    }

    fun bottomPadding(basePaddingBottom: Int, navigationBarInsetBottom: Int): Int {
        return basePaddingBottom + navigationBarInsetBottom
    }
}
