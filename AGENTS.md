# ci4-domain-starter

Domain app template (port 8090). Owns its own business logic and database tables.
Delegates auth and IAM to a central hub (`ci4-api-starter`). Never issues JWTs.

## Entry Points

- `app/Filters/DomainAuthFilter.php` — Alias `domainauth`; replaces `jwtauth`; calls `HubClient::introspect()`
- `app/Libraries/Hub/HubClient.php` — Only place that talks to the hub (introspection + service token)
- `app/Config/DomainPermissions.php` — Permission catalog for this domain (dot-separated codes)
- `app/Commands/SyncPermissions.php` — `php spark domain:sync-permissions`
- `app/Config/Scaffolding.php` — Overrides `protectedRouteFilters` to `['domainauth', 'permission:items.read', 'throttle']`

## Contracts & Invariants

- Domain app never issues JWTs — that's the hub's job, always.
- `DomainAuthFilter` (alias `domainauth`) replaces `jwtauth` on all protected routes.
- `HubClient` is the only place that calls the hub — never call hub URLs directly from controllers.
- Permission codes use `.` separator — never `:` (CI4 filter parser splits on `:`).
- `php spark serve --port 8090` — always use SPACE before port, never `=` sign (spark silently ignores `=`).

## Commands

```bash
php spark serve --port 8090       # Note: SPACE not =
vendor/bin/phpunit
composer quality
php spark migrate
php spark domain:sync-permissions --admin-token=<jwt>
bash vendor/bin/make-crud.sh ResourceName Domain 'field:type' yes
```

## Patterns

Adding a new CRUD module:
```bash
bash vendor/bin/make-crud.sh ResourceName Domain 'field:type' yes
php spark migrate
pkill -f 'spark serve'; php spark serve --port 8090 &
```
Generated routes automatically use `domainauth + permission:items.read + throttle`.

Adding a new permission:
1. Append to `app/Config/DomainPermissions.php`.
2. Run `php spark domain:sync-permissions`.
3. Attach the permission to the appropriate role in the hub admin panel.

## Anti-patterns

- Don't issue JWTs from this app.
- Don't call `Services::userModel()` or IAM services — those only exist in the hub.
- Don't hardcode permission strings — use `DomainPermissions::PERMISSIONS` and sync.
- Don't store tokens in `localStorage` — pass via `Authorization` header (SPAs) or PHP sessions (admin layer).

## Related Context

- Detailed reference: `CLAUDE.md` (this repo)
- Hub API it delegates to: `dcardenasl/ci4-api-starter`
- Runtime base classes: `dcardenasl/ci4-api-core` package
