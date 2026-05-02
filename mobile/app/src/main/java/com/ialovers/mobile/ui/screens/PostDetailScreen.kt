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
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.ialovers.mobile.PostDetailUiState
import com.ialovers.mobile.data.CommentItem
import com.ialovers.mobile.data.PostItem
import com.ialovers.mobile.ui.components.Avatar
import com.ialovers.mobile.ui.components.MessageBlock
import com.ialovers.mobile.ui.components.PostCard

@Composable
fun PostDetailScreen(
    state: PostDetailUiState,
    onBack: () -> Unit,
    onToggleLike: (PostItem) -> Unit,
    onCreateComment: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    var comment by rememberSaveable { mutableStateOf("") }

    Column(modifier = modifier.fillMaxSize()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 8.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TextButton(onClick = onBack) {
                Text("Volver")
            }
            Text(
                text = "Publicacion",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
            )
        }

        when {
            state.isLoading -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center,
                ) {
                    CircularProgressIndicator()
                }
            }

            state.error != null && state.post == null -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(24.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        text = state.error,
                        textAlign = TextAlign.Center,
                    )
                }
            }

            state.post != null -> {
                LazyColumn(
                    modifier = Modifier.weight(1f),
                    contentPadding = PaddingValues(bottom = 12.dp),
                ) {
                    item {
                        PostCard(
                            post = state.post,
                            onOpen = {},
                            onToggleLike = onToggleLike,
                        )
                    }

                    item {
                        Text(
                            text = "Comentarios",
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp),
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                        )
                    }

                    if (state.comments.isEmpty()) {
                        item {
                            Text(
                                text = "Se el primero en comentar.",
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    } else {
                        items(state.comments, key = { it.id }) { item ->
                            CommentRow(comment = item)
                        }
                    }
                }

                MessageBlock(
                    message = state.error,
                    isError = true,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp),
                )

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(12.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    OutlinedTextField(
                        value = comment,
                        onValueChange = { comment = it },
                        label = { Text("Escribe un comentario") },
                        modifier = Modifier.weight(1f),
                        maxLines = 3,
                    )
                    Button(
                        onClick = {
                            onCreateComment(comment)
                            comment = ""
                        },
                        enabled = comment.isNotBlank() && !state.isCommentSending,
                    ) {
                        Text(if (state.isCommentSending) "..." else "Enviar")
                    }
                }
            }
        }
    }
}

@Composable
private fun CommentRow(comment: CommentItem) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 10.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Row(
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalAlignment = Alignment.Top,
        ) {
            Avatar(
                url = comment.avatarUrl,
                label = comment.username,
            )
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = comment.username,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold,
                )
                Text(
                    text = comment.content,
                    style = MaterialTheme.typography.bodyMedium,
                )
            }
        }
        Divider(color = MaterialTheme.colorScheme.surfaceVariant)
    }
}
