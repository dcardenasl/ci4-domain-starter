# Autenticación y delegación al hub

> English version: [AUTHENTICATION.md](AUTHENTICATION.md). Para un resumen alto-nivel ver [`../tech/jwt-auth.es.md`](../tech/jwt-auth.es.md).

Esta app de dominio delega **todas** las preocupaciones de identidad y acceso al hub (`ci4-api-starter`). Login, refresh, reseteo de contraseña, verificación de email, emisión de JWT, listas de revocación de JTI y las tablas `users` / `roles` / `permissions` viven en el hub. Este documento describe el contrato entre ambos.

## Frontera

```
Cliente (browser / SPA / CLI / servicio)
        │
        │  Authorization: Bearer <jwt>
        ▼
   App de dominio (este repo, :8090)
        │
        │  POST /api/v1/auth/introspect          (valida JWT de usuario)
        │  POST /api/v1/auth/service-token       (M2M cuando esta app llama al hub)
        │  POST /api/v1/iam/permissions          (solo en setup — domain:sync-permissions)
        │  X-App-Key: <hub.apiKey>
        ▼
        Hub (ci4-api-starter, :8080)
                ▼
        users / roles / permissions / role_permissions / user_roles
```

El contrato es **HTTP, no secretos compartidos**. La app de dominio no tiene la clave de firma del JWT; la confianza fluye a través del endpoint de introspección.

## Login (lo emite el hub)

La app de dominio no tiene endpoint `/login`. El cliente se loguea directamente contra el hub:

```
POST <hub>/api/v1/auth/login
X-App-Key: <hub.apiKey>          # el X-App-Key de ESTA app de dominio, no del hub
{ "email": "...", "password": "..." }
```

El hub responde con `{ access_token, refresh_token, user: { id, email, permissions: [...] } }`. El array `permissions` lo computa `EffectivePermissionsResolver(user_id, application_id)` donde `application_id` es la fila que coincide con el `X-App-Key` — es decir, **los permisos para esta app de dominio**, no los del hub. Esto es lo que permite usar el mismo token de login en varias apps de dominio con conjuntos de permisos diferentes.

## Validar una petición (`DomainAuthFilter`)

Monta el filtro en cada grupo de rutas protegidas. El scaffolder lo hace automáticamente (`Config\Scaffolding::protectedRouteFilters = ['domainauth', 'permission:items.read', 'throttle']`).

```
1. El filtro extrae la cabecera Authorization → obtiene <jwt>.
2. HubClient::introspect(<jwt>) → POST <hub>/api/v1/auth/introspect con X-App-Key.
3. El hub valida firma, expiración y blacklist (la lista de revocación de JTI vive en el hub).
4. El hub re-ejecuta EffectivePermissionsResolver(uid, application_id) para ESTA app.
5. El hub devuelve { active, uid, permissions, jti, exp }.
6. El filtro rechaza con 401 si active=false; si no:
      - Cachea la respuesta por jti durante hub.introspectCacheTtl segundos.
      - Llama ApiRequest::setAuthContext(uid, permissions) y ContextHolder::set(uid, permissions).
7. El PermissionFilter downstream funciona sin cambios: lee de ContextHolder.
```

### Invalidación de cache

**No hay invalidación push** entre hub y dominio. Si los permisos de un usuario cambian en el hub, la app de dominio servirá los `permissions[]` cacheados hasta que expire el TTL (60s por defecto). Para la mayoría de flujos es aceptable; si tu dominio necesita propagación más rápida, baja `hub.introspectCacheTtl` o limpia el cache (`php spark cache:clear`) en un hook.

## Llamar al hub de vuelta (service tokens)

Si la app de dominio necesita llamar a endpoints privilegiados del hub (p.ej. obtener metadatos de un usuario para un reporte de auditoría), lo hace con un **service token**:

```
1. HubClient::getServiceToken() → cacheado en memoria hasta exp - serviceTokenSafetyMargin.
2. En miss: POST <hub>/api/v1/auth/service-token con X-App-Key + body firmado.
3. El hub devuelve { access_token, exp } con scope solo para esta application.
4. HubClient inyecta Authorization: Bearer <service_token> en la siguiente llamada.
```

Los service tokens **no satisfacen `iam.superadmin-access`**. Por eso `domain:sync-permissions` (que llama a `POST /api/v1/iam/permissions`) necesita un JWT de superadmin one-shot human-in-the-loop en lugar de un service token.

## Sincronización del catálogo de permisos (setup)

```
1. Edita app/Config/DomainPermissions.php → añade { code, description, category }.
2. php spark domain:sync-permissions --admin-token=<superadmin_jwt>
3. El comando itera PERMISSIONS y llama POST <hub>/api/v1/iam/permissions por cada uno.
4. El hub hace upsert (idempotente — los duplicados se saltan, no fallan).
5. El admin del hub asigna el nuevo permiso a los roles que lo necesiten.
```

El comando es idempotente y se puede re-ejecutar tras corregir el estado del hub (fila `applications` faltante, API key incorrecta, etc.). Sale con código no-cero al primer fallo de auth para no spamear al hub.

## Qué vive dónde

| Aspecto | Hub | App de dominio |
|---|---|---|
| Cuentas de usuario (tabla `users`) | ✅ | ❌ |
| Login / logout / refresh | ✅ | ❌ |
| Reseteo de contraseña, verificación de email, OAuth | ✅ | ❌ |
| Emisión y firma de JWT | ✅ | ❌ |
| Blacklist / revocación de JTI | ✅ | ❌ |
| Catálogo de permisos (tabla `permissions`) | ✅ (los registra el dominio en setup) | ❌ |
| Definición de roles (`roles`, `role_permissions`) | ✅ | ❌ |
| Mapeo `(user, application, role)` (`user_roles`) | ✅ | ❌ |
| **Validar un JWT entrante** | responde vía `/auth/introspect` | llama a `/auth/introspect` |
| **Aplicar permisos por ruta** | ❌ | ✅ (filtro `permission:<código>`) |
| Lógica de negocio del dominio + BD del dominio | ❌ | ✅ |
| Audit logs del dominio | ❌ | ✅ (tabla `audit_logs` aquí) |

## Errores comunes

- **`hub.apiKey` incorrecto** — toda llamada a introspect devuelve 401 desde el hub. El X-App-Key debe coincidir con el `applications.code` de ESTA app de dominio, no del hub.
- **Intentar validar JWT localmente** — no hay clave de firma compartida a propósito. Siempre vía `HubClient::introspect()`.
- **Almacenar tokens en `localStorage`** — solo lado servidor (sesión PHP para admin server-rendered, cabecera para SPAs).
- **Añadir una migración `users` aquí** — alto. Esos datos viven en el hub.
- **Usar `:` en un código de permiso** — usa `.` (p.ej. `items.archive`). El parser de filtros de CI4 divide por `:` para argumentos y trunca silenciosamente `permission:items:archive` en `permission:items`.

## Dónde seguir leyendo

- [`../tech/jwt-auth.es.md`](../tech/jwt-auth.es.md) — resumen corto de la validación.
- [`FILTERS.es.md`](FILTERS.es.md) — el pipeline de filtros completo (`domainauth`, `permission`, `throttle`, idempotency, correlation ID).
- [`../../app/Libraries/Hub/HubClient.php`](../../app/Libraries/Hub/HubClient.php) — único punto de contacto con el hub.
- Repo del hub (`ci4-api-starter`), `docs/tech/jwt-auth.md` y `docs/tech/iam-rbac.md` — internals del lado del hub.
