package com.ialovers.mobile.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.ialovers.mobile.NotificationsUiState
import com.ialovers.mobile.data.NotificationItem
import java.time.Duration
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter

@Composable
fun NotificationsScreen(
    state: NotificationsUiState,
    onRefresh: () -> Unit,
    onOpenPost: (Int) -> Unit,
    onOpenUserProfile: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    when {
        state.isLoading && state.notifications.isEmpty() -> {
            Box(
                modifier = modifier.fillMaxSize(),
                contentAlignment = Alignment.Center,
            ) {
                CircularProgressIndicator()
            }
        }

        state.error != null && state.notifications.isEmpty() -> {
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

        state.notifications.isEmpty() -> {
            Box(
                modifier = modifier
                    .fillMaxSize()
                    .padding(24.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    text = "No tienes notificaciones nuevas.",
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                )
            }
        }

        else -> {
            LazyColumn(modifier = modifier.fillMaxSize()) {
                items(state.notifications, key = { it.id }) { notification ->
                    NotificationItemView(
                        notification = notification,
                        onOpenPost = onOpenPost,
                        onOpenUserProfile = onOpenUserProfile,
                    )
                    HorizontalDivider(color = MaterialTheme.colorScheme.surfaceVariant)
                }
            }
        }
    }
}

@Composable
private fun NotificationItemView(
    notification: NotificationItem,
    onOpenPost: (Int) -> Unit,
    onOpenUserProfile: (String) -> Unit,
) {
    val bgColor = if (!notification.isRead) {
        MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.3f)
    } else {
        MaterialTheme.colorScheme.surface
    }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(bgColor)
            .clickable {
                if (notification.postId != null) {
                    onOpenPost(notification.postId)
                } else if (!notification.fromUsername.isNullOrBlank()) {
                    onOpenUserProfile(notification.fromUsername)
                }
            }
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        NotificationAvatar(
            avatarUrl = notification.fromAvatarUrl,
            username = notification.fromUsername,
            onClick = {
                if (!notification.fromUsername.isNullOrBlank()) {
                    onOpenUserProfile(notification.fromUsername)
                }
            },
        )

        Spacer(Modifier.width(12.dp))

        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = notificationText(notification),
                style = MaterialTheme.typography.bodyMedium,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.size(2.dp))
            Text(
                text = relativeTime(notification.createdAt),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        if (notification.postImageUrl != null && notification.postId != null) {
            Spacer(Modifier.width(10.dp))
            PostThumbnail(
                imageUrl = notification.postImageUrl,
                onClick = { onOpenPost(notification.postId) },
            )
        }
    }
}

@Composable
private fun NotificationAvatar(
    avatarUrl: String?,
    username: String?,
    onClick: () -> Unit,
) {
    Box(
        modifier = Modifier
            .size(42.dp)
            .clip(CircleShape)
            .clickable(onClick = onClick)
            .background(MaterialTheme.colorScheme.secondaryContainer),
        contentAlignment = Alignment.Center,
    ) {
        if (avatarUrl.isNullOrBlank()) {
            Text(
                text = username?.firstOrNull()?.uppercase() ?: "I",
                color = MaterialTheme.colorScheme.onSecondaryContainer,
                fontWeight = FontWeight.Bold,
                fontSize = 16.sp,
            )
        } else {
            val context = LocalContext.current
            AsyncImage(
                model = ImageRequest.Builder(context)
                    .data(avatarUrl)
                    .crossfade(false)
                    .size(128)
                    .build(),
                contentDescription = "Avatar de ${username ?: "usuario"}",
                modifier = Modifier
                    .size(42.dp)
                    .clip(CircleShape),
                contentScale = ContentScale.Crop,
            )
        }
    }
}

@Composable
private fun PostThumbnail(
    imageUrl: String,
    onClick: () -> Unit,
) {
    val context = LocalContext.current
    Box(
        modifier = Modifier
            .size(48.dp)
            .clip(RoundedCornerShape(8.dp))
            .clickable(onClick = onClick)
            .background(MaterialTheme.colorScheme.surfaceVariant),
    ) {
        AsyncImage(
            model = ImageRequest.Builder(context)
                .data(imageUrl)
                .crossfade(false)
                .size(128)
                .build(),
            contentDescription = "Miniatura del post",
            modifier = Modifier
                .fillMaxSize()
                .clip(RoundedCornerShape(8.dp)),
            contentScale = ContentScale.Crop,
        )
    }
}

private fun notificationText(item: NotificationItem): String {
    val username = item.fromUsername ?: "Alguien"
    return when (item.type) {
        "follow" -> "$username ha empezado a seguirte."
        "like" -> "$username le ha dado me gusta a tu post."
        "comment" -> "$username ha comentado tu post."
        "reply" -> "$username ha respondido en un post."
        else -> "$username tiene una novedad para ti."
    }
}

private fun relativeTime(createdAt: String?): String {
    if (createdAt.isNullOrBlank()) return ""
    return try {
        val instant = Instant.parse(createdAt)
        val duration = Duration.between(instant, Instant.now())
        val seconds = duration.seconds.coerceAtLeast(0)

        when {
            seconds < 60 -> "Ahora"
            seconds < 3600 -> "${seconds / 60} min"
            seconds < 86400 -> "${seconds / 3600} h"
            seconds < 604800 -> "${seconds / 86400} d"
            else -> {
                val localDate = instant.atZone(ZoneId.systemDefault()).toLocalDate()
                localDate.format(DateTimeFormatter.ofPattern("dd/MM"))
            }
        }
    } catch (_: Exception) {
        ""
    }
}
