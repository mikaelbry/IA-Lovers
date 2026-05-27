/*
 * Punto de configuración global de la aplicación Android.
 * Define el cargador de imágenes compartido para que Coil use caché
 * de memoria y disco en todas las pantallas.
 */
package com.ialovers.mobile

import android.app.Application
import coil.ImageLoader
import coil.ImageLoaderFactory
import coil.disk.DiskCache
import coil.memory.MemoryCache

/** Aplicación base que proporciona un ImageLoader común a toda la app. */
class IaLoversApplication : Application(), ImageLoaderFactory {
    /** Crea el cargador de imágenes con caché y sin animación de fundido. */
    override fun newImageLoader(): ImageLoader {
        return ImageLoader.Builder(this)
            .crossfade(false)
            .memoryCache {
                MemoryCache.Builder(this)
                    .maxSizePercent(0.25)
                    .build()
            }
            .diskCache {
                DiskCache.Builder()
                    .directory(cacheDir.resolve("image_cache"))
                    .maxSizePercent(0.05)
                    .build()
            }
            .build()
    }
}
