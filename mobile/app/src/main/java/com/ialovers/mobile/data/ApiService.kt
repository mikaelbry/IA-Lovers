package com.ialovers.mobile.data

import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Query

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

    @POST("comments/create")
    suspend fun createComment(@Body request: CreateCommentRequest): CreateCommentResponse

    @POST("mobile/logout")
    suspend fun mobileLogout(@Header("Authorization") authorization: String? = null): SuccessResponse
}
