# Referencia de API

Base URL:

```text
/backend
```

Todas las respuestas se devuelven en JSON. Las rutas privadas requieren:

```http
Authorization: Bearer <token>
```

## Autenticación

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| POST | `/register` | No | Alias de `/register/start`. |
| POST | `/register/start` | No | Inicia registro web. Requiere Altcha. |
| POST | `/register/verify` | No | Verifica código y crea cuenta. |
| POST | `/register/resend` | No | Reenvía código de registro. |
| POST | `/register/cancel` | No | Cancela registro pendiente. |
| POST | `/login` | No | Login web. Requiere Altcha. |
| GET | `/session` | Sí | Devuelve usuario autenticado. |
| POST | `/logout` | Sí | Revoca token actual. |
| GET | `/altcha/challenge` | No | Genera challenge Altcha. |

### POST `/register/start`

Body:

```json
{
  "username": "usuario",
  "email": "correo@example.com",
  "password": "Password123",
  "password_confirmation": "Password123",
  "altcha": "payload-altcha"
}
```

Respuesta:

```json
{
  "message": "Código enviado al correo indicado",
  "flow_token": "...",
  "email": "correo@example.com",
  "masked_email": "co***@example.com",
  "resend_cooldown": 30
}
```

### POST `/register/verify`

Body:

```json
{
  "flow_token": "...",
  "code": "123456"
}
```

### POST `/login`

Body:

```json
{
  "email": "correo@example.com",
  "password": "Password123",
  "altcha": "payload-altcha"
}
```

Respuesta:

```json
{
  "token": "...",
  "expires_at": "2026-06-26 12:00:00",
  "expires_in_days": 30,
  "user": {
    "id": 1,
    "username": "usuario",
    "avatar_url": null
  }
}
```

## Autenticación mobile

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| POST | `/mobile/register/start` | No | Inicia registro mobile sin Altcha. |
| POST | `/mobile/register/verify` | No | Verifica registro mobile. |
| POST | `/mobile/register/resend` | No | Reenvía código de registro mobile. |
| POST | `/mobile/register/cancel` | No | Cancela registro pendiente mobile. |
| POST | `/mobile/login` | No | Login mobile sin Altcha. |

Los cuerpos son iguales a los endpoints web equivalentes, excepto que no se envia `altcha`.

## Recuperación de contraseña

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| POST | `/password-reset/start` | No | Inicia recuperación web. Requiere Altcha. |
| POST | `/password-reset/resend` | No | Reenvía código. |
| POST | `/password-reset/complete` | No | Verifica código y cambia contraseña. |
| POST | `/password-reset/cancel` | No | Cancela flujo pendiente si no está bloqueado. |
| POST | `/mobile/password-reset/start` | No | Inicia recuperación mobile sin Altcha. |
| POST | `/mobile/password-reset/resend` | No | Reenvía código mobile. |
| POST | `/mobile/password-reset/complete` | No | Completa recuperación mobile. |
| POST | `/mobile/password-reset/cancel` | No | Cancela recuperación mobile. |

### POST `/password-reset/start`

Body:

```json
{
  "email": "correo@example.com",
  "altcha": "payload-altcha"
}
```

### POST `/password-reset/complete`

Body:

```json
{
  "flow_token": "...",
  "code": "123456",
  "password": "NuevaPassword123",
  "password_confirmation": "NuevaPassword123"
}
```

## Usuarios

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| GET | `/users/profile` | Sí | Perfil privado, seguidores y posts propios. |
| GET | `/users/settings-summary` | Sí | Datos para pantalla de ajustes. |
| GET | `/users/public?id=1` | Opcional | Perfil publico por id. |
| GET | `/users/username?username=usuario` | Opcional | Perfil publico por nombre de usuario. |
| GET | `/users/check-username?username=usuario` | No | Comprueba disponibilidad. |
| POST | `/user/update` | Sí | Actualiza username, email y opcionalmente contraseña. |
| POST | `/user/avatar` | Sí | Sube avatar. `multipart/form-data`. |
| POST | `/user/email-change/start` | Sí | Inicia cambio de correo con código. |
| POST | `/user/email-change/resend` | Sí | Reenvía código de cambio de correo. |
| POST | `/user/email-change/verify` | Sí | Confirma cambio de correo. |
| POST | `/user/email-change/cancel` | Sí | Cancela cambio de correo pendiente. |
| POST | `/user/delete` | Sí | Elimina cuenta y datos asociados. |

### POST `/user/update`

Body:

```json
{
  "username": "nuevo_usuario",
  "email": "correo@example.com",
  "password": "NuevaPassword123",
  "password_confirmation": "NuevaPassword123",
  "current_password": "PasswordActual123"
}
```

`current_password` es obligatorio cuando se cambian datos sensibles.

### POST `/user/avatar`

Formato:

```text
multipart/form-data
avatar=<archivo jpg/png/webp>
```

### POST `/user/email-change/start`

Body:

```json
{
  "email": "nuevo@example.com",
  "current_password": "PasswordActual123"
}
```

### POST `/user/delete`

Body:

```json
{
  "current_password": "PasswordActual123",
  "confirm_text": "ELIMINAR MI CUENTA"
}
```

## Publicaciónes

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| GET | `/posts` | Opcional | Feed, búsqueda y filtros. |
| GET | `/posts/show?id=1` | Opcional | Detalle de públicación. |
| POST | `/posts/create` | Sí | Crea públicación con imagen. |
| POST | `/posts/delete` | Sí | Borra públicación propia. |
| POST | `/posts/toggle-like` | Sí | Da o quita like. |

### GET `/posts`

Query params:

| Parámetro | Descripción |
| --- | --- |
| `type` | `explore` por defecto, `following` para seguidos. |
| `q` | Texto/tag de búsqueda. |
| `order` | `recent` o `likes`. |
| `id` | Filtra por usuario. |
| `cursor` | Paginación por fecha/id. |
| `cursor_likes` | Paginación cuando `order=likes`. |

### POST `/posts/create`

Formato:

```text
multipart/form-data
image=<archivo jpg/png/webp>
title=<texto opcional max 80>
description=<texto opcional max 500>
tags=["Arte","ChatGPT"]
```

### POST `/posts/delete`

Body:

```json
{ "post_id": 1 }
```

### POST `/posts/toggle-like`

Body:

```json
{ "post_id": 1 }
```

## Comentarios

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| POST | `/comments/create` | Sí | Crea comentario o respuesta. |
| POST | `/comments/delete` | Sí | Borra comentario propio. |

### POST `/comments/create`

Body:

```json
{
  "post_id": 1,
  "content": "Texto del comentario",
  "parent_id": null
}
```

`parent_id` es opcional y sirve para responder a otro comentario.

### POST `/comments/delete`

Body:

```json
{ "comment_id": 1 }
```

## Seguidores

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| POST | `/follow` | Sí | Sigue o deja de seguir a otro usuario. |
| GET | `/users/followers?id=1` | No | Lista seguidores. |
| GET | `/users/following?id=1` | No | Lista usuarios seguidos. |

### POST `/follow`

Body:

```json
{ "user_id": 2 }
```

Respuesta:

```json
{ "following": true }
```

## Notificaciones

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| GET | `/notifications` | Sí | Lista notificaciones y contador. |
| GET | `/notifications/unread-count` | Sí | Devuelve contador de no leídas. |
| POST | `/notifications/read` | Sí | Marca una o todas como leídas. |

### POST `/notifications/read`

Body para una notificación:

```json
{ "id": 1 }
```

Body para todas:

```json
{}
```

## Tags

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| GET | `/tags/search?q=arte` | No | Busca tags por texto. |
| POST | `/tags/create` | Sí | Crea tag si no existe. |

### POST `/tags/create`

Body:

```json
{ "name": "Arte" }
```

## Códigos de error habituales

| Código | Uso |
| --- | --- |
| 400 | Entrada inválida o datos obligatorios ausentes. |
| 401 | Falta autenticación o token inválido. |
| 403 | Usuario autenticado sin permiso para esa acción. |
| 404 | Recurso no encontrado. |
| 409 | Conflicto, por ejemplo correo ya en uso. |
| 410 | Código caducado. |
| 429 | Demásiados intentos o cooldown activo. |
| 500 | Error interno no controlado. |
