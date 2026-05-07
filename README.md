# ci4-domain-starter

CodeIgniter 4 domain app template. Owns business logic and its own database;
delegates auth / IAM / users to a central hub (`ci4-api-starter`) via
`POST /api/v1/auth/introspect`.

## Quick start

```bash
./init.sh
# Prompts for hub URL, X-App-Key, app code, and DB credentials.
# Runs composer install, migrate, and registers permissions in the hub.
php spark serve --port 8090
```

## Hub coordinates required

Before running `init.sh` make sure the hub:

1. Has an entry in `applications` with the code you'll use here.
2. Has an API key bound to that application (e.g. `php spark apps:bootstrap <code>`).

Without these, `domain:sync-permissions` cannot run. You can re-run it any time.

## What's in the box

- `App\Filters\DomainAuthFilter` (alias `domainauth`) — JWT validation via the hub
- `App\Libraries\Hub\HubClient` — introspect + service-token + register-permission
- `App\Commands\SyncPermissions` (`php spark domain:sync-permissions`)
- `Config\DomainPermissions` — declarative permission catalog for this app
- `Config\Scaffolding` override — `make-crud` generates routes wrapped in `domainauth`

## What's NOT in the box

This is a domain app, not the hub. Out of scope here:

- Users, roles, password reset, email verification, Google OAuth, JWT issuance
- `/api/v1/iam/*` administration endpoints
- File storage drivers (S3, local) — re-add as a domain-specific module if needed

See `CLAUDE.md` for working agreements and `TASKS.md` for in-flight tasks.
