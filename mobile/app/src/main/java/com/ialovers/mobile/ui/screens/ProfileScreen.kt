package com.ialovers.mobile.ui.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.ialovers.mobile.ProfileUiState
import com.ialovers.mobile.data.PostItem
import com.ialovers.mobile.ui.components.Avatar
import com.ialovers.mobile.ui.components.PostCard

@Composable
fun ProfileScreen(
    state: ProfileUiState,
    onRefresh: () -> Unit,
    onLogout: () -> Unit,
    onOpenPost: (Int) -> Unit,
    onToggleLike: (PostItem) -> Unit,
    modifier: Modifier = Modifier,
) {
    val profile = state.profile

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
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(14.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Avatar(
                                    url = profile.user.avatarUrl,
                                    label = profile.user.username,
                                )
                                Column {
                                    Text(
                                        text = profile.user.username,
                                        style = MaterialTheme.typography.titleLarge,
                                        fontWeight = FontWeight.Bold,
                                    )
                                    Text(
                                        text = profile.user.email ?: "",
                                        style = MaterialTheme.typography.bodyMedium,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(18.dp),
                            ) {
                                ProfileStat(label = "Seguidores", value = profile.followers.toString())
                                ProfileStat(label = "Posts", value = profile.posts.size.toString())
                            }

                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                TextButton(onClick = onRefresh) {
                                    Text("Recargar")
                                }
                                Button(onClick = onLogout) {
                                    Text("Salir")
                                }
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
) {
    Column {
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
