package com.ialovers.mobile.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Divider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.ialovers.mobile.data.PostItem

@Composable
fun PostCard(
    post: PostItem,
    onOpen: (Int) -> Unit,
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
            AsyncImage(
                model = post.filePath,
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
                .padding(horizontal = 8.dp, vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TextButton(
                onClick = { onToggleLike(post) },
            ) {
                Text(if (post.likedByUser) "Me gusta (${post.likesCount})" else "Me gusta (${post.likesCount})")
            }

            TextButton(
                onClick = { onOpen(post.id) },
            ) {
                Text("Comentar (${post.commentsCount})")
            }
        }

        PostText(post = post)

        Divider(color = MaterialTheme.colorScheme.surfaceVariant)
    }
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

    AsyncImage(
        model = url,
        contentDescription = "Avatar de $label",
        modifier = modifier
            .size(38.dp)
            .clip(CircleShape),
        contentScale = ContentScale.Crop,
    )
}
