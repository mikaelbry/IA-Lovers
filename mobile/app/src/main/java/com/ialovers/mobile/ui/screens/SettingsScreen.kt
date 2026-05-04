package com.ialovers.mobile.ui.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.ialovers.mobile.SettingsSection
import com.ialovers.mobile.SettingsUiState
import com.ialovers.mobile.ui.components.Avatar
import com.ialovers.mobile.ui.components.MessageBlock

@Composable
fun SettingsScreen(
    state: SettingsUiState,
    onBack: () -> Unit,
    onSelectSection: (SettingsSection) -> Unit,
    onUpdateAvatar: (Uri) -> Unit,
    onUpdateUsername: (String, String) -> Unit,
    onStartEmailChange: (String, String) -> Unit,
    onVerifyEmailChange: (String) -> Unit,
    onResendEmailChange: () -> Unit,
    onCancelEmailChange: () -> Unit,
    onUpdatePassword: (String, String, String) -> Unit,
    onRequestDeleteConfirmation: (String) -> Unit,
    onDeleteAccount: (String) -> Unit,
    onLogout: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val summary = state.summary

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
                text = "Ajustes",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
            )
        }

        when {
            state.isLoading && summary == null -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center,
                ) {
                    CircularProgressIndicator()
                }
            }

            summary == null -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(24.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        text = state.error ?: "No se pudieron cargar los ajustes.",
                        textAlign = TextAlign.Center,
                    )
                }
            }

            else -> {
                LazyColumn(
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                ) {
                    item {
                        Card(modifier = Modifier.fillMaxWidth()) {
                            Row(
                                modifier = Modifier.padding(16.dp),
                                horizontalArrangement = Arrangement.spacedBy(14.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Avatar(summary.user.avatarUrl, summary.user.username)
                                Column {
                                    Text(summary.user.username, fontWeight = FontWeight.Bold)
                                    Text(
                                        text = summary.user.email.orEmpty(),
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }
                        }
                    }

                    item {
                        SettingsSectionSelector(
                            active = state.activeSection,
                            onSelect = onSelectSection,
                        )
                    }

                    item {
                        MessageBlock(message = state.statusMessage, isError = false)
                        MessageBlock(message = state.error, isError = true, modifier = Modifier.padding(top = 8.dp))
                    }

                    item {
                        when (state.activeSection) {
                            SettingsSection.Account -> AccountSettings(state)
                            SettingsSection.Avatar -> AvatarSettings(
                                isSaving = state.isSaving,
                                onUpdateAvatar = onUpdateAvatar,
                            )
                            SettingsSection.Username -> UsernameSettings(
                                isSaving = state.isSaving,
                                currentUsername = summary.user.username,
                                onSubmit = onUpdateUsername,
                            )
                            SettingsSection.Email -> EmailSettings(
                                state = state,
                                currentEmail = summary.user.email.orEmpty(),
                                onStart = onStartEmailChange,
                                onVerify = onVerifyEmailChange,
                                onResend = onResendEmailChange,
                                onCancel = onCancelEmailChange,
                            )
                            SettingsSection.Password -> PasswordSettings(
                                isSaving = state.isSaving,
                                onSubmit = onUpdatePassword,
                            )
                            SettingsSection.Delete -> DeleteSettings(
                                state = state,
                                onRequestConfirm = onRequestDeleteConfirmation,
                                onDelete = onDeleteAccount,
                            )
                            SettingsSection.Logout -> LogoutSettings(onLogout = onLogout)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun SettingsSectionSelector(
    active: SettingsSection,
    onSelect: (SettingsSection) -> Unit,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(8.dp)) {
            SettingsSection.entries.forEach { section ->
                FilledTonalButton(
                    onClick = { onSelect(section) },
                    modifier = Modifier.fillMaxWidth(),
                    enabled = active != section,
                ) {
                    Text(section.label)
                }
            }
        }
    }
}

@Composable
private fun AccountSettings(state: SettingsUiState) {
    val summary = state.summary ?: return

    SettingsCard(title = "Informacion de cuenta") {
        InfoRow("Nombre de usuario", summary.user.username)
        InfoRow("Correo", summary.user.email.orEmpty())
        InfoRow("Seguidores", summary.followers.toString())
        InfoRow("Posts", summary.postsCount.toString())
        InfoRow("Cuenta creada", summary.user.createdAt ?: "Fecha no disponible")
    }
}

@Composable
private fun AvatarSettings(
    isSaving: Boolean,
    onUpdateAvatar: (Uri) -> Unit,
) {
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            onUpdateAvatar(uri)
        }
    }

    SettingsCard(title = "Cambiar avatar") {
        Text("Puedes usar JPG, PNG o WEBP hasta 4 MB.")
        Button(
            onClick = { picker.launch("image/*") },
            enabled = !isSaving,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text(if (isSaving) "Guardando..." else "Seleccionar avatar")
        }
    }
}

@Composable
private fun UsernameSettings(
    isSaving: Boolean,
    currentUsername: String,
    onSubmit: (String, String) -> Unit,
) {
    var username by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }

    SettingsCard(title = "Cambiar nombre de usuario") {
        OutlinedTextField(
            value = currentUsername,
            onValueChange = {},
            readOnly = true,
            label = { Text("Usuario actual") },
            modifier = Modifier.fillMaxWidth(),
        )
        OutlinedTextField(
            value = username,
            onValueChange = { username = it },
            label = { Text("Nuevo usuario") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
        PasswordField(
            value = password,
            onValueChange = { password = it },
            label = "Contrasena actual",
        )
        Button(
            onClick = { onSubmit(username, password) },
            enabled = !isSaving,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text(if (isSaving) "Guardando..." else "Cambiar usuario")
        }
    }
}

@Composable
private fun EmailSettings(
    state: SettingsUiState,
    currentEmail: String,
    onStart: (String, String) -> Unit,
    onVerify: (String) -> Unit,
    onResend: () -> Unit,
    onCancel: () -> Unit,
) {
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var code by rememberSaveable { mutableStateOf("") }

    LaunchedEffect(state.emailChange.pending) {
        if (!state.emailChange.pending) {
            code = ""
        }
    }

    SettingsCard(title = "Cambiar correo") {
        OutlinedTextField(
            value = currentEmail,
            onValueChange = {},
            readOnly = true,
            label = { Text("Correo actual") },
            modifier = Modifier.fillMaxWidth(),
        )

        if (!state.emailChange.pending) {
            OutlinedTextField(
                value = email,
                onValueChange = { email = it },
                label = { Text("Nuevo correo") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
            )
            PasswordField(
                value = password,
                onValueChange = { password = it },
                label = "Contrasena actual",
            )
            Button(
                onClick = { onStart(email, password) },
                enabled = !state.isSaving,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (state.isSaving) "Enviando..." else "Enviar codigo")
            }
        } else {
            Text("Codigo enviado a ${state.emailChange.maskedEmail}.")
            OutlinedTextField(
                value = code,
                onValueChange = { code = it.take(6) },
                label = { Text("Codigo de 6 digitos") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
            )
            Button(
                onClick = { onVerify(code) },
                enabled = !state.isSaving,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (state.isSaving) "Verificando..." else "Confirmar codigo")
            }
            OutlinedButton(
                onClick = onResend,
                enabled = !state.isSaving,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Reenviar codigo")
            }
            TextButton(
                onClick = onCancel,
                enabled = !state.isSaving,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Cancelar cambio")
            }
        }
    }
}

@Composable
private fun PasswordSettings(
    isSaving: Boolean,
    onSubmit: (String, String, String) -> Unit,
) {
    var current by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var confirm by rememberSaveable { mutableStateOf("") }

    SettingsCard(title = "Cambiar contrasena") {
        PasswordField(current, { current = it }, "Contrasena actual")
        PasswordField(password, { password = it }, "Nueva contrasena")
        PasswordField(confirm, { confirm = it }, "Confirmar contrasena")
        Button(
            onClick = { onSubmit(current, password, confirm) },
            enabled = !isSaving,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text(if (isSaving) "Guardando..." else "Cambiar contrasena")
        }
    }
}

@Composable
private fun DeleteSettings(
    state: SettingsUiState,
    onRequestConfirm: (String) -> Unit,
    onDelete: (String) -> Unit,
) {
    var password by rememberSaveable { mutableStateOf("") }
    var confirm by rememberSaveable { mutableStateOf("") }

    SettingsCard(title = "Borrar la cuenta") {
        Text(
            text = "Esta accion es irreversible.",
            color = MaterialTheme.colorScheme.error,
            fontWeight = FontWeight.Bold,
        )
        PasswordField(password, { password = it }, "Contrasena actual")

        if (state.deleteConfirmStep) {
            OutlinedTextField(
                value = confirm,
                onValueChange = { confirm = it },
                label = { Text("Escribe ELIMINAR MI CUENTA") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
            )
            Button(
                onClick = { onDelete(confirm) },
                enabled = !state.isSaving,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (state.isSaving) "Borrando..." else "Borrar cuenta definitivamente")
            }
        } else {
            Button(
                onClick = { onRequestConfirm(password) },
                enabled = !state.isSaving,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Continuar")
            }
        }
    }
}

@Composable
private fun LogoutSettings(onLogout: () -> Unit) {
    SettingsCard(title = "Cerrar sesion") {
        Text("Cierra la sesion en este dispositivo y vuelve a la pantalla inicial.")
        Button(
            onClick = onLogout,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text("Cerrar sesion")
        }
    }
}

@Composable
private fun SettingsCard(
    title: String,
    content: @Composable ColumnScope.() -> Unit,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
            )
            Divider()
            content()
        }
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.SemiBold,
        )
    }
}

@Composable
private fun PasswordField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        visualTransformation = PasswordVisualTransformation(),
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
        modifier = Modifier.fillMaxWidth(),
        singleLine = true,
    )
}
