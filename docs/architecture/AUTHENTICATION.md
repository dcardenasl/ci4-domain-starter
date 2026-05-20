# Authentication & Hub Delegation

> Spanish version: [AUTHENTICATION.es.md](AUTHENTICATION.es.md). For a high-level summary see [`../tech/jwt-auth.md`](../tech/jwt-auth.md).

This domain app delegates **all** identity and access concerns to the hub (`ci4-api-starter`). Login, refresh, password reset, email verification, JWT issuance, JTI revocation lists, and the `users` / `roles` / `permissions` tables live on the hub. This document describes the contract between the two.

## Boundary

```
Client (browser / SPA / CLI / service)
        │
        │  Authorization: Bearer <jwt>
        ▼
   Domain app (this repo, :8090)
        │
        │  POST /api/v1/auth/introspect          (validates user JWTs)
        │  POST /api/v1/auth/service-token       (M2M when this app calls back)
        │  POST /api/v1/iam/permissions          (setup-time only — domain:sync-permissions)
        │  X-App-Key: <hub.apiKey>
        ▼
        Hub (ci4-api-starter, :8080)
                ▼
        users / roles / permissions / role_permissions / user_roles
```

The contract is **HTTP, not shared secrets**. The domain app does not have the JWT signing key; trust flows through the introspection endpoint.

## Login (issued by the hub)

The domain app does not own a `/login` endpoint. The client logs in directly against the hub:

```
POST <hub>/api/v1/auth/login
X-App-Key: <hub.apiKey>          # the X-App-Key of THIS domain app, not the hub
{ "email": "...", "password": "..." }
```

The hub returns `{ access_token, refresh_token, user: { id, email, permissions: [...] } }`. The `permissions` array is computed by `EffectivePermissionsResolver(user_id, application_id)` where `application_id` is the row matching the `X-App-Key` — i.e. **this domain app's permissions**, not the hub's. This is what makes the same login token usable across many domain apps with different permission sets.

## Validating a request (`DomainAuthFilter`)

Wire the filter on every protected route group. The scaffolder does this automatically (`Config\Scaffolding::protectedRouteFilters = ['domainauth', 'permission:items.read', 'throttle']`).

```
1. Filter pulls Authorization header → extracts <jwt>.
2. HubClient::introspect(<jwt>) → POST <hub>/api/v1/auth/introspect with X-App-Key.
3. Hub validates signature, expiry, blacklist (JTI revocation list lives on the hub).
4. Hub re-runs EffectivePermissionsResolver(uid, application_id) for THIS app.
5. Hub returns { active, uid, permissions, jti, exp }.
6. Filter rejects with 401 if active=false; otherwise:
      - Caches the response keyed by jti for hub.introspectCacheTtl seconds.
      - Calls ApiRequest::setAuthContext(uid, permissions) and ContextHolder::set(uid, permissions).
7. Downstream PermissionFilter runs unchanged: it reads ContextHolder.
```

### Cache invalidation

There is **no push invalidation** between hub and domain. If a user's permissions change on the hub, the domain app will continue serving the cached `permissions[]` until TTL expires (default 60s). For most flows this is acceptable; if your domain needs faster fan-out, lower `hub.introspectCacheTtl` or call `php spark cache:clear` on a hook.

## Calling back into the hub (service tokens)

If the domain app needs to call privileged hub endpoints (e.g. fetching user metadata for an audit report), it does so with a **service token**:

```
1. HubClient::getServiceToken() → cached in-memory until exp - serviceTokenSafetyMargin.
2. On miss: POST <hub>/api/v1/auth/service-token with X-App-Key + signed body.
3. Hub returns { access_token, exp } scoped to this application only.
4. HubClient injects Authorization: Bearer <service_token> into the next call.
```

Service tokens **cannot satisfy `iam.superadmin-access`**. That's why `domain:sync-permissions` (which calls `POST /api/v1/iam/permissions`) needs a one-time human-in-the-loop superadmin JWT instead of a service token.

## Permission catalog sync (setup-time)

```
1. Edit app/Config/DomainPermissions.php → add { code, description, category }.
2. php spark domain:sync-permissions --admin-token=<superadmin_jwt>
3. Command iterates PERMISSIONS and calls POST <hub>/api/v1/iam/permissions for each.
4. Hub upserts (idempotent — duplicates are skipped, not errored).
5. Hub admin attaches the new permission to whichever roles need it.
```

The command is idempotent and safe to re-run after fixing hub-side state (missing `applications` row, wrong API key, etc.). It exits non-zero on the first auth failure so you don't spam the hub.

## What lives where

| Concern | Hub | Domain app |
|---|---|---|
| User accounts (`users` table) | ✅ | ❌ |
| Login / logout / refresh | ✅ | ❌ |
| Password reset, email verification, OAuth | ✅ | ❌ |
| JWT issuance + signing | ✅ | ❌ |
| JTI blacklist / revocation | ✅ | ❌ |
| Permission catalog (`permissions` table) | ✅ (registered by domain at setup) | ❌ |
| Role definitions (`roles`, `role_permissions`) | ✅ | ❌ |
| `(user, application, role)` mapping (`user_roles`) | ✅ | ❌ |
| **Validating an incoming JWT** | answers via `/auth/introspect` | calls `/auth/introspect` |
| **Enforcing per-route permissions** | ❌ | ✅ (`permission:<code>` filter) |
| Domain business logic + domain DB | ❌ | ✅ |
| Domain audit logs | ❌ | ✅ (`audit_logs` table here) |

## Pitfalls

- **Wrong `hub.apiKey`** — every introspect call returns 401 from the hub. The X-App-Key must match the `applications.code` of THIS domain app, not the hub itself.
- **Trying to validate JWTs locally** — there's no shared signing key on purpose. Always go through `HubClient::introspect()`.
- **Storing tokens in `localStorage`** — server-side only (PHP session for server-rendered admin, header for SPAs).
- **Adding a `users` migration here** — stop. That data lives in the hub.
- **Using `:` in a permission code** — use `.` (e.g. `items.archive`). CI4's filter parser splits on `:` for arguments and silently truncates `permission:items:archive` to `permission:items`.

## Where to read next

- [`../tech/jwt-auth.md`](../tech/jwt-auth.md) — short summary of validation.
- [`FILTERS.md`](FILTERS.md) — the wider filter pipeline (`domainauth`, `permission`, `throttle`, idempotency, correlation ID).
- [`../../app/Libraries/Hub/HubClient.php`](../../app/Libraries/Hub/HubClient.php) — the only place that talks to the hub.
- Hub repository (`ci4-api-starter`), `docs/tech/jwt-auth.md` and `docs/tech/iam-rbac.md` — the hub-side internals.
