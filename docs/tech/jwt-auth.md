# JWT Validation (delegated to the hub)

> Spanish version: [jwt-auth.es.md](jwt-auth.es.md). For the full delegation flow see [`../architecture/AUTHENTICATION.md`](../architecture/AUTHENTICATION.md).

This domain app **does not issue, refresh, or revoke JWTs**. All token lifecycle operations live on the hub (`ci4-api-starter`).

## What this app does

When a request arrives with `Authorization: Bearer <jwt>`, `App\Filters\DomainAuthFilter` (alias `domainauth`) calls the hub:

```
POST <hub.url>/api/v1/auth/introspect
X-App-Key: <hub.apiKey>
Content-Type: application/json

{ "token": "<jwt>" }
```

The hub answers with `{ active, uid, permissions[], jti, exp, ... }`. The filter:

1. Rejects the request with 401 if `active` is `false` (revoked, expired, or unknown).
2. Re-resolves the user's effective permissions for **this app's** `application_id` (the hub uses `EffectivePermissionsResolver(uid, application_id)`), so the `permissions[]` returned reflect what the user can do *here*, not the verbatim JWT scope.
3. Caches the response per JTI for `hub.introspectCacheTtl` seconds (default `60`).
4. Injects `(uid, permissions[])` into `ApiRequest::setAuthContext()` and `ContextHolder` so downstream `permission:<code>` filters and audit writers work without changes.

## What this app does NOT do

- Issue access tokens, refresh tokens, or password-reset tokens.
- Maintain a `users` / `refresh_tokens` / `password_resets` / `email_verifications` table.
- Decode JWTs locally (no shared signing secret with the hub — the introspection endpoint is the contract boundary).
- Track JTI revocation lists. The hub owns revocation; we trust `active=false`.

## Configuration

See the root [`README.md`](../../README.md) § "Configuration" for the full list. The JWT-related variables are:

| Variable | Purpose |
|---|---|
| `hub.url` | Base URL of the hub. |
| `hub.apiKey` | `X-App-Key` bound to this app's `applications` row in the hub. |
| `hub.introspectCacheTtl` | TTL in seconds for cached introspect responses. Default `60`. |

## Where to read next

- [`../architecture/AUTHENTICATION.md`](../architecture/AUTHENTICATION.md) — full sequence diagram, error mapping, edge cases.
- `app/Libraries/Hub/HubClient.php` — single point of contact with the hub.
- `app/Filters/DomainAuthFilter.php` — the filter wired into protected routes.
- Hub repository (`ci4-api-starter`), `docs/tech/jwt-auth.md` — JWT issuance, signing, blacklist, refresh.
