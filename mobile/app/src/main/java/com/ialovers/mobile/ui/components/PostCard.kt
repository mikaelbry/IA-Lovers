/*
 * Componentes compartidos para representar publicaciones y usuarios.
 * Agrupa la tarjeta de post, acciones de me gusta/comentario, texto del
 * post, avatar y utilidades de enlace.
 */
package com.ialovers.mobile.ui.components

import androidx.compose.foundation.clickable
import androidx.compose.foundation.background
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
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.outlined.FavoriteBorder
import androidx.compose.material.icons.outlined.ModeComment
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import coil.request.ImageRequest
import androidx.compose.ui.res.painterResource
import com.ialovers.mobile.BuildConfig
import com.ialovers.mobile.data.PostItem
import kotlinx.coroutines.delay

/** Muestra una publicación completa con autor, imagen, acciones y texto. */
@Composable
fun PostCard(
    post: PostItem,
    onOpen: (Int) -> Unit,
    onOpenAuthor: (String) -> Unit = {},
    onToggleLike: (PostItem) -> Unit,
    currentUsername: String? = null,
    onDeletePost: (PostItem) -> Unit = {},
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
            Row(
                modifier = Modifier
                    .weight(1f)
                    .clickable {
                        post.username?.takeIf { it.isNotBlank() }?.let(onOpenAuthor)
                    },
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
            PostOptionsMenu(
                post = post,
                isOwner = post.username != null && currentUsername != null &&
                    post.username.equals(currentUsername, ignoreCase = true),
                onDeletePost = onDeletePost,
            )
        }

        if (!post.filePath.isNullOrBlank()) {
            val context = LocalContext.current
            val screenWidth = context.resources.displayMetrics.widthPixels
            AsyncImage(
                model = ImageRequest.Builder(context)
                    .data(post.filePath)
                    .crossfade(true)
                    .size(screenWidth)
                    .build(),
                contentDescription = post.title ?: post.description ?: "Publicacion",
                modifier = Modifier
                    .fillMaxWidth()
                    .aspectRatio(1f),
                contentScale = ContentScale.Crop,
                placeholder = painterResource(android.R.drawable.ic_menu_gallery),
                error = painterResource(android.R.drawable.ic_menu_gallery),
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

/** Muestra el menú contextual de una publicación para compartirla o eliminarla. */
@Composable
private fun PostOptionsMenu(
    post: PostItem,
    isOwner: Boolean,
    onDeletePost: (PostItem) -> Unit,
) {
    val clipboardManager = LocalClipboardManager.current
    var expanded by remember { mutableStateOf(false) }
    var copied by remember { mutableStateOf(false) }
    var showDeleteDialog by remember { mutableStateOf(false) }

    LaunchedEffect(copied) {
        if (copied) {
            delay(2000)
            copied = false
        }
    }

    Box {
        IconButton(onClick = { expanded = true }) {
            Icon(
                imageVector = Icons.Filled.MoreVert,
                contentDescription = "Mas opciones",
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        DropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false },
        ) {
            DropdownMenuItem(
                text = {
                    Text(
                        text = if (copied) "Enlace copiado al portapapeles" else "Compartir",
                        color = if (copied) Color(0xFF188038) else Color.Unspecified,
                    )
                },
                onClick = {
                    clipboardManager.setText(AnnotatedString(post.shareUrl()))
                    copied = true
                },
            )
            if (isOwner) {
                DropdownMenuItem(
                    text = {
                        Text(
                            text = "Eliminar",
                            color = MaterialTheme.colorScheme.error,
                        )
                    },
                    onClick = {
                        expanded = false
                        showDeleteDialog = true
                    },
                )
            }
        }
    }

    if (showDeleteDialog) {
        AlertDialog(
            onDismissRequest = { showDeleteDialog = false },
            title = { Text("Eliminar publicacion") },
            text = { Text("Estas seguro de que deseas borrar esta publicacion? Esta accion es irreversible.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        showDeleteDialog = false
                        onDeletePost(post)
                    },
                ) {
                    Text("Eliminar", color = MaterialTheme.colorScheme.error)
                }
            },
            dismissButton = {
                TextButton(onClick = { showDeleteDialog = false }) {
                    Text("Cancelar")
                }
            },
        )
    }
}

/** Botón de me gusta con contador, adaptado al ancho de la fila de acciones. */
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

/** Botón de comentarios con contador, adaptado al ancho de la fila de acciones. */
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

/** Dibuja el título, descripción y etiquetas de una publicación. */
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

/** Muestra el avatar remoto de un usuario o una inicial si no hay imagen. */
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

/** Construye la URL web pública para compartir una publicación. */
private fun PostItem.shareUrl(): String {
    val apiBase = BuildConfig.API_BASE_URL.trimEnd('/')
    val appBase = apiBase.removeSuffix("/backend")
    return "$appBase/web/post.html?id=$id"
}

private const val AVATAR_IMAGE_SIZE_PX = 128
