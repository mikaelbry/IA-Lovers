package com.ialovers.mobile.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val IaBlue = Color(0xFF2481CE)
private val IaBlueDark = Color(0xFF072C4E)
private val IaBlueMid = Color(0xFF154C79)
private val IaCyan = Color(0xFF4DD0E1)
private val IaBackground = Color(0xFFF3F4F6)
private val IaText = Color(0xFF10263A)
private val IaMutedText = Color(0xFF4D6275)
private val IaSurfaceVariant = Color(0xFFEAF3FA)
private val IaError = Color(0xFFC23D4C)

private val LightColors = lightColorScheme(
    primary = IaBlue,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFD7ECFB),
    onPrimaryContainer = IaBlueDark,
    secondary = IaBlueMid,
    onSecondary = Color.White,
    secondaryContainer = IaSurfaceVariant,
    onSecondaryContainer = IaBlueDark,
    tertiary = IaCyan,
    onTertiary = IaBlueDark,
    background = IaBackground,
    onBackground = IaText,
    surface = Color.White,
    onSurface = IaText,
    surfaceVariant = IaSurfaceVariant,
    onSurfaceVariant = IaMutedText,
    outline = Color(0xFFD6E0E8),
    error = IaError,
    errorContainer = Color(0xFFF9D8DE),
    onErrorContainer = Color(0xFF601824),
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFF8BCBFF),
    onPrimary = IaBlueDark,
    primaryContainer = IaBlueMid,
    onPrimaryContainer = Color.White,
    secondary = IaCyan,
    onSecondary = IaBlueDark,
    secondaryContainer = Color(0xFF123A5A),
    onSecondaryContainer = Color(0xFFE5F6FF),
    background = Color(0xFF071D31),
    onBackground = Color(0xFFEAF3FA),
    surface = Color(0xFF0D2A43),
    onSurface = Color(0xFFEAF3FA),
    surfaceVariant = Color(0xFF123A5A),
    onSurfaceVariant = Color(0xFFC1D5E5),
    outline = Color(0xFF5B7A91),
    error = Color(0xFFFFB3BF),
    errorContainer = Color(0xFF7D2433),
    onErrorContainer = Color(0xFFFFE8EC),
)

@Composable
fun IaLoversTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        content = content,
    )
}
