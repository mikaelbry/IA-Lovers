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
import androidx.compose.foundation.clickable
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
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
    onEnterCommentThread: (Int) -> Unit,
    onLeaveCommentThread: () -> Unit,
    onOpenUserProfile: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    var comment by rememberSaveable { mutableStateOf("") }
    val commentTree = state.comments.toCommentTree()
    val flatComments = commentTree.flattenById()
    val activeComment = state.commentThread.lastOrNull()?.let { flatComments[it] }
    val commentsToShow = activeComment?.children ?: commentTree

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
                            onOpenAuthor = onOpenUserProfile,
                            onToggleLike = onToggleLike,
                        )
                    }

                    item {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 16.dp, vertical = 12.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(
                                text = "Comentarios",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                            )
                            if (state.commentThread.isNotEmpty()) {
                                TextButton(onClick = onLeaveCommentThread) {
                                    Text("Volver al hilo anterior")
                                }
                            }
                        }
                    }

                    if (activeComment != null) {
                        item {
                            CommentRow(
                                comment = activeComment.comment,
                                repliesCount = activeComment.descendantCount(),
                                isParentFocus = true,
                                onReply = onEnterCommentThread,
                                onOpenUserProfile = onOpenUserProfile,
                            )
                        }
                    }

                    if (commentsToShow.isEmpty()) {
                        item {
                            Text(
                                text = if (activeComment == null) {
                                    "Se el primero en comentar."
                                } else {
                                    "Todavia no hay respuestas."
                                },
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    } else {
                        items(commentsToShow, key = { it.comment.id }) { item ->
                            CommentRow(
                                comment = item.comment,
                                repliesCount = item.descendantCount(),
                                isParentFocus = false,
                                onReply = onEnterCommentThread,
                                onOpenUserProfile = onOpenUserProfile,
                            )
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
private fun CommentRow(
    comment: CommentItem,
    repliesCount: Int,
    isParentFocus: Boolean,
    onReply: (Int) -> Unit,
    onOpenUserProfile: (String) -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 10.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Row(
            modifier = Modifier.clickable { onOpenUserProfile(comment.username) },
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
        Row(
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TextButton(onClick = { onReply(comment.id) }) {
                Text("Responder")
            }
            if (repliesCount > 0 && !isParentFocus) {
                TextButton(onClick = { onReply(comment.id) }) {
                    Text("$repliesCount respuestas")
                }
            }
        }
        HorizontalDivider(color = MaterialTheme.colorScheme.surfaceVariant)
    }
}

private data class CommentNode(
    val comment: CommentItem,
    val children: List<CommentNode>,
)

private fun List<CommentItem>.toCommentTree(): List<CommentNode> {
    fun build(parentId: Int?): List<CommentNode> {
        return filter { it.parentId == parentId }
            .map { comment ->
                CommentNode(
                    comment = comment,
                    children = build(comment.id),
                )
            }
    }

    return build(null)
}

private fun List<CommentNode>.flattenById(): Map<Int, CommentNode> {
    val result = mutableMapOf<Int, CommentNode>()

    fun visit(node: CommentNode) {
        result[node.comment.id] = node
        node.children.forEach(::visit)
    }

    forEach(::visit)
    return result
}

private fun CommentNode.descendantCount(): Int {
    return children.size + children.sumOf { it.descendantCount() }
}
