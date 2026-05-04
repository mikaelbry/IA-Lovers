package com.ialovers.mobile.ui.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.ialovers.mobile.CreatePostUiState
import com.ialovers.mobile.ui.components.MessageBlock

@Composable
fun CreatePostScreen(
    state: CreatePostUiState,
    onSelectImage: (Uri?) -> Unit,
    onPublish: (String, String, List<String>) -> Unit,
    modifier: Modifier = Modifier,
) {
    var step by rememberSaveable { mutableIntStateOf(if (state.imageUri == null) 1 else 2) }
    var title by rememberSaveable { mutableStateOf("") }
    var description by rememberSaveable { mutableStateOf("") }
    var tagInput by rememberSaveable { mutableStateOf("") }
    val tags = remember { mutableStateListOf<String>() }

    val imagePicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            onSelectImage(uri)
            step = 2
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Text(
            text = "Nueva publicacion",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
        )

        MessageBlock(message = state.statusMessage, isError = false)
        MessageBlock(message = state.error, isError = true)

        if (step == 1 || state.imageUri == null) {
            Card(modifier = Modifier.fillMaxWidth()) {
                Column(
                    modifier = Modifier.padding(18.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                ) {
                    Text(
                        text = "Selecciona una imagen",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                    )
                    Text(
                        text = "Formatos permitidos: JPG, PNG o WEBP hasta 4 MB.",
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Button(
                        onClick = { imagePicker.launch("image/*") },
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Text("Elegir de la galeria")
                    }
                }
            }
            return@Column
        }

        val context = LocalContext.current
        AsyncImage(
            model = ImageRequest.Builder(context)
                .data(state.imageUri)
                .crossfade(false)
                .size(PREVIEW_IMAGE_SIZE_PX)
                .build(),
            contentDescription = "Imagen seleccionada",
            modifier = Modifier
                .fillMaxWidth()
                .aspectRatio(1f)
                .clip(RoundedCornerShape(8.dp)),
            contentScale = ContentScale.Crop,
        )

        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedButton(
                onClick = {
                    onSelectImage(null)
                    step = 1
                },
                enabled = !state.isPublishing,
            ) {
                Text("Cambiar imagen")
            }
            TextButton(
                onClick = { imagePicker.launch("image/*") },
                enabled = !state.isPublishing,
            ) {
                Text("Elegir otra")
            }
        }

        OutlinedTextField(
            value = title,
            onValueChange = { title = it.take(80) },
            label = { Text("Titulo") },
            supportingText = { Text("${title.length}/80") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
        )

        OutlinedTextField(
            value = description,
            onValueChange = { description = it.take(500) },
            label = { Text("Descripcion") },
            supportingText = { Text("${description.length}/500") },
            modifier = Modifier.fillMaxWidth(),
            minLines = 4,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
        )

        Card(modifier = Modifier.fillMaxWidth()) {
            Column(
                modifier = Modifier.padding(14.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                Text(
                    text = "Hashtags",
                    fontWeight = FontWeight.Bold,
                )
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(
                        value = tagInput,
                        onValueChange = { tagInput = it.take(24) },
                        label = { Text("Agregar hashtag") },
                        modifier = Modifier.weight(1f),
                        singleLine = true,
                    )
                    Button(
                        onClick = {
                            val tag = tagInput.trim().trimStart('#')
                            if (tag.isNotBlank() && tags.size < 4 && tags.none { it.equals(tag, ignoreCase = true) }) {
                                tags.add(tag)
                                tagInput = ""
                            }
                        },
                        enabled = tags.size < 4 && tagInput.isNotBlank(),
                    ) {
                        Text("Añadir")
                    }
                }

                if (tags.isEmpty()) {
                    Text(
                        text = "Puedes añadir hasta 4 hashtags.",
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                } else {
                    tags.forEach { tag ->
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                        ) {
                            Text("#$tag", color = MaterialTheme.colorScheme.primary)
                            TextButton(onClick = { tags.remove(tag) }) {
                                Text("Quitar")
                            }
                        }
                    }
                }
            }
        }

        Button(
            onClick = { onPublish(title, description, tags.toList()) },
            enabled = !state.isPublishing,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text(if (state.isPublishing) "Publicando..." else "Publicar")
        }
    }
}

private const val PREVIEW_IMAGE_SIZE_PX = 1080
