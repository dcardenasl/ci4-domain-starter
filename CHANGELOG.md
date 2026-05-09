# Changelog

All notable changes to ci4-domain-starter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **`init.sh` validates `ci4-api-core` service wiring after migrations** — runs `php spark core:check` post-migration to confirm all 4 required service factories are registered in `app/Config/Services.php`. Setup now fails fast with a clear error instead of surfacing cryptic `BadMethodCallException`s on the first real request.
- **Swagger generator stability** — `GenerateSwagger` no longer throws `TypeError` when the `components` object is empty (fresh domain with no custom schemas). `UserResponse` reference in `AuthTokenSchema.php` resolved; `public/swagger.json` regenerated to reflect the current route surface.
- **PHPStan baseline** — removed stale `security.php` bootstrap reference from `phpstan.neon` that caused a file-not-found warning on CI after procedural security helpers were consolidated into `dcardenasl/ci4-api-core`.

### Changed
- **Test suite aligned with namespaced API** — `SecurityHelperTest` updated to call `dcardenasl\Ci4ApiCore\Security\*` classes. Stale `ValidatesRequiredFieldsTest` (for a trait no longer in this repo) removed, reducing the test surface to only domain-owned code.
- **Consume base classes from `dcardenasl/ci4-api-core` (CORE-005).** `composer.json` declares `dcardenasl/ci4-api-core: dev-main` via path repo `../ci4-api-core` (replaces the obsolete `ci4-api-crud-maker` entry). All 24 inline base classes deleted from `app/` (HTTP, base DTOs, `PaginatedResponseDTO`, base exceptions, `BaseAuditableModel`, `Auditable`/`HandlesTransactions` traits, `ApiResult`/`OperationResult`/`ExceptionFormatter` support, base interfaces, `ApiController`, `BaseCrudService`, `AuditServiceInterface`). Domain code (controllers, services, models, exceptions, filters, repositories, mappers, factories, configs, tests) imports them from `dcardenasl\Ci4ApiCore\…`. Generated CRUDs from `vendor/bin/make-crud.sh` already emit the new namespace. Architecture tests pruned to the domain's actual surface (3 pure-core tests removed; 6 trimmed to domain artifacts; `FileModelConventionsTest` and the metrics-coupled assertions in `FeatureToggleFilterTest` removed). PHPStan L8 clean, 202 PHPUnit tests green, smoke `make-crud Widget Demo` + `module:check` + server `/health` 200 OK.
- **Documentation overhaul (DOM-106).** `README.md` and `README.es.md` rewritten with quickstart, hub↔domain diagram, command reference, env vars, and pointers into `docs/`. `docs/README.md` and `docs/README.es.md` re-indexed: title corrected (was "API Starter Kit"), broken link to `../GETTING_STARTED.md` removed, dedicated "Hub integration" section added.
- **`docs/tech/jwt-auth.{md,es.md}` and `docs/architecture/AUTHENTICATION.{md,es.md}` rewritten** as hub-aware pointer docs. They now describe `DomainAuthFilter`, `HubClient::introspect()`, the per-app permission re-resolution, and the boundary table (what lives on the hub vs. the domain). The previous content was a stale clone from `ci4-api-starter` describing local JWT issuance/blacklist that this template no longer implements.

### Removed

- **Stale clone docs from `ci4-api-starter`** (DOM-106): `docs/tech/password-reset.{md,es.md}`, `docs/tech/email-verification.{md,es.md}`, `docs/tech/email.{md,es.md}`, `docs/tech/file-storage.{md,es.md}`, `docs/tech/refresh-tokens.{md,es.md}`, `docs/tech/token-revocation.{md,es.md}`. These features were stripped in DOM-001 (they live on the hub); the documentation referenced classes that no longer exist in this repo.

## [0.1.0] — 2026-05-07

### Added

- Initial release of `ci4-domain-starter` — CodeIgniter 4 template for **domain apps** that delegate authentication and IAM to a central hub (`ci4-api-starter`).
- **Hub integration**:
  - `App\Config\Hub` — base URL, X-App-Key, app code, introspect cache TTL, service-token safety margin, optional admin token for setup.
  - `App\Libraries\Hub\HubClient` — single point of contact with the hub. Handles `POST /auth/introspect` (cached per JTI) and `POST /auth/service-token` (cached until expiry minus safety margin). Optional `registerPermission()` for setup-time permission catalog sync.
  - `App\Filters\DomainAuthFilter` (alias `domainauth`) — validates JWTs by calling the hub. Injects `(uid, permissions[])` into `ApiRequest::setAuthContext()` and `ContextHolder` so `PermissionFilter` works unchanged.
- **Permission catalog sync**:
  - `App\Config\DomainPermissions` — declarative source of truth for permissions owned by this domain (`items.read`, `items.write`, `items.delete` by default).
  - `php spark domain:sync-permissions --admin-token=<jwt>` — registers each permission in the hub via `POST /api/v1/iam/permissions`. Idempotent, skips already-existing permissions, exits non-zero if the hub rejects the admin token. Service tokens cannot satisfy `iam.superadmin-access`, so a one-time human-in-the-loop superadmin JWT is required for catalog sync.
- **Scaffolding override**: `App\Config\Scaffolding` overrides `protectedRouteFilters` to `['domainauth', 'permission:items.read', 'throttle']` — every CRUD module generated via `make-crud.sh` is protected by `domainauth` automatically.
- **Example module**: `Items` resource (`app/Controllers/Api/V1/Example/`, `app/Services/Example/`, migration `2026-05-07-061141_CreateItemsTable`) demonstrating the full domain app flow end-to-end.
- **Inherited from the kit hardening (B5–B11)**: security headers, correlation ID propagation, idempotency keys, deprecation headers, RFC 7807 problem details opt-in, maintenance mode filter, JSON file logging, request logs / metrics / audit logs / queue infrastructure.
- **Setup wizard**: `init.sh` prompts for hub coordinates (URL, app code, X-App-Key), DB credentials, optional superadmin JWT, runs `composer install`, `php spark migrate`, `php spark domain:sync-permissions`, and optionally starts the dev server. Supports `--skip-deps`, `--skip-db`, `--skip-sync`, `--skip-server`.
- **Docs**: `CLAUDE.md` (workflow, architecture, command reference), `README.md`, `TASKS.md`, `docs/architecture/`, `docs/runbooks/`, `docs/template/`.
- **CI/CD**: `.github/workflows/ci.yml` (PHPStan level 8 + PHPUnit + CS-Fixer), `release.yml`, `dependabot.yml`. Multi-stage `Dockerfile` running as `www-data`, `docker-compose.yml`, `.dockerignore`.
