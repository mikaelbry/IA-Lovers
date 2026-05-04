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

interface ApiService {
    @POST("mobile/login")
    suspend fun mobileLogin(@Body request: LoginRequest): AuthResponse

    @POST("mobile/register/start")
    suspend fun mobileRegisterStart(@Body request: RegisterStartRequest): RegisterStartResponse

    @POST("mobile/register/verify")
    suspend fun mobileRegisterVerify(@Body request: RegisterVerifyRequest): RegisterMessageResponse

    @POST("mobile/register/resend")
    suspend fun mobileRegisterResend(@Body request: FlowTokenRequest): RegisterResendResponse

    @POST("mobile/register/cancel")
    suspend fun mobileRegisterCancel(@Body request: FlowTokenRequest): SuccessResponse

    @GET("session")
    suspend fun session(): SessionResponse

    @GET("users/profile")
    suspend fun userProfile(): ProfileResponse

    @GET("users/username")
    suspend fun profileByUsername(@Query("username") username: String): ProfileResponse

    @GET("users/settings-summary")
    suspend fun settingsSummary(): SettingsSummaryResponse

    @GET("users/followers")
    suspend fun followers(@Query("user_id") userId: Int? = null): List<FollowUser>

    @GET("users/following")
    suspend fun following(@Query("user_id") userId: Int? = null): List<FollowUser>

    @GET("users/check-username")
    suspend fun checkUsername(@Query("username") username: String): CheckUsernameResponse

    @POST("user/update")
    suspend fun updateProfile(@Body request: UpdateProfileRequest): UpdateProfileResponse

    @Multipart
    @POST("user/avatar")
    suspend fun updateAvatar(@Part avatar: MultipartBody.Part): AvatarResponse

    @POST("user/email-change/start")
    suspend fun startEmailChange(@Body request: StartEmailChangeRequest): StartEmailChangeResponse

    @POST("user/email-change/verify")
    suspend fun verifyEmailChange(@Body request: VerifyEmailChangeRequest): VerifyEmailChangeResponse

    @POST("user/email-change/resend")
    suspend fun resendEmailChange(): ResendEmailChangeResponse

    @POST("user/email-change/cancel")
    suspend fun cancelEmailChange(): SuccessResponse

    @POST("user/delete")
    suspend fun deleteAccount(@Body request: DeleteAccountRequest): SuccessResponse

    @GET("posts")
    suspend fun posts(
        @Query("type") type: String,
        @Query("cursor") cursor: Int? = null,
        @Query("cursor_likes") cursorLikes: Int? = null,
        @Query("order") order: String = "recent",
    ): FeedResponse

    @GET("posts/show")
    suspend fun postDetail(@Query("id") id: Int): PostDetailResponse

    @POST("posts/toggle-like")
    suspend fun toggleLike(@Body request: ToggleLikeRequest): ToggleLikeResponse

    @Multipart
    @POST("posts/create")
    suspend fun createPost(
        @Part image: MultipartBody.Part,
        @Part("title") title: RequestBody,
        @Part("description") description: RequestBody,
        @Part("tags") tags: RequestBody,
    ): CreatePostResponse

    @POST("comments/create")
    suspend fun createComment(@Body request: CreateCommentRequest): CreateCommentResponse

    @POST("mobile/logout")
    suspend fun mobileLogout(@Header("Authorization") authorization: String? = null): SuccessResponse
}
