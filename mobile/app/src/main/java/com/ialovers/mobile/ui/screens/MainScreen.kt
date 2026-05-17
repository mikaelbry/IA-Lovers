package com.ialovers.mobile.ui.screens

import android.net.Uri
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.ui.Alignment
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AddBox
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.Badge
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.ialovers.mobile.FeedUiState
import com.ialovers.mobile.CreatePostUiState
import com.ialovers.mobile.MainTab
import com.ialovers.mobile.NotificationsUiState
import com.ialovers.mobile.PostDetailUiState
import com.ialovers.mobile.ProfileUiState
import com.ialovers.mobile.SettingsSection
import com.ialovers.mobile.SettingsUiState
import com.ialovers.mobile.data.PostItem

@Composable
fun MainScreen(
    selectedTab: MainTab,
    activePostId: Int?,
    activeUserProfileUsername: String?,
    isSettingsOpen: Boolean,
    exploreState: FeedUiState,
    exploreSearchQuery: String,
    followingState: FeedUiState,
    profileState: ProfileUiState,
    viewedProfileState: ProfileUiState,
    postDetailState: PostDetailUiState,
    settingsState: SettingsUiState,
    createPostState: CreatePostUiState,
    notificationsState: NotificationsUiState,
    notificationsUnreadCount: Int,
    onSelectTab: (MainTab) -> Unit,
    onOpenSettings: (SettingsSection) -> Unit,
    onCloseSettings: () -> Unit,
    onRefreshFeed: (MainTab) -> Unit,
    onLoadMoreFeed: (MainTab) -> Unit,
    onExploreSearchQueryChange: (String) -> Unit,
    onRefreshProfile: () -> Unit,
    onOpenPost: (Int) -> Unit,
    onClosePost: () -> Unit,
    onOpenUserProfile: (String) -> Unit,
    onCloseUserProfile: () -> Unit,
    onToggleLike: (PostItem) -> Unit,
    onDeletePost: (PostItem) -> Unit,
    onToggleFollow: (String) -> Unit,
    onCreateComment: (String) -> Unit,
    onEnterCommentThread: (Int) -> Unit,
    onLeaveCommentThread: () -> Unit,
    onSelectCreatePostImage: (Uri?) -> Unit,
    onPublishPost: (String, String, List<String>) -> Unit,
    onUpdateAvatar: (Uri) -> Unit,
    onUpdateUsername: (String, String) -> Unit,
    onStartEmailChange: (String, String) -> Unit,
    onVerifyEmailChange: (String) -> Unit,
    onResendEmailChange: () -> Unit,
    onCancelEmailChange: () -> Unit,
    onUpdatePassword: (String, String, String) -> Unit,
    onRequestDeleteConfirmation: (String) -> Unit,
    onDeleteAccount: (String) -> Unit,
    onLogout: () -> Unit,
) {
    val exploreListState = rememberLazyListState()
    val followingListState = rememberLazyListState()
    val currentUsername = profileState.profile?.user?.username

    Scaffold(
        modifier = Modifier.statusBarsPadding(),
        topBar = {
            when {
                activePostId != null -> {}
                activeUserProfileUsername != null -> {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 8.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(
                            text = "Perfil",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.padding(horizontal = 10.dp),
                        )
                        Spacer(Modifier.weight(1f))
                        TextButton(onClick = onCloseUserProfile) {
                            Text("Volver")
                        }
                    }
                }
                else -> AppHeader(
                    title = selectedTab.label,
                    searchQuery = if (selectedTab == MainTab.Explore) exploreSearchQuery else null,
                    onSearchQueryChange = if (selectedTab == MainTab.Explore) onExploreSearchQueryChange else null,
                )
            }
        },
        bottomBar = {
            if (activePostId == null && activeUserProfileUsername == null) {
                BottomNavigation(
                    selectedTab = selectedTab,
                    onSelectTab = onSelectTab,
                    onOpenSettings = onOpenSettings,
                    onLogout = onLogout,
                    unreadCount = notificationsUnreadCount,
                )
            }
        },
        containerColor = MaterialTheme.colorScheme.background,
    ) { innerPadding ->
        if (activePostId != null) {
            PostDetailScreen(
                state = postDetailState,
                onBack = onClosePost,
                onToggleLike = onToggleLike,
                onCreateComment = onCreateComment,
                onEnterCommentThread = onEnterCommentThread,
                onLeaveCommentThread = onLeaveCommentThread,
                onOpenUserProfile = onOpenUserProfile,
                currentUsername = currentUsername,
                onDeletePost = onDeletePost,
                modifier = Modifier.padding(innerPadding),
            )
            return@Scaffold
        }

        if (activeUserProfileUsername != null) {
            ProfileScreen(
                state = viewedProfileState,
                onRefresh = {},
                onOpenSettings = {},
                onOpenPost = onOpenPost,
                onOpenUserProfile = onOpenUserProfile,
                onToggleLike = onToggleLike,
                onToggleFollow = onToggleFollow,
                currentUsername = currentUsername,
                onDeletePost = onDeletePost,
                showSettings = false,
                modifier = Modifier.padding(innerPadding),
            )
            return@Scaffold
        }

        if (isSettingsOpen) {
            SettingsScreen(
                state = settingsState,
                onBack = onCloseSettings,
                onSelectSection = onOpenSettings,
                onUpdateAvatar = onUpdateAvatar,
                onUpdateUsername = onUpdateUsername,
                onStartEmailChange = onStartEmailChange,
                onVerifyEmailChange = onVerifyEmailChange,
                onResendEmailChange = onResendEmailChange,
                onCancelEmailChange = onCancelEmailChange,
                onUpdatePassword = onUpdatePassword,
                onRequestDeleteConfirmation = onRequestDeleteConfirmation,
                onDeleteAccount = onDeleteAccount,
                onLogout = onLogout,
                modifier = Modifier.padding(innerPadding),
            )
            return@Scaffold
        }

        when (selectedTab) {
            MainTab.Explore -> FeedScreen(
                state = exploreState,
                listState = exploreListState,
                emptyText = "Todavia no hay publicaciones para explorar.",
                onRefresh = { onRefreshFeed(MainTab.Explore) },
                onLoadMore = { onLoadMoreFeed(MainTab.Explore) },
                onOpenPost = onOpenPost,
                onOpenUserProfile = onOpenUserProfile,
                onToggleLike = onToggleLike,
                currentUsername = currentUsername,
                onDeletePost = onDeletePost,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Following -> FeedScreen(
                state = followingState,
                listState = followingListState,
                emptyText = "Cuando sigas a otros usuarios, sus publicaciones apareceran aqui.",
                onRefresh = { onRefreshFeed(MainTab.Following) },
                onLoadMore = { onLoadMoreFeed(MainTab.Following) },
                onOpenPost = onOpenPost,
                onOpenUserProfile = onOpenUserProfile,
                onToggleLike = onToggleLike,
                currentUsername = currentUsername,
                onDeletePost = onDeletePost,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Create -> CreatePostScreen(
                state = createPostState,
                onSelectImage = onSelectCreatePostImage,
                onPublish = onPublishPost,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Notifications -> NotificationsScreen(
                state = notificationsState,
                onRefresh = { onRefreshFeed(MainTab.Notifications) },
                onOpenPost = onOpenPost,
                onOpenUserProfile = onOpenUserProfile,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Profile -> ProfileScreen(
                state = profileState,
                onRefresh = onRefreshProfile,
                onOpenSettings = { onOpenSettings(SettingsSection.Account) },
                onOpenPost = onOpenPost,
                onOpenUserProfile = onOpenUserProfile,
                onToggleLike = onToggleLike,
                currentUsername = currentUsername,
                onDeletePost = onDeletePost,
                modifier = Modifier.padding(innerPadding),
            )
        }
    }
}

@Composable
private fun AppHeader(
    title: String,
    searchQuery: String? = null,
    onSearchQueryChange: ((String) -> Unit)? = null,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 18.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            modifier = Modifier.weight(0.42f),
        )

        if (searchQuery != null && onSearchQueryChange != null) {
            SearchField(
                query = searchQuery,
                onQueryChange = onSearchQueryChange,
                modifier = Modifier
                    .weight(1f)
                    .height(48.dp),
            )
        }
    }
}

@Composable
private fun SearchField(
    query: String,
    onQueryChange: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    val textStyle = MaterialTheme.typography.bodyLarge.copy(
        color = MaterialTheme.colorScheme.onSurface,
        fontSize = 16.sp,
    )

    BasicTextField(
        value = query,
        onValueChange = onQueryChange,
        singleLine = true,
        textStyle = textStyle,
        cursorBrush = SolidColor(MaterialTheme.colorScheme.primary),
        modifier = modifier
            .background(
                color = MaterialTheme.colorScheme.surfaceVariant,
                shape = CircleShape,
            )
            .padding(start = 18.dp, end = 8.dp),
        decorationBox = { innerTextField ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                Box(
                    modifier = Modifier.weight(1f),
                    contentAlignment = Alignment.CenterStart,
                ) {
                    if (query.isBlank()) {
                        Text(
                            text = "Buscar",
                            style = textStyle,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            maxLines = 1,
                        )
                    }
                    innerTextField()
                }

                if (query.isNotBlank()) {
                    IconButton(
                        onClick = { onQueryChange("") },
                        modifier = Modifier.size(36.dp),
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.Close,
                            contentDescription = "Limpiar busqueda",
                            modifier = Modifier.size(20.dp),
                        )
                    }
                } else {
                    Icon(
                        imageVector = Icons.Outlined.Search,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier
                            .padding(end = 8.dp)
                            .size(22.dp),
                    )
                }
            }
        },
    )
}

@Composable
@OptIn(ExperimentalFoundationApi::class)
private fun BottomNavigation(
    selectedTab: MainTab,
    onSelectTab: (MainTab) -> Unit,
    onOpenSettings: (SettingsSection) -> Unit,
    onLogout: () -> Unit,
    unreadCount: Int = 0,
) {
    NavigationBar {
        MainTab.entries.forEach { tab ->
            var showProfileMenu by remember { mutableStateOf(false) }
            val isProfile = tab == MainTab.Profile

            NavigationBarItem(
                selected = selectedTab == tab,
                onClick = { onSelectTab(tab) },
                modifier = if (isProfile) {
                    Modifier.combinedClickable(
                        onClick = { onSelectTab(tab) },
                        onLongClick = { showProfileMenu = true },
                    )
                } else {
                    Modifier
                },
                icon = {
                    if (tab == MainTab.Notifications) {
                        BadgedBox(
                            badge = {
                                if (unreadCount > 0) {
                                    Badge {
                                        Text(
                                            text = if (unreadCount > 9) "9+" else unreadCount.toString(),
                                        )
                                    }
                                }
                            },
                        ) {
                            Icon(
                                imageVector = tab.icon,
                                contentDescription = tab.label,
                            )
                        }
                    } else {
                        Icon(
                            imageVector = tab.icon,
                            contentDescription = tab.label,
                        )
                    }
                },
                label = {
                    Text(
                        text = tab.label,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        fontSize = 9.sp,
                    )
                },
            )

            if (isProfile) {
                DropdownMenu(
                    expanded = showProfileMenu,
                    onDismissRequest = { showProfileMenu = false },
                ) {
                    DropdownMenuItem(
                        text = { Text("Ajustes") },
                        onClick = {
                            showProfileMenu = false
                            onOpenSettings(SettingsSection.Account)
                        },
                    )
                    DropdownMenuItem(
                        text = { Text("Cerrar sesion") },
                        onClick = {
                            showProfileMenu = false
                            onLogout()
                        },
                    )
                }
            }
        }
    }
}

private val MainTab.icon: androidx.compose.ui.graphics.vector.ImageVector
    get() = when (this) {
        MainTab.Explore -> Icons.Outlined.Search
        MainTab.Following -> Icons.Outlined.Group
        MainTab.Create -> Icons.Outlined.AddBox
        MainTab.Notifications -> Icons.Outlined.Notifications
        MainTab.Profile -> Icons.Outlined.Person
    }
