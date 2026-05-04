package com.ialovers.mobile.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class ApiErrorResponse(
    val error: String? = null,
)

@Serializable
data class MobileUser(
    val id: Int,
    val username: String,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
)

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

@Serializable
data class AuthResponse(
    val token: String,
    @SerialName("expires_at")
    val expiresAt: String? = null,
    @SerialName("expires_in_days")
    val expiresInDays: Int? = null,
    val user: MobileUser,
)

@Serializable
data class SessionResponse(
    val authenticated: Boolean,
    @SerialName("expires_in_days")
    val expiresInDays: Int? = null,
    val user: MobileUser,
)

@Serializable
data class ProfileResponse(
    val user: ProfileUser,
    val followers: Int = 0,
    val posts: List<PostItem> = emptyList(),
)

@Serializable
data class FollowUser(
    val username: String,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
)

@Serializable
data class SettingsSummaryResponse(
    val user: ProfileUser,
    val followers: Int = 0,
    @SerialName("posts_count")
    val postsCount: Int = 0,
)

@Serializable
data class FeedResponse(
    val posts: List<PostItem> = emptyList(),
    @SerialName("next_cursor")
    val nextCursor: Int? = null,
    @SerialName("next_cursor_likes")
    val nextCursorLikes: Int? = null,
)

@Serializable
data class PostDetailResponse(
    val post: PostItem,
    val comments: List<CommentItem> = emptyList(),
)

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

@Serializable
data class CommentItem(
    val id: Int,
    @SerialName("post_id")
    val postId: Int,
    @SerialName("user_id")
    val userId: Int,
    @SerialName("parent_id")
    val parentId: Int? = null,
    val username: String,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
    val content: String,
    @SerialName("created_at")
    val createdAt: String? = null,
)

@Serializable
data class LoginRequest(
    val email: String,
    val password: String,
)

@Serializable
data class RegisterStartRequest(
    val username: String,
    val email: String,
    val password: String,
    @SerialName("password_confirmation")
    val passwordConfirmation: String,
)

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

@Serializable
data class RegisterVerifyRequest(
    @SerialName("flow_token")
    val flowToken: String,
    val code: String,
)

@Serializable
data class FlowTokenRequest(
    @SerialName("flow_token")
    val flowToken: String,
)

@Serializable
data class RegisterMessageResponse(
    val message: String? = null,
    val email: String? = null,
)

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

@Serializable
data class ToggleLikeRequest(
    @SerialName("post_id")
    val postId: Int,
)

@Serializable
data class ToggleLikeResponse(
    val liked: Boolean,
)

@Serializable
data class CreateCommentRequest(
    @SerialName("post_id")
    val postId: Int,
    val content: String,
    @SerialName("parent_id")
    val parentId: Int? = null,
)

@Serializable
data class CreateCommentResponse(
    val comment: CommentItem,
    @SerialName("comments_count")
    val commentsCount: Int,
)

@Serializable
data class CreatePostResponse(
    val message: String? = null,
    val id: Int,
)

@Serializable
data class UpdateProfileRequest(
    val username: String,
    val email: String,
    val password: String? = null,
    @SerialName("current_password")
    val currentPassword: String,
)

@Serializable
data class UpdateProfileResponse(
    val message: String? = null,
)

@Serializable
data class CheckUsernameResponse(
    val available: Boolean,
)

@Serializable
data class AvatarResponse(
    val message: String? = null,
    @SerialName("avatar_path")
    val avatarPath: String? = null,
    @SerialName("avatar_url")
    val avatarUrl: String? = null,
)

@Serializable
data class StartEmailChangeRequest(
    @SerialName("new_email")
    val newEmail: String,
    @SerialName("current_password")
    val currentPassword: String,
)

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

@Serializable
data class VerifyEmailChangeRequest(
    val code: String,
)

@Serializable
data class VerifyEmailChangeResponse(
    val message: String? = null,
    val email: String,
)

@Serializable
data class ResendEmailChangeResponse(
    val message: String? = null,
    @SerialName("masked_email")
    val maskedEmail: String? = null,
    @SerialName("resend_cooldown")
    val resendCooldown: Int = 30,
)

@Serializable
data class DeleteAccountRequest(
    @SerialName("current_password")
    val currentPassword: String,
    @SerialName("confirm_text")
    val confirmText: String,
)

@Serializable
data class SuccessResponse(
    val success: Boolean = true,
)
