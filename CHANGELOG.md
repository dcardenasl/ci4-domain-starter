# Changelog

All notable changes to ci4-domain-starter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] — 2026-05-24

### Fixed

- `app/Config/Project.php`: bumped `VERSION` constant and `$version` property from `1.1.1` to `1.2.1`; the committed `public/swagger.json` had `1.1.2` while `Project.php` still said `1.1.1`, causing `swagger:generate` to produce a divergent version and the CI swagger-validate step to fail.
- `public/swagger.json`: regenerated to reflect version `1.2.1`.

## [1.2.0] — 2026-05-24

### Added

- **`init.sh --admin-token <jwt>`** — explicit CLI argument for passing the hub superadmin JWT, enabling fully non-interactive permission sync in automated and CI/CD workflows without relying on env-var guards.

### Fixed

- Hub admin token is now correctly persisted for non-interactive `domain:sync-permissions` runs when supplied via `--admin-token`.

## [1.1.2] — 2026-05-23

### Fixed

- `app/Config/Project.php` now identifies the repo as `CodeIgniter 4 Domain Starter` instead of the API starter template, and `public/swagger.json` was regenerated to match.

## [1.1.1] — 2026-05-23

### Fixed

- `scripts/bootstrap_env.php` now accepts commented placeholders (`; key = value` / `# key = value`) when updating `.env` files.

## [1.1.0] — 2026-05-23

### Added

- **`init.sh` `--docker-container` support** — new optional CLI flag enables isolated Docker container initialization workflow. Useful for CI/CD pipelines and containerized development where environment isolation is critical.

## [1.0.2] — 2026-05-23

### Fixed

- `app/Config/Scaffolding.php`: updated namespace imports from the retired `dcardenasl\CI4ApiCrudMaker` to `dcardenasl\Ci4ApiScaffolding`. The stale namespace caused CI4's `config('Scaffolding')` to throw a class-not-found error, making `MakeCrud` fall back to empty `protectedRouteFilters` (routes generated without auth filters).

## [1.0.1] — 2026-05-22

### Fixed

- `CLAUDE.md`: added explicit warning that `--port=<n>` (equals sign) is silently ignored by `php spark serve`; use `--port <n>` (space) to avoid the server starting on the default port and colliding with the hub.
- `init.sh`: switched `.env` value injection from raw `printf` appends to `php scripts/bootstrap_env.php` (handles quoted/unquoted existing values correctly) and added `php spark key:generate --force` for the encryption key.

### Dependencies

- Updated `dcardenasl/ci4-api-scaffolding` (require-dev) to `^0.5.0`.

## [1.0.0] — 2026-05-20

First stable release. This version formalises the commitment to semantic versioning — the 0.1.0 entry below is preserved as historical context for the pre-release codebase but was never tagged or published. v1.0.0 ships the runtime foundation, hub auth delegation, scaffolding overrides, the example `Items` module, the full hardening surface inherited from `dcardenasl/ci4-api-core`, the documentation overhaul (DOM-106), a tag-driven release workflow, dependency updates to `ci4-api-core ^0.7.0` and `codeigniter4/framework ^4.7`, and the audit code fixes from BFF-M1/M2.

### Added
- **Runtime foundation pinned to `dcardenasl/ci4-api-core` v0.4.1.** `composer.json` now declares the constraint `^0.4.1` against the published Packagist version (previously `dev-main` via path repository); the local `../ci4-api-core` path repository is preserved as a non-canonical override so workspace contributors can still cross-edit without modifying the constraint. Downstream consumers resolve cleanly from Packagist.

### Changed
- **`dcardenasl/ci4-api-core` bumped to `^0.7.0`** — picks up `AbstractServiceClient`, `IntrospectResult`, `AbstractIntrospectionFilter`, and `HubClientInterface` from core. The domain app's `HubClient` migrated onto `AbstractServiceClient` (v0.5.0); v0.6.0 widened the CI4 requirement to `^4.7`; v0.7.0 promotes the shared types described below.
- **`DomainAuthFilter` refactored** — extends `dcardenasl\Ci4ApiCore\Http\Filters\AbstractIntrospectionFilter` instead of reimplementing the full introspect flow. Now implements only the `introspect(string $token): IntrospectResult` hook; Bearer extraction, `ContextHolder` population, and 401 responses are handled by the inherited `AbstractJwtAuthFilter`. Reduces the filter from 77 to ~20 lines.
- **`ThrottleFilter` simplified** — empty extension of `dcardenasl\Ci4ApiCore\Http\Filters\AbstractThrottleFilter` (105 → 10 lines). `App\Filters\Concerns\RateLimitResponseHelpers` trait deleted; fixed-window IP + user-id bucketing is fully inherited from the core base class.
- **`HubClient` now implements `HubClientInterface`** — declares `dcardenasl\Ci4ApiCore\Contracts\HubClientInterface`; `getUser()` method added to satisfy the interface contract.
- **`IntrospectResult` local copy deleted** — all code imports `dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult` from `ci4-api-core`.
- **`codeigniter4/framework` constraint bumped to `^4.7`** — locks to the current stable CI4 (v4.7.2). README CI4 badge updated from 4.5 to 4.7.
- **`codeigniter4/framework` constraint bumped to `^4.7`** — locks to the current stable CI4 (v4.7.2); the effective floor was already 4.6 (transitively via `ci4-api-core`). README CI4 badge updated from 4.5 to 4.7.
- **`php-cs-fixer` bumped to `^3.95`** in dev dependencies.
- **Consume base classes from `dcardenasl/ci4-api-core` (CORE-005).** All 24 inline base classes deleted from `app/` (HTTP, base DTOs, `PaginatedResponseDTO`, base exceptions, `BaseAuditableModel`, `Auditable` / `HandlesTransactions` traits, `ApiResult` / `OperationResult` / `ExceptionFormatter` support, base interfaces, `ApiController`, `BaseCrudService`, `AuditServiceInterface`). Domain code (controllers, services, models, exceptions, filters, repositories, mappers, factories, configs, tests) imports them from `dcardenasl\Ci4ApiCore\…`. Generated CRUDs from `vendor/bin/make-crud.sh` already emit the new namespace. Architecture tests pruned to the domain's actual surface (3 pure-core tests removed; 6 trimmed to domain artifacts; `FileModelConventionsTest` and the metrics-coupled assertions in `FeatureToggleFilterTest` removed). PHPStan level 8 clean, PHPUnit suite green, smoke `make-crud Widget Demo` + `module:check` + server `/health` 200 OK.
- **Test suite aligned with namespaced API.** `SecurityHelperTest` updated to call `dcardenasl\Ci4ApiCore\Security\*` classes. Stale `ValidatesRequiredFieldsTest` (for a trait no longer in this repo) removed, reducing the test surface to only domain-owned code.
- **Documentation overhaul (DOM-106).** `README.md` and `README.es.md` rewritten with quickstart, hub↔domain diagram, command reference, env vars, and pointers into `docs/`. `docs/README.md` and `docs/README.es.md` re-indexed: title corrected (was "API Starter Kit"), broken link to `../GETTING_STARTED.md` removed, dedicated "Hub integration" section added.
- **`docs/tech/jwt-auth.{md,es.md}` and `docs/architecture/AUTHENTICATION.{md,es.md}` rewritten** as hub-aware pointer docs. They now describe `DomainAuthFilter`, `HubClient::introspect()`, the per-app permission re-resolution, and the boundary table (what lives on the hub vs. the domain). The previous content was a stale clone from `ci4-api-starter` describing local JWT issuance / blacklist that this template no longer implements.

### Fixed
- **`init.sh` validates `ci4-api-core` service wiring after migrations** — runs `php spark core:check` post-migration to confirm all 4 required service factories are registered in `app/Config/Services.php`. Setup now fails fast with a clear error instead of surfacing cryptic `BadMethodCallException`s on the first real request.
- **Swagger generator stability** — `GenerateSwagger` no longer throws `TypeError` when the `components` object is empty (fresh domain with no custom schemas). `UserResponse` reference in `AuthTokenSchema.php` resolved; `public/swagger.json` regenerated to reflect the current route surface.
- **PHPStan baseline** — removed stale `security.php` bootstrap reference from `phpstan.neon` that caused a file-not-found warning on CI after procedural security helpers were consolidated into `dcardenasl/ci4-api-core`.

### Docs

- **README** — corrected the stale paragraph claiming the base classes "live in-tree and will be extracted to `dcardenasl/ci4-api-core` (DOM-104)". The extraction already shipped; the README now states the base classes are consumed from the `dcardenasl\Ci4ApiCore\…` namespace.

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

[unreleased]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.0.2...v1.1.0
[1.0.0]: https://github.com/dcardenasl/ci4-domain-starter/releases/tag/v1.0.0
