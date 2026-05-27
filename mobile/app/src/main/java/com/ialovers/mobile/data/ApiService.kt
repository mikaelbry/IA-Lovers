/*
 * Define todos los endpoints HTTP que consume la aplicación móvil.
 * Retrofit usa esta interfaz para convertir llamadas Kotlin en peticiones
 * al backend de IA Lovers y deserializar sus respuestas JSON.
 */
package com.ialovers.mobile.data

import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Query
import okhttp3.MultipartBody
import okhttp3.RequestBody

/** Contrato Retrofit de la API remota usada por la app. */
interface ApiService {
    /** Inicia sesión desde el cliente móvil y devuelve el token de acceso. */
    @POST("mobile/login")
    suspend fun mobileLogin(@Body request: LoginRequest): AuthResponse

    /** Inicia el registro móvil y solicita el envío del código de verificación. */
    @POST("mobile/register/start")
    suspend fun mobileRegisterStart(@Body request: RegisterStartRequest): RegisterStartResponse

    /** Verifica el código recibido por correo y crea la cuenta pendiente. */
    @POST("mobile/register/verify")
    suspend fun mobileRegisterVerify(@Body request: RegisterVerifyRequest): RegisterMessageResponse

    /** Reenvía el código de verificación de un registro pendiente. */
    @POST("mobile/register/resend")
    suspend fun mobileRegisterResend(@Body request: FlowTokenRequest): RegisterResendResponse

    /** Cancela un registro pendiente cuando el usuario abandona el flujo. */
    @POST("mobile/register/cancel")
    suspend fun mobileRegisterCancel(@Body request: FlowTokenRequest): SuccessResponse

    /** Inicia el flujo de recuperación de contraseña desde el móvil. */
    @POST("mobile/password-reset/start")
    suspend fun mobilePasswordResetStart(@Body request: PasswordResetStartRequest): PasswordResetStartResponse

    /** Reenvía el código de recuperación de contraseña. */
    @POST("mobile/password-reset/resend")
    suspend fun mobilePasswordResetResend(@Body request: FlowTokenRequest): PasswordResetResendResponse

    /** Completa la recuperación validando el código y guardando la nueva contraseña. */
    @POST("mobile/password-reset/complete")
    suspend fun mobilePasswordResetComplete(@Body request: PasswordResetCompleteRequest): RegisterMessageResponse

    /** Cancela una recuperación de contraseña pendiente. */
    @POST("mobile/password-reset/cancel")
    suspend fun mobilePasswordResetCancel(@Body request: FlowTokenRequest): SuccessResponse

    /** Comprueba si el token guardado sigue representando una sesión válida. */
    @GET("session")
    suspend fun session(): SessionResponse

    /** Obtiene el perfil privado del usuario autenticado. */
    @GET("users/profile")
    suspend fun userProfile(): ProfileResponse

    /** Obtiene el perfil público asociado a un nombre de usuario. */
    @GET("users/username")
    suspend fun profileByUsername(@Query("username") username: String): ProfileResponse

    /** Recupera los datos necesarios para la pantalla de ajustes. */
    @GET("users/settings-summary")
    suspend fun settingsSummary(): SettingsSummaryResponse

    /** Lista los seguidores del usuario indicado o del usuario actual. */
    @GET("users/followers")
    suspend fun followers(@Query("user_id") userId: Int? = null): List<FollowUser>

    /** Lista las cuentas que sigue el usuario indicado o el usuario actual. */
    @GET("users/following")
    suspend fun following(@Query("user_id") userId: Int? = null): List<FollowUser>

    /** Comprueba si un nombre de usuario está disponible antes de guardarlo. */
    @GET("users/check-username")
    suspend fun checkUsername(@Query("username") username: String): CheckUsernameResponse

    /** Actualiza datos básicos del perfil, como usuario, correo o contraseña. */
    @POST("user/update")
    suspend fun updateProfile(@Body request: UpdateProfileRequest): UpdateProfileResponse

    /** Sube un nuevo avatar para el usuario autenticado. */
    @Multipart
    @POST("user/avatar")
    suspend fun updateAvatar(@Part avatar: MultipartBody.Part): AvatarResponse

    /** Inicia el cambio de correo y envía un código de confirmación. */
    @POST("user/email-change/start")
    suspend fun startEmailChange(@Body request: StartEmailChangeRequest): StartEmailChangeResponse

    /** Verifica el código del cambio de correo pendiente. */
    @POST("user/email-change/verify")
    suspend fun verifyEmailChange(@Body request: VerifyEmailChangeRequest): VerifyEmailChangeResponse

    /** Reenvía el código para confirmar un cambio de correo. */
    @POST("user/email-change/resend")
    suspend fun resendEmailChange(): ResendEmailChangeResponse

    /** Cancela el cambio de correo pendiente del usuario. */
    @POST("user/email-change/cancel")
    suspend fun cancelEmailChange(): SuccessResponse

    /** Borra la cuenta del usuario tras confirmar su contraseña. */
    @POST("user/delete")
    suspend fun deleteAccount(@Body request: DeleteAccountRequest): SuccessResponse

    /** Carga un feed paginado de publicaciones, con búsqueda y orden opcionales. */
    @GET("posts")
    suspend fun posts(
        @Query("type") type: String,
        @Query("cursor") cursor: Int? = null,
        @Query("cursor_likes") cursorLikes: Int? = null,
        @Query("q") query: String? = null,
        @Query("order") order: String = "recent",
    ): FeedResponse

    /** Obtiene el detalle de una publicación y sus comentarios. */
    @GET("posts/show")
    suspend fun postDetail(@Query("id") id: Int): PostDetailResponse

    /** Alterna el me gusta del usuario sobre una publicación. */
    @POST("posts/toggle-like")
    suspend fun toggleLike(@Body request: ToggleLikeRequest): ToggleLikeResponse

    /** Elimina una publicación propia. */
    @POST("posts/delete")
    suspend fun deletePost(@Body request: DeletePostRequest): SuccessResponse

    /** Publica una imagen con título, descripción y etiquetas. */
    @Multipart
    @POST("posts/create")
    suspend fun createPost(
        @Part image: MultipartBody.Part,
        @Part("title") title: RequestBody,
        @Part("description") description: RequestBody,
        @Part("tags") tags: RequestBody,
    ): CreatePostResponse

    /** Crea un comentario o respuesta en una publicación. */
    @POST("comments/create")
    suspend fun createComment(@Body request: CreateCommentRequest): CreateCommentResponse

    /** Elimina un comentario propio. */
    @POST("comments/delete")
    suspend fun deleteComment(@Body request: DeleteCommentRequest): DeleteCommentResponse

    /** Cierra la sesión del dispositivo móvil. */
    @POST("logout")
    suspend fun mobileLogout(@Header("Authorization") authorization: String? = null): SuccessResponse

    /** Sigue o deja de seguir a otro usuario. */
    @POST("follow")
    suspend fun toggleFollow(@Body request: ToggleFollowRequest): ToggleFollowResponse

    /** Obtiene las notificaciones del usuario autenticado. */
    @GET("notifications")
    suspend fun notifications(): NotificationsResponse

    /** Devuelve el número de notificaciones sin leer. */
    @GET("notifications/unread-count")
    suspend fun notificationsUnreadCount(): UnreadCountResponse

    /** Marca una notificación concreta o todas como leídas. */
    @POST("notifications/read")
    suspend fun notificationsRead(@Body request: NotificationsReadRequest): SuccessResponse
}
