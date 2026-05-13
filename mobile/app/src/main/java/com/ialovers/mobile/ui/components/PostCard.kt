package com.ialovers.mobile.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.outlined.FavoriteBorder
import androidx.compose.material.icons.outlined.ModeComment
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.ialovers.mobile.data.PostItem

@Composable
fun PostCard(
    post: PostItem,
    onOpen: (Int) -> Unit,
    onOpenAuthor: (String) -> Unit = {},
    onToggleLike: (PostItem) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(MaterialTheme.colorScheme.surface)
            .clickable { onOpen(post.id) },
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable {
                    post.username?.takeIf { it.isNotBlank() }?.let(onOpenAuthor)
                }
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Avatar(
                url = post.avatarUrl,
                label = post.username.orEmpty(),
            )
            Text(
                text = post.username ?: "usuario",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
            )
        }

        if (!post.filePath.isNullOrBlank()) {
            val context = LocalContext.current
            AsyncImage(
                model = ImageRequest.Builder(context)
                    .data(post.filePath)
                    .crossfade(false)
                    .size(FEED_IMAGE_SIZE_PX)
                    .build(),
                contentDescription = post.title ?: post.description ?: "Publicacion",
                modifier = Modifier
                    .fillMaxWidth()
                    .aspectRatio(1f),
                contentScale = ContentScale.Crop,
            )
        }

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 4.dp, vertical = 2.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            LikeButton(
                liked = post.likedByUser,
                count = post.likesCount,
                onClick = { onToggleLike(post) },
            )

            CommentButton(
                count = post.commentsCount,
                onClick = { onOpen(post.id) },
            )
        }

        PostText(post = post)

        HorizontalDivider(color = MaterialTheme.colorScheme.surfaceVariant)
    }
}

@Composable
private fun RowScope.LikeButton(
    liked: Boolean,
    count: Int,
    onClick: () -> Unit,
) {
    val icon = if (liked) Icons.Filled.Favorite else Icons.Outlined.FavoriteBorder
    val tint = if (liked) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant

    IconButton(onClick = onClick) {
        Icon(
            imageVector = icon,
            contentDescription = if (liked) "Quitar like" else "Dar like",
            tint = tint,
            modifier = Modifier.size(24.dp),
        )
    }
    Text(
        text = count.toString(),
        style = MaterialTheme.typography.bodySmall,
        fontSize = 13.sp,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
    )
}

@Composable
private fun RowScope.CommentButton(
    count: Int,
    onClick: () -> Unit,
) {
    IconButton(onClick = onClick) {
        Icon(
            imageVector = Icons.Outlined.ModeComment,
            contentDescription = "Comentar",
            tint = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.size(24.dp),
        )
    }
    Text(
        text = count.toString(),
        style = MaterialTheme.typography.bodySmall,
        fontSize = 13.sp,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
    )
}

@Composable
fun PostText(
    post: PostItem,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier.padding(horizontal = 16.dp, vertical = 4.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        if (!post.title.isNullOrBlank()) {
            Text(
                text = post.title,
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.SemiBold,
            )
        }

        if (!post.description.isNullOrBlank()) {
            Text(
                text = post.description,
                style = MaterialTheme.typography.bodyMedium,
            )
        }

        if (!post.tags.isNullOrBlank()) {
            Text(
                text = post.tags.split(",").joinToString(" ") { "#${it.trim()}" },
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.primary,
            )
        }
    }
}

@Composable
fun Avatar(
    url: String?,
    label: String,
    modifier: Modifier = Modifier,
) {
    if (url.isNullOrBlank()) {
        Box(
            modifier = modifier
                .size(38.dp)
                .clip(CircleShape)
                .background(MaterialTheme.colorScheme.secondaryContainer),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                text = label.firstOrNull()?.uppercase() ?: "I",
                color = MaterialTheme.colorScheme.onSecondaryContainer,
                fontWeight = FontWeight.Bold,
            )
        }
        return
    }

    val context = LocalContext.current
    AsyncImage(
        model = ImageRequest.Builder(context)
            .data(url)
            .crossfade(false)
            .size(AVATAR_IMAGE_SIZE_PX)
            .build(),
        contentDescription = "Avatar de $label",
        modifier = modifier
            .size(38.dp)
            .clip(CircleShape),
        contentScale = ContentScale.Crop,
    )
}

private const val FEED_IMAGE_SIZE_PX = 1080
private const val AVATAR_IMAGE_SIZE_PX = 128
