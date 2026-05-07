# Validación de JWT (delegada al hub)

> English version: [jwt-auth.md](jwt-auth.md). Para el flujo de delegación completo ver [`../architecture/AUTHENTICATION.es.md`](../architecture/AUTHENTICATION.es.md).

Esta app de dominio **no emite, refresca ni revoca JWT**. Todas las operaciones del ciclo de vida del token viven en el hub (`ci4-api-starter`).

## Qué hace esta app

Cuando llega una petición con `Authorization: Bearer <jwt>`, `App\Filters\DomainAuthFilter` (alias `domainauth`) llama al hub:

```
POST <hub.url>/api/v1/auth/introspect
X-App-Key: <hub.apiKey>
Content-Type: application/json

{ "token": "<jwt>" }
```

El hub responde con `{ active, uid, permissions[], jti, exp, ... }`. El filtro:

1. Rechaza la petición con 401 si `active` es `false` (revocado, expirado o desconocido).
2. Re-resuelve los permisos efectivos del usuario para el `application_id` de **esta app** (el hub usa `EffectivePermissionsResolver(uid, application_id)`), así que el `permissions[]` devuelto refleja lo que el usuario puede hacer *aquí*, no el scope literal del JWT.
3. Cachea la respuesta por JTI durante `hub.introspectCacheTtl` segundos (por defecto `60`).
4. Inyecta `(uid, permissions[])` en `ApiRequest::setAuthContext()` y `ContextHolder` para que los filtros `permission:<código>` y los audit writers downstream funcionen sin cambios.

## Qué NO hace esta app

- Emitir access tokens, refresh tokens ni tokens de password-reset.
- Mantener tablas `users` / `refresh_tokens` / `password_resets` / `email_verifications`.
- Decodificar JWT localmente (no hay secret de firma compartido con el hub — el endpoint de introspección es la frontera del contrato).
- Llevar listas de revocación de JTI. El hub posee la revocación; confiamos en `active=false`.

## Configuración

Ver el [`README.es.md`](../../README.es.md) raíz § "Configuración" para la lista completa. Las variables relevantes para JWT son:

| Variable | Propósito |
|---|---|
| `hub.url` | URL base del hub. |
| `hub.apiKey` | `X-App-Key` vinculado a la fila de esta app en `applications` del hub. |
| `hub.introspectCacheTtl` | TTL en segundos del cache de respuestas de introspect. Por defecto `60`. |

## Dónde seguir leyendo

- [`../architecture/AUTHENTICATION.es.md`](../architecture/AUTHENTICATION.es.md) — diagrama de secuencia completo, mapeo de errores, casos límite.
- `app/Libraries/Hub/HubClient.php` — único punto de contacto con el hub.
- `app/Filters/DomainAuthFilter.php` — el filtro montado en rutas protegidas.
- Repo del hub (`ci4-api-starter`), `docs/tech/jwt-auth.md` — emisión, firma, blacklist y refresh de JWT.
