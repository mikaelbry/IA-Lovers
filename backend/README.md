# Documentación del backend

Este backend implementa la API de IA-Lovers. Está escrito en PHP sin framework, con un router propio, controladores por recurso, servicios para la lógica de negocio e integraciónes externas, modelos de acceso a datos y utilidades transversales.

La URL pública esperada para la API es:

```text
/backend
```

En Vercel existe además un wrapper en `api/index.php`. Ese archivo solo carga `backend/index.php` porque Vercel detecta funciones serverless dentro de la carpeta especial `api/`, pero el código real del backend vive en `backend/`.

## Estructura

```text
backend/
  index.php              Punto de entrada HTTP y registro de rutas.
  config/
    Database.php         Conexión PDO a PostgreSQL y carga de variables .env.
  core/
    Router.php           Router GET/POST y normalización de URL.
    Response.php         Respuestas JSON y códigos HTTP.
    Auth.php             Tokens de sesión, usuario autenticado y revocación.
    Middleware.php       Middleware de autenticación.
  controllers/
    AuthController.php   Delega endpoints de auth a servicios finales.
    UserController.php   Perfil, ajustes, avatar, cambio de email y borrado.
    PostController.php   Feed, detalle, creación, borrado y likes.
    CommentController.php Comentarios y respuestas.
    FollowController.php Seguidores, seguidos y seguir/dejar de seguir.
    NotificationController.php Notificaciones.
    TagController.php    Búsqueda y creación de tags.
  services/
    AuthService.php      Login, logout y sesión.
    RegistrationService.php Registro con código por email.
    PasswordResetService.php Recuperación de contraseña.
    AuthSupport.php      Validaciones y utilidades compartidas de auth.
    GmailMailer.php      Envío de emails con PHPMailer.
    Storage.php          Integración con Supabase Storage.
    Altcha.php           CAPTCHA Altcha.
  models/
    User.php
    Comment.php
    Notification.php
    PendingRegistration.php
    PendingPasswordReset.php
    PendingEmailChange.php
  utils/
    RateLimiter.php      Limitador de peticiones por IP.
  vendor/
    PHPMailer y dependencias incluidas manualmente.
```

## Flujo de una petición

1. La petición entra por `backend/index.php`.
2. Se aplican cabeceras de seguridad, CORS y tipo de contenido JSON.
3. Se cargan `Router`, `Response`, utilidades y controladores.
4. `Router` normaliza la URL para aceptar rutas bajo `/backend/...`.
5. El router ejecuta el controlador asociado.
6. El controlador valida entrada, llama a servicios/modelos y responde con `Response::json`.
7. Si ocurre una excepción no controlada, `index.php` devuelve JSON con código HTTP 500.

## Autenticación

La autenticación usa tokens Bearer.

```http
Authorization: Bearer <token>
```

`Auth::issueToken()` genera un token aleatorio, guarda su hash en base de datos y devuelve:

```json
{
  "token": "...",
  "expires_at": "...",
  "expires_in_days": 30
}
```

`Auth::user()` valida el header `Authorization`, busca el hash del token activo y devuelve el usuario autenticado. `Middleware::auth()` envuelve esa llamada y responde `401` si no hay sesión valida.

## Registro

El registro está separado en:

- `AuthController`: expone los métodos llamados por las rutas.
- `RegistrationService`: contiene el flujo real de registro.
- `AuthSupport`: valida usuario, email, contraseña y genera códigos.
- `PendingRegistration`: guarda registros pendientes hasta verificar el código.
- `GmailMailer`: envia el código al correo.

Flujo:

1. `/register/start` valida datos y CAPTCHA Altcha.
2. Se crea o actualiza un registro pendiente con `flow_token`.
3. Se envia un código de 6 digitos al correo.
4. `/register/verify` comprueba código, caducidad e intentos.
5. Si todo es correcto, crea el usuario y elimina el registro pendiente.

Las rutas `/mobile/register/...` reutilizan el mismo flujo, pero no exigen Altcha.

## Login, logout y sesión

`AuthService` se queda solo con la autenticación directa:

- `login()` y `mobileLogin()` validan email/contraseña y emiten token.
- `session()` devuelve el usuario autenticado.
- `logout()` revoca el token actual.

## Recuperación de contraseña

`PasswordResetService` gestiona el ciclo completo:

1. Solicitud de recuperación por correo.
2. Código temporal con `flow_token`.
3. Reenvío con cooldown.
4. Verificación de código.
5. Cambio de contraseña y revocación de otros tokens.

El flujo limita intentos y puede bloquear temporalmente el proceso tras demásiados errores.

## Gestion de usuarios

`UserController` cubre:

- Perfil privado del usuario autenticado.
- Perfil publico por usuario o por nombre.
- Resumen de ajustes.
- Validación de disponibilidad de username.
- Actualizacion de username/email/contraseña.
- Subida y borrado de avatar.
- Cambio de correo con código de verificación.
- Eliminación de cuenta y limpieza relacionada.

El cambio de correo usa `PendingEmailChange` y envia códigos con `GmailMailer`.

## Publicaciónes

`PostController` permite:

- Crear públicaciónes con imagen.
- Borrar públicaciónes propias.
- Obtener feed general, feed de seguidos o posts de un usuario.
- Filtrar por búsqueda/tag.
- Ordenar por recientes o likes.
- Obtener detalle.
- Dar o quitar like.

Las imagenes se suben a Supabase Storage mediante `Storage::uploadUserFile`. Antes de subir se valida:

- Tamaño máximo: 4 MB.
- MIME permitido: `image/jpeg`, `image/png`, `image/webp`.

## Comentarios

`CommentController` crea y elimina comentarios. Soporta respuestas a comentarios padre y devuelve comentarios normalizados mediante `mapCommentResponse`.

Al comentar en una públicación ajena o responder a otro usuario, se crean notificaciones.

## Seguidores

`FollowController` permite seguir/dejar de seguir a otro usuario, listar seguidores y listar seguidos. Al seguir a alguien se genera una notificación para el usuario seguido.

## Notificaciones

`NotificationController` lista notificaciones, devuelve el contador de no leídas y marca una o todas como leidas.

`Notification` centraliza la creación y limpieza de notificaciones asociadas a follows, likes, comentarios y públicaciónes eliminadas.

## Tags

`TagController` busca tags por texto y permite crear tags nuevos si no existen. Los posts pueden asociarse a varios tags mediante la tabla intermedia `post_tags`.

## Servicios externos

### Supabase Storage

`Storage.php` encapsula:

- Construccion de URL pública.
- Normalizacion de posts para convertir `file_path` en URL pública.
- Subida de archivos.
- Borrado de archivos.

Variables necesarias:

```text
SUPABASE_URL
SUPABASE_SERVICE_ROLE_KEY
SUPABASE_STORAGE_BUCKET
SUPABASE_STORAGE_PUBLIC_URL
```

### Gmail / SMTP

`GmailMailer.php` usa PHPMailer para enviar:

- Códigos de registro.
- Códigos de cambio de correo.
- Códigos de recuperación de contraseña.

Variables necesarias:

```text
SMTP_HOST
SMTP_PORT
SMTP_SECURE
SMTP_USERNAME
SMTP_PASSWORD
SMTP_FROM_EMAIL
SMTP_FROM_NAME
APP_NAME
```

### Altcha

`Altcha.php` genera y verifica challenges CAPTCHA. Protege los flujos web de registro, login y recuperación de contraseña. Las rutas mobile no exigen Altcha.

Variable necesaria:

```text
ALTCHA_HMAC_KEY
```

## Base de datos

`Database.php` crea una conexión PDO a PostgreSQL. Puede usar:

- `DATABASE_URL`, si existe.
- O variables separadas: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_SSLMODE`, `DB_HOSTADDR`, `DB_CONNECT_TIMEOUT`.

La clase carga `.env` manualmente para entorno local.

## Rate limiting

`RateLimiter` guarda un registro temporal en el directorio temporal del sistema. La clave combina el nombre del flujo y la IP del cliente. Se usa en operaciones sensibles:

- Registro.
- Verificación de códigos.
- Reenvío de códigos.
- Login.
- Recuperación de contraseña.
- Cambio de correo.

## Convenciones de respuesta

Todas las respuestas son JSON.

Exito:

```json
{ "success": true }
```

Error:

```json
{ "error": "Mensaje descriptivo" }
```

Los controladores usan códigos HTTP adecuados: `400`, `401`, `403`, `404`, `409`, `410`, `429` y `500`.

## Despliegue en Vercel

Vercel exige funciones serverless dentro de `api/`. Por eso existe:

```text
api/index.php
```

Ese archivo incluye:

```php
require_once __DIR__ . '/../backend/index.php';
```

El `vercel.json` apunta la funcion a `api/index.php` y mantiene la URL pública `/backend/...` mediante rewrites.

## Archivos que no forman parte de la lógica propia

`backend/vendor/` contiene dependencias externas. No conviene modificarlo salvo para actualizar librerías.
