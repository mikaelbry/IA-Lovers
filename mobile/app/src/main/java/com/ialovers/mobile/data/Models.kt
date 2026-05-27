/*
 * Contiene los modelos serializables que la app intercambia con la API.
 * Estas clases describen cuerpos de petición, respuestas del backend y
 * estructuras de estado que llegan ya preparadas para la interfaz móvil.
 */
package com.ialovers.mobile.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/** Error estándar devuelto por la API cuando una petición falla. */
@Serializable
data class ApiErrorResponse(
    val error: String? = null,
)

/** Usuario mínimo incluido en respuestas de autenticación y sesión. */
@Serializable
data class MobileUser(
    val id: Int,
    val username: String,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
)

/** Datos principales de un usuario dentro de perfiles y ajustes. */
@Serializable
data class ProfileUser(
    val id: Int,
    val username: String,
    val email: String? = null,
    @SerialName("created_at")
    val createdAt: String? = null,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
)

/** Respuesta de inicio de sesión con token y usuario autenticado. */
@Serializable
data class AuthResponse(
    val token: String,
    @SerialName("expires_at")
    val expiresAt: String? = null,
    @SerialName("expires_in_days")
    val expiresInDays: Int? = null,
    val user: MobileUser,
)

/** Respuesta de comprobación de sesión activa. */
@Serializable
data class SessionResponse(
    val authenticated: Boolean,
    @SerialName("expires_in_days")
    val expiresInDays: Int? = null,
    val user: MobileUser,
)

/** Perfil completo de un usuario junto a sus publicaciones y contadores. */
@Serializable
data class ProfileResponse(
    val user: ProfileUser,
    val followers: Int = 0,
    @SerialName("is_following")
    val isFollowing: Boolean = false,
    val posts: List<PostItem> = emptyList(),
)

/** Usuario resumido mostrado en listas de seguidores y seguidos. */
@Serializable
data class FollowUser(
    val username: String,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
)

/** Resumen de cuenta usado por la pantalla de ajustes. */
@Serializable
data class SettingsSummaryResponse(
    val user: ProfileUser,
    val followers: Int = 0,
    @SerialName("posts_count")
    val postsCount: Int = 0,
)

/** Página de publicaciones con cursores para cargar más contenido. */
@Serializable
data class FeedResponse(
    val posts: List<PostItem> = emptyList(),
    @SerialName("next_cursor")
    val nextCursor: Int? = null,
    @SerialName("next_cursor_likes")
    val nextCursorLikes: Int? = null,
)

/** Detalle de una publicación con su árbol plano de comentarios. */
@Serializable
data class PostDetailResponse(
    val post: PostItem,
    val comments: List<CommentItem> = emptyList(),
)

/** Publicación mostrada en feeds, perfiles y detalles. */
@Serializable
data class PostItem(
    val id: Int,
    @SerialName("user_id")
    val userId: Int? = null,
    val username: String? = null,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
    val title: String? = null,
    val description: String? = null,
    @SerialName("file_path")
    val filePath: String? = null,
    @SerialName("created_at")
    val createdAt: String? = null,
    @SerialName("likes_count")
    val likesCount: Int = 0,
    @SerialName("comments_count")
    val commentsCount: Int = 0,
    @SerialName("liked_by_user")
    val likedByUser: Boolean = false,
    val tags: String? = null,
)

/** Comentario o respuesta asociada a una publicación. */
@Serializable
data class CommentItem(
    val id: Int,
    @SerialName("post_id")
    val postId: Int,
    @SerialName("user_id")
    val userId: Int? = null,
    @SerialName("parent_id")
    val parentId: Int? = null,
    val username: String,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
    val content: String,
    @SerialName("created_at")
    val createdAt: String? = null,
    @SerialName("is_deleted")
    val isDeleted: Boolean = false,
)

/** Credenciales enviadas para iniciar sesión. */
@Serializable
data class LoginRequest(
    val email: String,
    val password: String,
)

/** Datos iniciales para crear una cuenta pendiente de verificación. */
@Serializable
data class RegisterStartRequest(
    val username: String,
    val email: String,
    val password: String,
    @SerialName("password_confirmation")
    val passwordConfirmation: String,
)

/** Respuesta al iniciar el registro con el flujo de verificación. */
@Serializable
data class RegisterStartResponse(
    val message: String? = null,
    @SerialName("flow_token")
    val flowToken: String,
    val email: String,
    @SerialName("masked_email")
    val maskedEmail: String? = null,
    @SerialName("resend_cooldown")
    val resendCooldown: Int? = null,
)

/** Código y token de flujo usados para confirmar un registro. */
@Serializable
data class RegisterVerifyRequest(
    @SerialName("flow_token")
    val flowToken: String,
    val code: String,
)

/** Petición genérica para operaciones que solo necesitan un token de flujo. */
@Serializable
data class FlowTokenRequest(
    @SerialName("flow_token")
    val flowToken: String,
)

/** Mensaje simple devuelto por acciones de registro o recuperación. */
@Serializable
data class RegisterMessageResponse(
    val message: String? = null,
    val email: String? = null,
)

/** Respuesta al reenviar el código de registro. */
@Serializable
data class RegisterResendResponse(
    val message: String? = null,
    @SerialName("flow_token")
    val flowToken: String,
    @SerialName("masked_email")
    val maskedEmail: String? = null,
    @SerialName("resend_cooldown")
    val resendCooldown: Int? = null,
)

/** Correo enviado para iniciar una recuperación de contraseña. */
@Serializable
data class PasswordResetStartRequest(
    val email: String,
)

/** Respuesta con token de flujo para continuar la recuperación de contraseña. */
@Serializable
data class PasswordResetStartResponse(
    val message: String? = null,
    @SerialName("flow_token")
    val flowToken: String,
    @SerialName("masked_email")
    val maskedEmail: String? = null,
    @SerialName("resend_cooldown")
    val resendCooldown: Int? = null,
)

/** Datos necesarios para completar el cambio de contraseña olvidada. */
@Serializable
data class PasswordResetCompleteRequest(
    @SerialName("flow_token")
    val flowToken: String,
    val code: String,
    val password: String,
    @SerialName("password_confirmation")
    val passwordConfirmation: String,
)

/** Respuesta al reenviar el código de recuperación de contraseña. */
@Serializable
data class PasswordResetResendResponse(
    val message: String? = null,
    @SerialName("flow_token")
    val flowToken: String,
    @SerialName("masked_email")
    val maskedEmail: String? = null,
    @SerialName("resend_cooldown")
    val resendCooldown: Int? = null,
)

/** Identificador de publicación usado para alternar un me gusta. */
@Serializable
data class ToggleLikeRequest(
    @SerialName("post_id")
    val postId: Int,
)

/** Identificador de publicación usado para borrarla. */
@Serializable
data class DeletePostRequest(
    @SerialName("post_id")
    val postId: Int,
)

/** Identificador de comentario usado para borrarlo. */
@Serializable
data class DeleteCommentRequest(
    @SerialName("comment_id")
    val commentId: Int,
)

/** Resultado de borrar un comentario y nuevo contador asociado. */
@Serializable
data class DeleteCommentResponse(
    val deleted: Boolean = false,
    val mode: String? = null,
    @SerialName("comments_count")
    val commentsCount: Int = 0,
)

/** Resultado de alternar un me gusta. */
@Serializable
data class ToggleLikeResponse(
    val liked: Boolean,
)

/** Datos para crear un comentario o una respuesta. */
@Serializable
data class CreateCommentRequest(
    @SerialName("post_id")
    val postId: Int,
    val content: String,
    @SerialName("parent_id")
    val parentId: Int? = null,
)

/** Comentario creado y contador actualizado de la publicación. */
@Serializable
data class CreateCommentResponse(
    val comment: CommentItem,
    @SerialName("comments_count")
    val commentsCount: Int,
)

/** Respuesta al publicar una nueva imagen. */
@Serializable
data class CreatePostResponse(
    val message: String? = null,
    val id: Int,
)

/** Datos enviados para actualizar información del perfil. */
@Serializable
data class UpdateProfileRequest(
    val username: String,
    val email: String,
    val password: String? = null,
    @SerialName("current_password")
    val currentPassword: String,
)

/** Mensaje devuelto tras actualizar el perfil. */
@Serializable
data class UpdateProfileResponse(
    val message: String? = null,
)

/** Resultado de comprobar disponibilidad de un nombre de usuario. */
@Serializable
data class CheckUsernameResponse(
    val available: Boolean,
)

/** Respuesta al subir o cambiar el avatar. */
@Serializable
data class AvatarResponse(
    val message: String? = null,
    @SerialName("avatar_path")
    val avatarPath: String? = null,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
)

/** Datos para iniciar un cambio de correo. */
@Serializable
data class StartEmailChangeRequest(
    @SerialName("new_email")
    val newEmail: String,
    @SerialName("current_password")
    val currentPassword: String,
)

/** Respuesta al iniciar el cambio de correo con verificación. */
@Serializable
data class StartEmailChangeResponse(
    val message: String? = null,
    @SerialName("new_email")
    val newEmail: String,
    @SerialName("masked_email")
    val maskedEmail: String,
    @SerialName("resend_cooldown")
    val resendCooldown: Int = 30,
)

/** Código usado para confirmar el cambio de correo. */
@Serializable
data class VerifyEmailChangeRequest(
    val code: String,
)

/** Respuesta final al verificar y aplicar el nuevo correo. */
@Serializable
data class VerifyEmailChangeResponse(
    val message: String? = null,
    val email: String,
)

/** Respuesta al reenviar el código de cambio de correo. */
@Serializable
data class ResendEmailChangeResponse(
    val message: String? = null,
    @SerialName("masked_email")
    val maskedEmail: String? = null,
    @SerialName("resend_cooldown")
    val resendCooldown: Int = 30,
)

/** Confirmación necesaria para borrar definitivamente la cuenta. */
@Serializable
data class DeleteAccountRequest(
    @SerialName("current_password")
    val currentPassword: String,
    @SerialName("confirm_text")
    val confirmText: String,
)

/** Respuesta booleana común para acciones simples. */
@Serializable
data class SuccessResponse(
    val success: Boolean = true,
)

/** Nombre de usuario objetivo para seguir o dejar de seguir. */
@Serializable
data class ToggleFollowRequest(
    @SerialName("username")
    val username: String,
)

/** Resultado de seguir o dejar de seguir a un usuario. */
@Serializable
data class ToggleFollowResponse(
    val following: Boolean,
)

/** Notificación individual mostrada en la pestaña de notificaciones. */
@Serializable
data class NotificationItem(
    val id: Int,
    val type: String,
    @SerialName("from_user_id")
    val fromUserId: Int? = null,
    @SerialName("from_username")
    val fromUsername: String? = null,
    @SerialName("from_avatar_url")
    val fromAvatarUrl: String? = null,
    @SerialName("post_id")
    val postId: Int? = null,
    @SerialName("post_title")
    val postTitle: String? = null,
    @SerialName("post_image_url")
    val postImageUrl: String? = null,
    @SerialName("is_read")
    val isRead: Boolean = false,
    @SerialName("created_at")
    val createdAt: String? = null,
)

/** Lista de notificaciones y contador de no leídas. */
@Serializable
data class NotificationsResponse(
    val notifications: List<NotificationItem> = emptyList(),
    @SerialName("unread_count")
    val unreadCount: Int = 0,
)

/** Contador de notificaciones pendientes de lectura. */
@Serializable
data class UnreadCountResponse(
    @SerialName("unread_count")
    val unreadCount: Int = 0,
)

/** Petición para marcar una notificación concreta o todas como leídas. */
@Serializable
data class NotificationsReadRequest(
    val id: Int? = null,
)
