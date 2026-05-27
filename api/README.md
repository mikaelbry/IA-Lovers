# Wrapper serverless para Vercel

Está carpeta existe para compatibilidad con Vercel.

Vercel detecta funciones serverless dentro de `api/`, pero el código real del backend de IA-Lovers vive en `backend/`. Por eso `api/index.php` solo incluye:

```php
require_once __DIR__ . '/../backend/index.php';
```

El `vercel.json` redirige las llamadas públicas `/backend/...` hacia está funcion serverless.
