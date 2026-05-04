package com.ialovers.mobile.ui.screens

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.ialovers.mobile.ProfileUiState
import com.ialovers.mobile.data.FollowUser
import com.ialovers.mobile.data.PostItem
import com.ialovers.mobile.ui.components.Avatar
import com.ialovers.mobile.ui.components.PostCard

@Composable
fun ProfileScreen(
    state: ProfileUiState,
    onRefresh: () -> Unit,
    onOpenSettings: () -> Unit,
    onOpenPost: (Int) -> Unit,
    onOpenUserProfile: (String) -> Unit,
    onToggleLike: (PostItem) -> Unit,
    showSettings: Boolean = true,
    onBack: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    val profile = state.profile
    var openFollowList by remember { mutableStateOf<FollowListType?>(null) }

    openFollowList?.let { type ->
        FollowListDialog(
            title = type.title,
            users = if (type == FollowListType.Followers) state.followers else state.following,
            emptyText = if (type == FollowListType.Followers) {
                "Todavia no tienes seguidores."
            } else {
                "Todavia no sigues a nadie."
            },
            onOpenUserProfile = onOpenUserProfile,
            onDismiss = { openFollowList = null },
        )
    }

    when {
        state.isLoading && profile == null -> {
            Box(
                modifier = modifier.fillMaxSize(),
                contentAlignment = Alignment.Center,
            ) {
                CircularProgressIndicator()
            }
        }

        state.error != null && profile == null -> {
            Box(
                modifier = modifier
                    .fillMaxSize()
                    .padding(24.dp),
                contentAlignment = Alignment.Center,
            ) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    Text(
                        text = state.error,
                        textAlign = TextAlign.Center,
                    )
                    Button(onClick = onRefresh) {
                        Text("Reintentar")
                    }
                }
            }
        }

        profile != null -> {
            LazyColumn(
                modifier = modifier.fillMaxSize(),
                contentPadding = PaddingValues(bottom = 8.dp),
            ) {
                item {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.surface,
                        ),
                    ) {
                        Column(
                            modifier = Modifier.padding(18.dp),
                            verticalArrangement = Arrangement.spacedBy(16.dp),
                        ) {
                            if (onBack != null) {
                                TextButton(onClick = onBack) {
                                    Text("Volver")
                                }
                            }

                            Row(
                                horizontalArrangement = Arrangement.spacedBy(14.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Avatar(
                                    url = profile.user.avatarUrl,
                                    label = profile.user.username,
                                )
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(
                                        text = profile.user.username,
                                        style = MaterialTheme.typography.titleLarge,
                                        fontWeight = FontWeight.Bold,
                                    )
                                }
                                if (showSettings) {
                                    IconButton(onClick = onOpenSettings) {
                                        Icon(
                                            imageVector = Icons.Outlined.Settings,
                                            contentDescription = "Ajustes",
                                        )
                                    }
                                }
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(18.dp),
                            ) {
                                ProfileStat(
                                    label = "Seguidores",
                                    value = state.followers.size.toString(),
                                    onClick = { openFollowList = FollowListType.Followers },
                                )
                                ProfileStat(
                                    label = "Siguiendo",
                                    value = state.following.size.toString(),
                                    onClick = { openFollowList = FollowListType.Following },
                                )
                                ProfileStat(label = "Posts", value = profile.posts.size.toString())
                            }
                        }
                    }
                }

                if (profile.posts.isEmpty()) {
                    item {
                        Text(
                            text = "Todavia no tienes publicaciones.",
                            modifier = Modifier.padding(24.dp),
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                } else {
                    items(profile.posts, key = { it.id }) { post ->
                        PostCard(
                            post = post.copy(username = profile.user.username, avatarUrl = profile.user.avatarUrl),
                            onOpen = onOpenPost,
                            onOpenAuthor = onOpenUserProfile,
                            onToggleLike = onToggleLike,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun ProfileStat(
    label: String,
    value: String,
    onClick: (() -> Unit)? = null,
) {
    Column(
        modifier = if (onClick != null) {
            Modifier.clickable(onClick = onClick)
        } else {
            Modifier
        },
    ) {
        Text(
            text = value,
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold,
        )
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun FollowListDialog(
    title: String,
    users: List<FollowUser>,
    emptyText: String,
    onOpenUserProfile: (String) -> Unit,
    onDismiss: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        text = {
            if (users.isEmpty()) {
                Text(
                    text = emptyText,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            } else {
                LazyColumn(
                    modifier = Modifier.heightIn(max = 360.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    items(users, key = { it.username }) { user ->
                        FollowUserRow(
                            user = user,
                            onClick = {
                                onDismiss()
                                onOpenUserProfile(user.username)
                            },
                        )
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text("Cerrar")
            }
        },
    )
}

@Composable
private fun FollowUserRow(
    user: FollowUser,
    onClick: () -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Avatar(url = user.avatarUrl, label = user.username)
        Text(
            text = user.username,
            style = MaterialTheme.typography.bodyLarge,
            fontWeight = FontWeight.SemiBold,
        )
    }
}

private enum class FollowListType(
    val title: String,
) {
    Followers("Seguidores"),
    Following("Siguiendo"),
}
