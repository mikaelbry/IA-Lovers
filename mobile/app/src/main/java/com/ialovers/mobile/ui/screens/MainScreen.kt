package com.ialovers.mobile.ui.screens

import android.net.Uri
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AddBox
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.Icon
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.ialovers.mobile.FeedUiState
import com.ialovers.mobile.CreatePostUiState
import com.ialovers.mobile.MainTab
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
    followingState: FeedUiState,
    profileState: ProfileUiState,
    viewedProfileState: ProfileUiState,
    postDetailState: PostDetailUiState,
    settingsState: SettingsUiState,
    createPostState: CreatePostUiState,
    onSelectTab: (MainTab) -> Unit,
    onOpenSettings: (SettingsSection) -> Unit,
    onCloseSettings: () -> Unit,
    onRefreshFeed: (MainTab) -> Unit,
    onLoadMoreFeed: (MainTab) -> Unit,
    onRefreshProfile: () -> Unit,
    onOpenPost: (Int) -> Unit,
    onClosePost: () -> Unit,
    onOpenUserProfile: (String) -> Unit,
    onCloseUserProfile: () -> Unit,
    onToggleLike: (PostItem) -> Unit,
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
    Scaffold(
        modifier = Modifier.statusBarsPadding(),
        topBar = {
            AppHeader(
                title = when {
                    activePostId != null -> "IA Lovers"
                    activeUserProfileUsername != null -> "Perfil"
                    else -> selectedTab.label
                },
            )
        },
        bottomBar = {
            if (activePostId == null && activeUserProfileUsername == null) {
                BottomNavigation(
                    selectedTab = selectedTab,
                    onSelectTab = onSelectTab,
                    onOpenSettings = onOpenSettings,
                    onLogout = onLogout,
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
                showSettings = false,
                onBack = onCloseUserProfile,
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
                emptyText = "Todavia no hay publicaciones para explorar.",
                onRefresh = { onRefreshFeed(MainTab.Explore) },
                onLoadMore = { onLoadMoreFeed(MainTab.Explore) },
                onOpenPost = onOpenPost,
                onOpenUserProfile = onOpenUserProfile,
                onToggleLike = onToggleLike,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Following -> FeedScreen(
                state = followingState,
                emptyText = "Cuando sigas a otros usuarios, sus publicaciones apareceran aqui.",
                onRefresh = { onRefreshFeed(MainTab.Following) },
                onLoadMore = { onLoadMoreFeed(MainTab.Following) },
                onOpenPost = onOpenPost,
                onOpenUserProfile = onOpenUserProfile,
                onToggleLike = onToggleLike,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Create -> CreatePostScreen(
                state = createPostState,
                onSelectImage = onSelectCreatePostImage,
                onPublish = onPublishPost,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Profile -> ProfileScreen(
                state = profileState,
                onRefresh = onRefreshProfile,
                onOpenSettings = { onOpenSettings(SettingsSection.Account) },
                onOpenPost = onOpenPost,
                onOpenUserProfile = onOpenUserProfile,
                onToggleLike = onToggleLike,
                modifier = Modifier.padding(innerPadding),
            )
        }
    }
}

@Composable
private fun AppHeader(title: String) {
    Text(
        text = title,
        modifier = Modifier.padding(horizontal = 18.dp, vertical = 14.dp),
        style = MaterialTheme.typography.titleLarge,
    )
}

@Composable
@OptIn(ExperimentalFoundationApi::class)
private fun BottomNavigation(
    selectedTab: MainTab,
    onSelectTab: (MainTab) -> Unit,
    onOpenSettings: (SettingsSection) -> Unit,
    onLogout: () -> Unit,
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
                    Icon(
                        imageVector = tab.icon,
                        contentDescription = tab.label,
                    )
                },
                label = { Text(tab.label) },
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
        MainTab.Profile -> Icons.Outlined.Person
    }
