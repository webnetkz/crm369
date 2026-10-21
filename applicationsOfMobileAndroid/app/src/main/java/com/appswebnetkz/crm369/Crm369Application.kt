package com.appswebnetkz.crm369

import android.app.Application
import coil.ImageLoader
import coil.ImageLoaderFactory
import coil.disk.DiskCache
import coil.memory.MemoryCache
import com.appswebnetkz.crm369.core.push.NotificationChannels
import okhttp3.Interceptor

class Crm369Application : Application(), ImageLoaderFactory {

    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
        NotificationChannels.ensure(this)
        container.pushRegistrar.restore()
    }

    /**
     * Coil image loader shared by all screens. Images of the active portal (avatars, logos,
     * attachments) are requested with the mobile bearer token.
     */
    override fun newImageLoader(): ImageLoader {
        val authInterceptor = Interceptor { chain ->
            val request = chain.request()
            val api = container.session.api()
            val authorization = api.authorizationHeader()
            if (authorization != null && request.header("Authorization") == null && api.isPortalUrl(request.url.toString())) {
                chain.proceed(request.newBuilder().header("Authorization", authorization).build())
            } else {
                chain.proceed(request)
            }
        }
        return ImageLoader.Builder(this)
            .okHttpClient {
                container.httpClient.newBuilder()
                    .addInterceptor(authInterceptor)
                    .build()
            }
            .memoryCache {
                MemoryCache.Builder(this)
                    .maxSizePercent(0.20)
                    .build()
            }
            .diskCache {
                DiskCache.Builder()
                    .directory(cacheDir.resolve("images"))
                    .maxSizeBytes(64L * 1024L * 1024L)
                    .build()
            }
            .crossfade(true)
            .build()
    }
}
