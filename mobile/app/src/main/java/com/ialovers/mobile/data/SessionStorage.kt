/*
 * Centraliza la persistencia local de la sesión móvil.
 * Guarda y borra el token de autenticación usando SharedPreferences.
 */
package com.ialovers.mobile.data

import android.content.Context
import android.content.SharedPreferences
import androidx.core.content.edit

/** Almacén ligero para conservar el token de sesión entre aperturas de la app. */
class SessionStorage(context: Context) {
    private val preferences: SharedPreferences =
        context.getSharedPreferences("ia_lovers_mobile", Context.MODE_PRIVATE)

    /** Token Bearer actual; al asignar un valor vacío se elimina de preferencias. */
    var authToken: String?
        get() = preferences.getString(KEY_AUTH_TOKEN, null)
        set(value) {
            preferences.edit {
                if (value.isNullOrBlank()) {
                    remove(KEY_AUTH_TOKEN)
                } else {
                    putString(KEY_AUTH_TOKEN, value)
                }
            }
        }

    /** Guarda el token recibido tras iniciar sesión correctamente. */
    fun saveSession(response: AuthResponse) {
        authToken = response.token
    }

    /** Borra cualquier token local y deja la app sin sesión persistida. */
    fun clearSession() {
        preferences.edit {
            remove(KEY_AUTH_TOKEN)
        }
    }

    companion object {
        private const val KEY_AUTH_TOKEN = "auth_token"
    }
}
