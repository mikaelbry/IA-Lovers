package com.ialovers.mobile

import android.content.Context
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.ialovers.mobile.data.ApiErrorResponse
import com.ialovers.mobile.data.ApiFactory
import com.ialovers.mobile.data.ApiService
import com.ialovers.mobile.data.CommentItem
import com.ialovers.mobile.data.CreateCommentRequest
import com.ialovers.mobile.data.FlowTokenRequest
import com.ialovers.mobile.data.LoginRequest
import com.ialovers.mobile.data.PostItem
import com.ialovers.mobile.data.ProfileResponse
import com.ialovers.mobile.data.RegisterStartRequest
import com.ialovers.mobile.data.RegisterVerifyRequest
import com.ialovers.mobile.data.SessionStorage
import com.ialovers.mobile.data.ToggleLikeRequest
import java.io.IOException
import kotlinx.coroutines.launch
import kotlinx.serialization.json.Json
import retrofit2.HttpException

class AppViewModel(
    context: Context,
) : ViewModel() {
    private val api: ApiService
    private val sessionStorage: SessionStorage
    private val json = Json { ignoreUnknownKeys = true }

    var rootDestination by mutableStateOf(RootDestination.Splash)
        private set

    var selectedTab by mutableStateOf(MainTab.Explore)
        private set

    var activePostId by mutableStateOf<Int?>(null)
        private set

    var isBusy by mutableStateOf(false)
        private set

    var authMessage by mutableStateOf<String?>(null)
        private set

    var authError by mutableStateOf<String?>(null)
        private set

    var pendingRegistration by mutableStateOf<PendingRegistrationUi?>(null)
        private set

    var exploreState by mutableStateOf(FeedUiState())
        private set

    var followingState by mutableStateOf(FeedUiState())
        private set

    var profileState by mutableStateOf(ProfileUiState(isLoading = true))
        private set

    var postDetailState by mutableStateOf(PostDetailUiState())
        private set

    init {
        val (service, storage) = ApiFactory.create(context)
        api = service
        sessionStorage = storage
        bootstrap()
    }

    fun goToAuthChoice() {
        authError = null
        authMessage = null
        rootDestination = RootDestination.AuthChoice
    }

    fun goToLogin() {
        authError = null
        authMessage = null
        rootDestination = RootDestination.Login
    }

    fun goToRegister() {
        authError = null
        authMessage = null
        rootDestination = RootDestination.Register
    }

    fun selectTab(tab: MainTab) {
        activePostId = null
        selectedTab = tab

        when (tab) {
            MainTab.Explore -> if (exploreState.posts.isEmpty()) refreshFeed(MainTab.Explore)
            MainTab.Following -> if (followingState.posts.isEmpty()) refreshFeed(MainTab.Following)
            MainTab.Profile -> if (profileState.profile == null) refreshProfile()
        }
    }

    fun openPost(postId: Int) {
        activePostId = postId
        loadPostDetail(postId)
    }

    fun closePost() {
        activePostId = null
        postDetailState = PostDetailUiState()
    }

    fun login(email: String, password: String) {
        val trimmedEmail = email.trim()
        val rawPassword = password

        if (trimmedEmail.isBlank() || rawPassword.isBlank()) {
            authError = "Introduce tu email y tu contrasena."
            return
        }

        viewModelScope.launch {
            isBusy = true
            authError = null

            try {
                val response = api.mobileLogin(
                    LoginRequest(
                        email = trimmedEmail,
                        password = rawPassword,
                    )
                )

                sessionStorage.saveSession(response)
                authMessage = null
                pendingRegistration = null
                enterMain()
            } catch (error: Throwable) {
                authError = errorMessage(error)
            } finally {
                isBusy = false
            }
        }
    }

    fun startRegistration(
        username: String,
        email: String,
        password: String,
        passwordConfirmation: String,
    ) {
        val trimmedUsername = username.trim()
        val trimmedEmail = email.trim()

        if (trimmedUsername.isBlank() || trimmedEmail.isBlank() || password.isBlank() || passwordConfirmation.isBlank()) {
            authError = "Completa todos los campos."
            return
        }

        if (password != passwordConfirmation) {
            authError = "Las contrasenas no coinciden."
            return
        }

        viewModelScope.launch {
            isBusy = true
            authError = null

            try {
                val response = api.mobileRegisterStart(
                    RegisterStartRequest(
                        username = trimmedUsername,
                        email = trimmedEmail,
                        password = password,
                        passwordConfirmation = passwordConfirmation,
                    )
                )

                pendingRegistration = PendingRegistrationUi(
                    flowToken = response.flowToken,
                    email = response.email,
                    maskedEmail = response.maskedEmail ?: response.email,
                    resendCooldown = response.resendCooldown ?: 30,
                )
                authMessage = response.message ?: "Te hemos enviado un codigo al correo indicado."
            } catch (error: Throwable) {
                authError = errorMessage(error)
            } finally {
                isBusy = false
            }
        }
    }

    fun verifyRegistration(code: String) {
        val pending = pendingRegistration

        if (pending == null) {
            authError = "No hay un registro pendiente."
            return
        }

        if (code.trim().length != 6) {
            authError = "El codigo debe tener 6 digitos."
            return
        }

        viewModelScope.launch {
            isBusy = true
            authError = null

            try {
                val response = api.mobileRegisterVerify(
                    RegisterVerifyRequest(
                        flowToken = pending.flowToken,
                        code = code.trim(),
                    )
                )

                pendingRegistration = null
                authMessage = response.message ?: "Cuenta creada correctamente. Ya puedes iniciar sesion."
                rootDestination = RootDestination.Login
            } catch (error: Throwable) {
                authError = errorMessage(error)
            } finally {
                isBusy = false
            }
        }
    }

    fun resendRegistrationCode() {
        val pending = pendingRegistration ?: run {
            authError = "No hay un registro pendiente."
            return
        }

        viewModelScope.launch {
            isBusy = true
            authError = null

            try {
                val response = api.mobileRegisterResend(
                    FlowTokenRequest(
                        flowToken = pending.flowToken,
                    )
                )

                pendingRegistration = pending.copy(
                    flowToken = response.flowToken,
                    maskedEmail = response.maskedEmail ?: pending.maskedEmail,
                    resendCooldown = response.resendCooldown ?: pending.resendCooldown,
                )
                authMessage = response.message ?: "Hemos reenviado un nuevo codigo."
            } catch (error: Throwable) {
                authError = errorMessage(error)
            } finally {
                isBusy = false
            }
        }
    }

    fun cancelPendingRegistration() {
        val pending = pendingRegistration ?: run {
            rootDestination = RootDestination.Register
            return
        }

        viewModelScope.launch {
            isBusy = true

            try {
                api.mobileRegisterCancel(
                    FlowTokenRequest(
                        flowToken = pending.flowToken,
                    )
                )
            } catch (_: Throwable) {
            } finally {
                pendingRegistration = null
                authError = null
                authMessage = null
                isBusy = false
            }
        }
    }

    fun refreshFeed(tab: MainTab) {
        if (tab == MainTab.Profile) {
            refreshProfile()
            return
        }

        viewModelScope.launch {
            setFeedState(tab, feedState(tab).copy(isLoading = true, error = null))

            try {
                val response = api.posts(type = tab.feedType)
                setFeedState(
                    tab,
                    FeedUiState(
                        isLoading = false,
                        posts = response.posts,
                        nextCursor = response.nextCursor,
                        nextCursorLikes = response.nextCursorLikes,
                    )
                )
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    setFeedState(tab, feedState(tab).copy(isLoading = false, error = errorMessage(error)))
                }
            }
        }
    }

    fun loadMoreFeed(tab: MainTab) {
        if (tab == MainTab.Profile) return

        val current = feedState(tab)
        val cursor = current.nextCursor ?: return

        if (current.isLoadingMore || current.isLoading) return

        viewModelScope.launch {
            setFeedState(tab, current.copy(isLoadingMore = true, error = null))

            try {
                val response = api.posts(
                    type = tab.feedType,
                    cursor = cursor,
                    cursorLikes = current.nextCursorLikes,
                )

                setFeedState(
                    tab,
                    feedState(tab).copy(
                        isLoadingMore = false,
                        posts = feedState(tab).posts + response.posts,
                        nextCursor = response.nextCursor,
                        nextCursorLikes = response.nextCursorLikes,
                    )
                )
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    setFeedState(tab, feedState(tab).copy(isLoadingMore = false, error = errorMessage(error)))
                }
            }
        }
    }

    fun refreshProfile() {
        viewModelScope.launch {
            loadProfileInternal()
        }
    }

    fun toggleLike(post: PostItem) {
        applyPostLike(post.id, !post.likedByUser)

        viewModelScope.launch {
            try {
                val response = api.toggleLike(ToggleLikeRequest(post.id))
                applyPostLike(post.id, response.liked)
            } catch (error: Throwable) {
                applyPostLike(post.id, post.likedByUser)
                handleAuthenticatedError(error)
            }
        }
    }

    fun createComment(content: String) {
        val postId = activePostId ?: return
        val trimmed = content.trim()

        if (trimmed.isBlank()) {
            return
        }

        viewModelScope.launch {
            postDetailState = postDetailState.copy(isCommentSending = true, error = null)

            try {
                val response = api.createComment(
                    CreateCommentRequest(
                        postId = postId,
                        content = trimmed,
                    )
                )

                val currentPost = postDetailState.post
                postDetailState = postDetailState.copy(
                    isCommentSending = false,
                    post = currentPost?.copy(commentsCount = response.commentsCount),
                    comments = postDetailState.comments + response.comment,
                )
                updatePostEverywhere(postId) { it.copy(commentsCount = response.commentsCount) }
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    postDetailState = postDetailState.copy(
                        isCommentSending = false,
                        error = errorMessage(error),
                    )
                }
            }
        }
    }

    fun logout() {
        val authorization = sessionStorage.authToken?.takeIf { it.isNotBlank() }?.let { "Bearer $it" }

        sessionStorage.clearSession()
        pendingRegistration = null
        authMessage = null
        authError = null
        exploreState = FeedUiState()
        followingState = FeedUiState()
        profileState = ProfileUiState(isLoading = false)
        postDetailState = PostDetailUiState()
        activePostId = null
        selectedTab = MainTab.Explore
        rootDestination = RootDestination.AuthChoice

        viewModelScope.launch {
            try {
                api.mobileLogout(authorization)
            } catch (_: Throwable) {
            }
        }
    }

    private fun bootstrap() {
        viewModelScope.launch {
            val token = sessionStorage.authToken

            if (token.isNullOrBlank()) {
                profileState = ProfileUiState(isLoading = false)
                rootDestination = RootDestination.AuthChoice
                return@launch
            }

            enterMain()
        }
    }

    private fun enterMain() {
        rootDestination = RootDestination.Main
        selectedTab = MainTab.Explore
        activePostId = null
        refreshFeed(MainTab.Explore)
        refreshProfile()
    }

    private suspend fun loadProfileInternal() {
        profileState = profileState.copy(isLoading = true, error = null)

        try {
            val profile = api.userProfile()
            profileState = ProfileUiState(
                isLoading = false,
                profile = profile,
            )
            authError = null
            authMessage = null
        } catch (error: Throwable) {
            handleAuthenticatedError(error) {
                profileState = ProfileUiState(
                    isLoading = false,
                    error = errorMessage(error),
                )
            }
        }
    }

    private fun loadPostDetail(postId: Int) {
        viewModelScope.launch {
            postDetailState = PostDetailUiState(isLoading = true)

            try {
                val response = api.postDetail(postId)
                postDetailState = PostDetailUiState(
                    isLoading = false,
                    post = response.post,
                    comments = response.comments,
                )
                updatePostEverywhere(postId) { response.post }
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    postDetailState = PostDetailUiState(
                        isLoading = false,
                        error = errorMessage(error),
                    )
                }
            }
        }
    }

    private fun applyPostLike(postId: Int, liked: Boolean) {
        updatePostEverywhere(postId) { post ->
            val delta = when {
                liked && !post.likedByUser -> 1
                !liked && post.likedByUser -> -1
                else -> 0
            }

            post.copy(
                likedByUser = liked,
                likesCount = (post.likesCount + delta).coerceAtLeast(0),
            )
        }
    }

    private fun updatePostEverywhere(postId: Int, update: (PostItem) -> PostItem) {
        exploreState = exploreState.copy(posts = exploreState.posts.map { if (it.id == postId) update(it) else it })
        followingState = followingState.copy(posts = followingState.posts.map { if (it.id == postId) update(it) else it })
        profileState = profileState.copy(
            profile = profileState.profile?.copy(
                posts = profileState.profile?.posts.orEmpty().map { if (it.id == postId) update(it) else it }
            )
        )

        postDetailState.post?.takeIf { it.id == postId }?.let {
            postDetailState = postDetailState.copy(post = update(it))
        }
    }

    private fun feedState(tab: MainTab): FeedUiState {
        return when (tab) {
            MainTab.Explore -> exploreState
            MainTab.Following -> followingState
            MainTab.Profile -> FeedUiState()
        }
    }

    private fun setFeedState(tab: MainTab, state: FeedUiState) {
        when (tab) {
            MainTab.Explore -> exploreState = state
            MainTab.Following -> followingState = state
            MainTab.Profile -> Unit
        }
    }

    private fun handleAuthenticatedError(error: Throwable, fallback: () -> Unit = {}) {
        if (error is HttpException && error.code() == 401) {
            sessionStorage.clearSession()
            exploreState = FeedUiState()
            followingState = FeedUiState()
            profileState = ProfileUiState(isLoading = false)
            activePostId = null
            rootDestination = RootDestination.AuthChoice
            authMessage = "La sesion habia caducado. Inicia sesion de nuevo."
            return
        }

        fallback()
    }

    private fun errorMessage(error: Throwable): String {
        return when (error) {
            is HttpException -> {
                val rawError = error.response()?.errorBody()?.string().orEmpty()
                val parsed = rawError.takeIf { it.isNotBlank() }?.let {
                    runCatching {
                        json.decodeFromString<ApiErrorResponse>(it).error
                    }.getOrNull()
                }

                parsed ?: "La API devolvio un error (${error.code()})."
            }

            is IOException -> "No se pudo conectar con la API. Revisa la URL base y que el servidor este levantado."
            else -> error.message ?: "Ha ocurrido un error inesperado."
        }
    }

    companion object {
        fun factory(context: Context): ViewModelProvider.Factory {
            return object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T {
                    return AppViewModel(context.applicationContext) as T
                }
            }
        }
    }
}

data class PendingRegistrationUi(
    val flowToken: String,
    val email: String,
    val maskedEmail: String,
    val resendCooldown: Int,
)

data class FeedUiState(
    val isLoading: Boolean = false,
    val isLoadingMore: Boolean = false,
    val posts: List<PostItem> = emptyList(),
    val nextCursor: Int? = null,
    val nextCursorLikes: Int? = null,
    val error: String? = null,
)

data class ProfileUiState(
    val isLoading: Boolean = false,
    val profile: ProfileResponse? = null,
    val error: String? = null,
)

data class PostDetailUiState(
    val isLoading: Boolean = false,
    val post: PostItem? = null,
    val comments: List<CommentItem> = emptyList(),
    val error: String? = null,
    val isCommentSending: Boolean = false,
)

enum class RootDestination {
    Splash,
    AuthChoice,
    Login,
    Register,
    Main,
}

enum class MainTab(
    val label: String,
    val feedType: String,
) {
    Explore("Explorar", "explore"),
    Following("Siguiendo", "following"),
    Profile("Mi perfil", "me"),
}
