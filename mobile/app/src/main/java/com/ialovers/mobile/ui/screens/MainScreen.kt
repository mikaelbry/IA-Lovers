package com.ialovers.mobile.ui.screens

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.ialovers.mobile.FeedUiState
import com.ialovers.mobile.MainTab
import com.ialovers.mobile.PostDetailUiState
import com.ialovers.mobile.ProfileUiState
import com.ialovers.mobile.data.PostItem

@Composable
fun MainScreen(
    selectedTab: MainTab,
    activePostId: Int?,
    exploreState: FeedUiState,
    followingState: FeedUiState,
    profileState: ProfileUiState,
    postDetailState: PostDetailUiState,
    onSelectTab: (MainTab) -> Unit,
    onRefreshFeed: (MainTab) -> Unit,
    onLoadMoreFeed: (MainTab) -> Unit,
    onRefreshProfile: () -> Unit,
    onOpenPost: (Int) -> Unit,
    onClosePost: () -> Unit,
    onToggleLike: (PostItem) -> Unit,
    onCreateComment: (String) -> Unit,
    onLogout: () -> Unit,
) {
    Scaffold(
        modifier = Modifier.statusBarsPadding(),
        topBar = {
            AppHeader(
                title = if (activePostId != null) "IA Lovers" else selectedTab.label,
            )
        },
        bottomBar = {
            if (activePostId == null) {
                BottomNavigation(
                    selectedTab = selectedTab,
                    onSelectTab = onSelectTab,
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
                onToggleLike = onToggleLike,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Following -> FeedScreen(
                state = followingState,
                emptyText = "Cuando sigas a otros usuarios, sus publicaciones apareceran aqui.",
                onRefresh = { onRefreshFeed(MainTab.Following) },
                onLoadMore = { onLoadMoreFeed(MainTab.Following) },
                onOpenPost = onOpenPost,
                onToggleLike = onToggleLike,
                modifier = Modifier.padding(innerPadding),
            )

            MainTab.Profile -> ProfileScreen(
                state = profileState,
                onRefresh = onRefreshProfile,
                onLogout = onLogout,
                onOpenPost = onOpenPost,
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
private fun BottomNavigation(
    selectedTab: MainTab,
    onSelectTab: (MainTab) -> Unit,
) {
    NavigationBar {
        MainTab.entries.forEach { tab ->
            NavigationBarItem(
                selected = selectedTab == tab,
                onClick = { onSelectTab(tab) },
                icon = {
                    Icon(
                        imageVector = tab.icon,
                        contentDescription = tab.label,
                    )
                },
                label = { Text(tab.label) },
            )
        }
    }
}

private val MainTab.icon: androidx.compose.ui.graphics.vector.ImageVector
    get() = when (this) {
        MainTab.Explore -> Icons.Outlined.Search
        MainTab.Following -> Icons.Outlined.Group
        MainTab.Profile -> Icons.Outlined.Person
    }
