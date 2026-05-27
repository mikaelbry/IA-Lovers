/*
 * Construye la infraestructura HTTP de la aplicación móvil.
 * Configura Retrofit, OkHttp, serialización JSON, tiempos de espera,
 * logs de depuración y el envío automático del token de sesión.
 */
package com.ialovers.mobile.data

import android.content.Context
import com.ialovers.mobile.BuildConfig
import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import kotlinx.serialization.json.Json
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import java.util.concurrent.TimeUnit

/** Interceptor que añade la cabecera Authorization cuando existe un token guardado. */
private class AuthInterceptor(
    private val sessionStorage: SessionStorage,
) : Interceptor {
    /** Inserta el token Bearer en cada petición antes de enviarla al backend. */
    override fun intercept(chain: Interceptor.Chain): okhttp3.Response {
        val builder = chain.request().newBuilder()
        val token = sessionStorage.authToken

        if (!token.isNullOrBlank()) {
            builder.header("Authorization", "Bearer $token")
        }

        return chain.proceed(builder.build())
    }
}

/** Fábrica responsable de crear el servicio Retrofit y el almacén de sesión. */
object ApiFactory {
    private val json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
    }

    /** Crea una instancia lista para usar de ApiService junto a su SessionStorage. */
    fun create(context: Context): Pair<ApiService, SessionStorage> {
        val sessionStorage = SessionStorage(context.applicationContext)

        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = if (BuildConfig.DEBUG) {
                HttpLoggingInterceptor.Level.BASIC
            } else {
                HttpLoggingInterceptor.Level.NONE
            }
        }

        val client = OkHttpClient.Builder()
            .connectTimeout(10, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .writeTimeout(20, TimeUnit.SECONDS)
            .addInterceptor(AuthInterceptor(sessionStorage))
            .addInterceptor(loggingInterceptor)
            .build()

        val retrofit = Retrofit.Builder()
            .baseUrl(BuildConfig.API_BASE_URL)
            .client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()

        return retrofit.create(ApiService::class.java) to sessionStorage
    }
}
