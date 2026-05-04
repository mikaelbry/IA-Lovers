package com.ialovers.mobile

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
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
import com.ialovers.mobile.data.DeleteAccountRequest
import com.ialovers.mobile.data.FlowTokenRequest
import com.ialovers.mobile.data.FollowUser
import com.ialovers.mobile.data.LoginRequest
import com.ialovers.mobile.data.PostItem
import com.ialovers.mobile.data.ProfileResponse
import com.ialovers.mobile.data.RegisterStartRequest
import com.ialovers.mobile.data.RegisterVerifyRequest
import com.ialovers.mobile.data.SessionStorage
import com.ialovers.mobile.data.SettingsSummaryResponse
import com.ialovers.mobile.data.StartEmailChangeRequest
import com.ialovers.mobile.data.ToggleLikeRequest
import com.ialovers.mobile.data.UpdateProfileRequest
import com.ialovers.mobile.data.VerifyEmailChangeRequest
import java.io.IOException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.HttpException

class AppViewModel(
    context: Context,
) : ViewModel() {
    private val appContext = context.applicationContext
    private val api: ApiService
    private val sessionStorage: SessionStorage
    private val json = Json { ignoreUnknownKeys = true }

    var rootDestination by mutableStateOf(RootDestination.Splash)
        private set

    var selectedTab by mutableStateOf(MainTab.Explore)
        private set

    var activePostId by mutableStateOf<Int?>(null)
        private set

    var activeUserProfileUsername by mutableStateOf<String?>(null)
        private set

    var isSettingsOpen by mutableStateOf(false)
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

    var viewedProfileState by mutableStateOf(ProfileUiState())
        private set

    var postDetailState by mutableStateOf(PostDetailUiState())
        private set

    var settingsState by mutableStateOf(SettingsUiState(isLoading = true))
        private set

    var createPostState by mutableStateOf(CreatePostUiState())
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
        activeUserProfileUsername = null
        isSettingsOpen = false
        selectedTab = tab

        when (tab) {
            MainTab.Explore -> if (exploreState.posts.isEmpty()) refreshFeed(MainTab.Explore)
            MainTab.Following -> if (followingState.posts.isEmpty()) refreshFeed(MainTab.Following)
            MainTab.Create -> Unit
            MainTab.Profile -> if (profileState.profile == null) refreshProfile()
        }
    }

    fun openPost(postId: Int) {
        isSettingsOpen = false
        activePostId = postId
        loadPostDetail(postId)
    }

    fun closePost() {
        activePostId = null
        postDetailState = PostDetailUiState()
    }

    fun openUserProfile(username: String) {
        val cleanUsername = username.trim()
        if (cleanUsername.isBlank()) return

        val currentUsername = profileState.profile?.user?.username
        if (currentUsername != null && cleanUsername.equals(currentUsername, ignoreCase = true)) {
            activePostId = null
            activeUserProfileUsername = null
            isSettingsOpen = false
            selectedTab = MainTab.Profile
            if (profileState.profile == null) refreshProfile()
            return
        }

        activePostId = null
        isSettingsOpen = false
        activeUserProfileUsername = cleanUsername
        loadViewedProfile(cleanUsername)
    }

    fun closeUserProfile() {
        activeUserProfileUsername = null
        viewedProfileState = ProfileUiState()
    }

    fun openSettings(section: SettingsSection = SettingsSection.Account) {
        activePostId = null
        selectedTab = MainTab.Profile
        isSettingsOpen = true
        settingsState = settingsState.copy(activeSection = section, statusMessage = null, error = null)
        loadSettings()
    }

    fun closeSettings() {
        isSettingsOpen = false
        settingsState = settingsState.copy(statusMessage = null, error = null)
        refreshProfile()
    }

    fun selectSettingsSection(section: SettingsSection) {
        settingsState = settingsState.copy(
            activeSection = section,
            statusMessage = null,
            error = null,
            emailChange = if (section == SettingsSection.Email) settingsState.emailChange else EmailChangeUiState(),
            deleteConfirmStep = if (section == SettingsSection.Delete) settingsState.deleteConfirmStep else false,
        )
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
        val parentId = postDetailState.commentThread.lastOrNull()

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
                        parentId = parentId,
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

    fun enterCommentThread(commentId: Int) {
        postDetailState = postDetailState.copy(
            commentThread = postDetailState.commentThread + commentId,
        )
    }

    fun leaveCommentThread() {
        postDetailState = postDetailState.copy(
            commentThread = postDetailState.commentThread.dropLast(1),
        )
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
        viewedProfileState = ProfileUiState()
        postDetailState = PostDetailUiState()
        settingsState = SettingsUiState(isLoading = false)
        createPostState = CreatePostUiState()
        activePostId = null
        activeUserProfileUsername = null
        isSettingsOpen = false
        selectedTab = MainTab.Explore
        rootDestination = RootDestination.AuthChoice

        viewModelScope.launch {
            try {
                api.mobileLogout(authorization)
            } catch (_: Throwable) {
            }
        }
    }

    fun setCreatePostImage(uri: Uri?) {
        createPostState = CreatePostUiState(imageUri = uri)
    }

    fun publishPost(
        title: String,
        description: String,
        tags: List<String>,
    ) {
        val imageUri = createPostState.imageUri

        if (imageUri == null) {
            createPostState = createPostState.copy(error = "Selecciona una imagen para publicar.")
            return
        }

        val cleanTitle = title.trim()
        val cleanDescription = description.trim()
        val cleanTags = tags.map { it.trim().trimStart('#') }.filter { it.isNotBlank() }.distinctBy { it.lowercase() }

        if (cleanTitle.length > 80) {
            createPostState = createPostState.copy(error = "El titulo no puede superar 80 caracteres.")
            return
        }

        if (cleanDescription.length > 500) {
            createPostState = createPostState.copy(error = "La descripcion no puede superar 500 caracteres.")
            return
        }

        if (cleanTags.size > 4 || cleanTags.any { it.length > 24 }) {
            createPostState = createPostState.copy(error = "Puedes usar hasta 4 hashtags de 24 caracteres.")
            return
        }

        viewModelScope.launch {
            createPostState = createPostState.copy(isPublishing = true, error = null, statusMessage = null)

            try {
                val image = imagePart(imageUri, "image")
                val response = api.createPost(
                    image = image,
                    title = cleanTitle.toRequestBody("text/plain".toMediaTypeOrNull()),
                    description = cleanDescription.toRequestBody("text/plain".toMediaTypeOrNull()),
                    tags = Json.encodeToString(cleanTags).toRequestBody("text/plain".toMediaTypeOrNull()),
                )

                createPostState = CreatePostUiState(statusMessage = "Publicacion creada.")
                selectedTab = MainTab.Explore
                refreshFeed(MainTab.Explore)
                refreshProfile()
                openPost(response.id)
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    createPostState = createPostState.copy(
                        isPublishing = false,
                        error = errorMessage(error),
                    )
                }
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
        activeUserProfileUsername = null
        isSettingsOpen = false
        refreshFeed(MainTab.Explore)
        refreshProfile()
    }

    fun updateUsername(newUsername: String, currentPassword: String) {
        val summary = settingsState.summary ?: return
        val username = newUsername.trim()

        if (username.isBlank() || currentPassword.isBlank()) {
            settingsState = settingsState.copy(error = "Introduce el nuevo usuario y tu contrasena actual.")
            return
        }

        viewModelScope.launch {
            settingsState = settingsState.copy(isSaving = true, error = null)

            try {
                val available = api.checkUsername(username).available
                if (!available) {
                    settingsState = settingsState.copy(isSaving = false, error = "Este nombre de usuario ya esta cogido.")
                    return@launch
                }

                api.updateProfile(
                    UpdateProfileRequest(
                        username = username,
                        email = summary.user.email.orEmpty(),
                        currentPassword = currentPassword,
                    )
                )

                loadSettingsInternal("Nombre de usuario actualizado correctamente.")
                refreshProfile()
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    settingsState = settingsState.copy(isSaving = false, error = errorMessage(error))
                }
            }
        }
    }

    fun updatePassword(currentPassword: String, password: String, confirmation: String) {
        val summary = settingsState.summary ?: return

        if (currentPassword.isBlank() || password.isBlank() || confirmation.isBlank()) {
            settingsState = settingsState.copy(error = "Completa todos los campos.")
            return
        }

        if (password != confirmation) {
            settingsState = settingsState.copy(error = "Las contrasenas no coinciden.")
            return
        }

        if (password.length < 8 || !password.any { it.isLetter() } || !password.any { it.isDigit() }) {
            settingsState = settingsState.copy(error = "La contrasena debe tener al menos 8 caracteres e incluir letras y numeros.")
            return
        }

        viewModelScope.launch {
            settingsState = settingsState.copy(isSaving = true, error = null)

            try {
                api.updateProfile(
                    UpdateProfileRequest(
                        username = summary.user.username,
                        email = summary.user.email.orEmpty(),
                        password = password,
                        currentPassword = currentPassword,
                    )
                )

                settingsState = settingsState.copy(
                    isSaving = false,
                    statusMessage = "Contrasena actualizada correctamente.",
                    error = null,
                )
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    settingsState = settingsState.copy(isSaving = false, error = errorMessage(error))
                }
            }
        }
    }

    fun updateAvatar(uri: Uri) {
        viewModelScope.launch {
            settingsState = settingsState.copy(isSaving = true, error = null)

            try {
                val part = imagePart(uri, "avatar")
                api.updateAvatar(part)
                loadSettingsInternal("Avatar actualizado correctamente.")
                refreshProfile()
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    settingsState = settingsState.copy(isSaving = false, error = errorMessage(error))
                }
            }
        }
    }

    fun startEmailChange(newEmail: String, currentPassword: String) {
        if (newEmail.isBlank() || currentPassword.isBlank()) {
            settingsState = settingsState.copy(error = "Introduce el nuevo correo y tu contrasena actual.")
            return
        }

        viewModelScope.launch {
            settingsState = settingsState.copy(isSaving = true, error = null)

            try {
                val response = api.startEmailChange(
                    StartEmailChangeRequest(
                        newEmail = newEmail.trim(),
                        currentPassword = currentPassword,
                    )
                )

                settingsState = settingsState.copy(
                    isSaving = false,
                    error = null,
                    statusMessage = "Hemos enviado un codigo a ${response.maskedEmail}.",
                    emailChange = EmailChangeUiState(
                        pending = true,
                        newEmail = response.newEmail,
                        maskedEmail = response.maskedEmail,
                        resendCooldown = response.resendCooldown,
                    ),
                )
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    settingsState = settingsState.copy(isSaving = false, error = errorMessage(error))
                }
            }
        }
    }

    fun verifyEmailChange(code: String) {
        if (!Regex("^\\d{6}$").matches(code.trim())) {
            settingsState = settingsState.copy(error = "El codigo debe tener 6 digitos.")
            return
        }

        viewModelScope.launch {
            settingsState = settingsState.copy(isSaving = true, error = null)

            try {
                api.verifyEmailChange(VerifyEmailChangeRequest(code.trim()))
                settingsState = settingsState.copy(emailChange = EmailChangeUiState())
                loadSettingsInternal("Correo actualizado correctamente.")
                refreshProfile()
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    settingsState = settingsState.copy(isSaving = false, error = errorMessage(error))
                }
            }
        }
    }

    fun resendEmailChange() {
        viewModelScope.launch {
            settingsState = settingsState.copy(isSaving = true, error = null)

            try {
                val response = api.resendEmailChange()
                settingsState = settingsState.copy(
                    isSaving = false,
                    statusMessage = "Hemos reenviado un nuevo codigo.",
                    emailChange = settingsState.emailChange.copy(
                        maskedEmail = response.maskedEmail ?: settingsState.emailChange.maskedEmail,
                        resendCooldown = response.resendCooldown,
                    ),
                )
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    settingsState = settingsState.copy(isSaving = false, error = errorMessage(error))
                }
            }
        }
    }

    fun cancelEmailChange() {
        viewModelScope.launch {
            runCatching { api.cancelEmailChange() }
            settingsState = settingsState.copy(
                emailChange = EmailChangeUiState(),
                statusMessage = null,
                error = null,
            )
        }
    }

    fun requestDeleteConfirmation(currentPassword: String) {
        if (currentPassword.isBlank()) {
            settingsState = settingsState.copy(error = "Introduce tu contrasena para continuar.")
            return
        }

        settingsState = settingsState.copy(
            deleteConfirmStep = true,
            deletePassword = currentPassword,
            error = "Escribe ELIMINAR MI CUENTA para confirmar.",
        )
    }

    fun deleteAccount(confirmText: String) {
        if (confirmText.trim() != "ELIMINAR MI CUENTA") {
            settingsState = settingsState.copy(error = "La confirmacion final no coincide.")
            return
        }

        viewModelScope.launch {
            settingsState = settingsState.copy(isSaving = true, error = null)

            try {
                api.deleteAccount(
                    DeleteAccountRequest(
                        currentPassword = settingsState.deletePassword,
                        confirmText = confirmText.trim(),
                    )
                )
                logout()
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    settingsState = settingsState.copy(isSaving = false, error = errorMessage(error))
                }
            }
        }
    }

    private fun loadSettings() {
        viewModelScope.launch {
            loadSettingsInternal()
        }
    }

    private suspend fun loadSettingsInternal(statusMessage: String? = null) {
        settingsState = settingsState.copy(isLoading = true, error = null)

        try {
            val response = api.settingsSummary()
            settingsState = settingsState.copy(
                isLoading = false,
                isSaving = false,
                summary = response,
                statusMessage = statusMessage,
                error = null,
            )
        } catch (error: Throwable) {
            handleAuthenticatedError(error) {
                settingsState = settingsState.copy(
                    isLoading = false,
                    isSaving = false,
                    error = errorMessage(error),
                )
            }
        }
    }

    private suspend fun imagePart(uri: Uri, formName: String): MultipartBody.Part = withContext(Dispatchers.IO) {
        val resolver = appContext.contentResolver
        val mimeType = resolver.getType(uri) ?: "application/octet-stream"

        if (mimeType !in setOf("image/jpeg", "image/png", "image/webp")) {
            val target = if (formName == "avatar") "avatar" else "imagen"
            throw IllegalArgumentException("La $target debe ser JPG, PNG o WEBP.")
        }

        val bytes = resolver.openInputStream(uri)?.use { it.readBytes() }
            ?: throw IOException("No se pudo leer la imagen seleccionada.")

        val maxBytes = 4 * 1024 * 1024
        if (bytes.size > maxBytes) {
            val target = if (formName == "avatar") "avatar" else "imagen"
            throw IllegalArgumentException("La $target no puede superar los 4 MB.")
        }

        val fileName = queryDisplayName(uri) ?: formName
        val body = bytes.toRequestBody(mimeType.toMediaTypeOrNull())
        MultipartBody.Part.createFormData(formName, fileName, body)
    }

    private fun queryDisplayName(uri: Uri): String? {
        return appContext.contentResolver.query(uri, null, null, null, null)?.use { cursor ->
            val index = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (index >= 0 && cursor.moveToFirst()) cursor.getString(index) else null
        }
    }

    private suspend fun loadProfileInternal() {
        profileState = profileState.copy(isLoading = true, error = null)

        try {
            val profileData = coroutineScope {
                val profile = async { api.userProfile() }
                val followers = async { api.followers() }
                val following = async { api.following() }

                ProfileData(
                    profile = profile.await(),
                    followers = followers.await(),
                    following = following.await(),
                )
            }

            profileState = ProfileUiState(
                isLoading = false,
                profile = profileData.profile,
                followers = profileData.followers,
                following = profileData.following,
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

    private fun loadViewedProfile(username: String) {
        viewModelScope.launch {
            viewedProfileState = ProfileUiState(isLoading = true)

            try {
                val profileData = coroutineScope {
                    val profile = async { api.profileByUsername(username) }
                    val loadedProfile = profile.await()
                    val followers = async { api.followers(loadedProfile.user.id) }
                    val following = async { api.following(loadedProfile.user.id) }

                    ProfileData(
                        profile = loadedProfile,
                        followers = followers.await(),
                        following = following.await(),
                    )
                }

                val currentUsername = profileState.profile?.user?.username
                if (currentUsername != null && profileData.profile.user.username.equals(currentUsername, ignoreCase = true)) {
                    closeUserProfile()
                    selectedTab = MainTab.Profile
                    return@launch
                }

                viewedProfileState = ProfileUiState(
                    isLoading = false,
                    profile = profileData.profile,
                    followers = profileData.followers,
                    following = profileData.following,
                )
            } catch (error: Throwable) {
                handleAuthenticatedError(error) {
                    viewedProfileState = ProfileUiState(
                        isLoading = false,
                        error = errorMessage(error),
                    )
                }
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
            MainTab.Create -> FeedUiState()
            MainTab.Profile -> FeedUiState()
        }
    }

    private fun setFeedState(tab: MainTab, state: FeedUiState) {
        when (tab) {
            MainTab.Explore -> exploreState = state
            MainTab.Following -> followingState = state
            MainTab.Create -> Unit
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
    val followers: List<FollowUser> = emptyList(),
    val following: List<FollowUser> = emptyList(),
    val error: String? = null,
)

private data class ProfileData(
    val profile: ProfileResponse,
    val followers: List<FollowUser>,
    val following: List<FollowUser>,
)

data class PostDetailUiState(
    val isLoading: Boolean = false,
    val post: PostItem? = null,
    val comments: List<CommentItem> = emptyList(),
    val commentThread: List<Int> = emptyList(),
    val error: String? = null,
    val isCommentSending: Boolean = false,
)

data class SettingsUiState(
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val activeSection: SettingsSection = SettingsSection.Account,
    val summary: SettingsSummaryResponse? = null,
    val emailChange: EmailChangeUiState = EmailChangeUiState(),
    val deleteConfirmStep: Boolean = false,
    val deletePassword: String = "",
    val statusMessage: String? = null,
    val error: String? = null,
)

data class CreatePostUiState(
    val imageUri: Uri? = null,
    val isPublishing: Boolean = false,
    val statusMessage: String? = null,
    val error: String? = null,
)

data class EmailChangeUiState(
    val pending: Boolean = false,
    val newEmail: String = "",
    val maskedEmail: String = "",
    val resendCooldown: Int = 30,
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
    Create("Publicar", "create"),
    Profile("Mi perfil", "me"),
}

enum class SettingsSection(
    val label: String,
) {
    Account("Informacion"),
    Avatar("Avatar"),
    Username("Usuario"),
    Email("Correo"),
    Password("Contrasena"),
    Delete("Borrar cuenta"),
    Logout("Cerrar sesion"),
}
