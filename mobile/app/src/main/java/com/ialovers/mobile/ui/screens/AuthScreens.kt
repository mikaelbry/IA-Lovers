package com.ialovers.mobile.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
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
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import com.ialovers.mobile.PendingRegistrationUi
import com.ialovers.mobile.ui.components.MessageBlock

@Composable
fun SplashScreen() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center,
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            CircularProgressIndicator()
            Text("Comprobando sesion...")
        }
    }
}

@Composable
fun AuthChoiceScreen(
    authMessage: String?,
    onLogin: () -> Unit,
    onRegister: () -> Unit,
) {
    AuthLayout {
        Column(
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Text(
                text = "IA Lovers",
                style = MaterialTheme.typography.headlineMedium,
                fontWeight = FontWeight.Bold,
            )
            Text(
                text = "Descubre, comparte y sigue creaciones generadas con IA.",
                style = MaterialTheme.typography.bodyLarge,
            )

            MessageBlock(message = authMessage, isError = false)

            Button(
                onClick = onLogin,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Iniciar sesion")
            }

            TextButton(
                onClick = onRegister,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Crear cuenta")
            }
        }
    }
}

@Composable
fun LoginScreen(
    isBusy: Boolean,
    message: String?,
    error: String?,
    onBack: () -> Unit,
    onLogin: (String, String) -> Unit,
    onGoRegister: () -> Unit,
) {
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }

    AuthLayout {
        Column(
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            TextButton(onClick = onBack) {
                Text("Volver")
            }

            Text(
                text = "Iniciar sesion",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
            )

            MessageBlock(message = message, isError = false)
            MessageBlock(message = error, isError = true)

            OutlinedTextField(
                value = email,
                onValueChange = { email = it },
                label = { Text("Email") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )

            OutlinedTextField(
                value = password,
                onValueChange = { password = it },
                label = { Text("Contrasena") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                visualTransformation = PasswordVisualTransformation(),
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )

            Button(
                onClick = { onLogin(email, password) },
                enabled = !isBusy,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (isBusy) "Entrando..." else "Entrar")
            }

            TextButton(
                onClick = onGoRegister,
                enabled = !isBusy,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("No tengo cuenta todavia")
            }
        }
    }
}

@Composable
fun RegisterScreen(
    isBusy: Boolean,
    message: String?,
    error: String?,
    pendingRegistration: PendingRegistrationUi?,
    onBack: () -> Unit,
    onStartRegistration: (String, String, String, String) -> Unit,
    onVerifyCode: (String) -> Unit,
    onResendCode: () -> Unit,
    onCancelPending: () -> Unit,
    onGoLogin: () -> Unit,
) {
    var username by rememberSaveable { mutableStateOf("") }
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var passwordConfirmation by rememberSaveable { mutableStateOf("") }
    var code by rememberSaveable { mutableStateOf("") }

    AuthLayout {
        Column(
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            TextButton(onClick = onBack) {
                Text("Volver")
            }

            Text(
                text = if (pendingRegistration == null) "Crear cuenta" else "Verificar correo",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
            )

            MessageBlock(message = message, isError = false)
            MessageBlock(message = error, isError = true)

            if (pendingRegistration == null) {
                OutlinedTextField(
                    value = username,
                    onValueChange = { username = it },
                    label = { Text("Usuario") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )

                OutlinedTextField(
                    value = email,
                    onValueChange = { email = it },
                    label = { Text("Email") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )

                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    label = { Text("Contrasena") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                    visualTransformation = PasswordVisualTransformation(),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )

                OutlinedTextField(
                    value = passwordConfirmation,
                    onValueChange = { passwordConfirmation = it },
                    label = { Text("Confirmar contrasena") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                    visualTransformation = PasswordVisualTransformation(),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )

                Button(
                    onClick = {
                        onStartRegistration(
                            username,
                            email,
                            password,
                            passwordConfirmation,
                        )
                    },
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(if (isBusy) "Enviando codigo..." else "Continuar")
                }
            } else {
                Text(
                    text = "Introduce el codigo enviado a ${pendingRegistration.maskedEmail}.",
                    style = MaterialTheme.typography.bodyLarge,
                )

                OutlinedTextField(
                    value = code,
                    onValueChange = { code = it.take(6) },
                    label = { Text("Codigo de 6 digitos") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )

                Button(
                    onClick = { onVerifyCode(code) },
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(if (isBusy) "Comprobando..." else "Verificar cuenta")
                }

                TextButton(
                    onClick = onResendCode,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text("Reenviar codigo")
                }

                TextButton(
                    onClick = onCancelPending,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text("Cancelar registro")
                }
            }

            Divider(modifier = Modifier.padding(top = 8.dp))

            TextButton(
                onClick = onGoLogin,
                enabled = !isBusy,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Ya tengo cuenta")
            }
        }
    }
}

@Composable
private fun AuthLayout(
    content: @Composable ColumnScope.() -> Unit,
) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background),
        contentAlignment = Alignment.Center,
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surface,
            ),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        ) {
            Column(
                modifier = Modifier.padding(24.dp),
                content = content,
            )
        }
    }
}
