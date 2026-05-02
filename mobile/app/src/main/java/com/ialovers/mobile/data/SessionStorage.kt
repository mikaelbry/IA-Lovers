package com.ialovers.mobile.data

import android.content.Context
import android.content.SharedPreferences
import androidx.core.content.edit

class SessionStorage(context: Context) {
    private val preferences: SharedPreferences =
        context.getSharedPreferences("ia_lovers_mobile", Context.MODE_PRIVATE)

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

    fun saveSession(response: AuthResponse) {
        authToken = response.token
    }

    fun clearSession() {
        preferences.edit {
            remove(KEY_AUTH_TOKEN)
        }
    }

    companion object {
        private const val KEY_AUTH_TOKEN = "auth_token"
    }
}
