# Changelog

All notable changes to ci4-domain-starter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.8.1] — 2026-06-12

### Changed

- **Granular permission codes in scaffolding** — `make:crud` now generates separate `create`, `update`, and `delete` permissions instead of a single `write` permission, matching the updated scaffolding engine in `ci4-api-scaffolding` v1.0+. Default route filters configuration (`ScaffoldingConfig`) reflects this split.
- **`DomainPermissions` scaffolding config** — updated example entries to use granular codes (`catalog.product.create`, `catalog.product.update`, `catalog.product.delete`) instead of `write`.

## [1.8.0] — 2026-06-10

### Added

- **`domain:sync-permissions` auto-mint token in development** — in `development` environment, the command now autonomously generates a temporary superadmin JWT by querying the Hub's local database, eliminating the need to manually capture and paste `--admin-token` during local dev setup (DOM-110).
- **`domain:sync-permissions` auto-cache flush** — after a successful sync in `development`, the command automatically runs `cache:clear` in the domain, hub, and admin projects if their directories are detected (DOM-110).
- **`docs/architecture/permissions.md`** — new architecture document detailing the cross-app permission flow (self vs domain application IDs), the `domain:sync-permissions` dev DX, and introspection cache management (DOM-111).

### Changed

- **`dcardenasl/ci4-api-scaffolding`** — updated constraint from `^0.7.6.2` to `^1.0` following the stable v1.0.0 release of the scaffolding package.

## [1.7.2] — 2026-06-06

### Changed

- **`dcardenasl/ci4-api-scaffolding`** — updated lock from v0.7.7 to v0.7.8, which restores PascalCase test directory output for generated CRUD modules.

### Fixed

- **Test directory casing** — migrated `tests/unit`, `tests/integration`, `tests/feature` to PascalCase (`tests/Unit`, `tests/Integration`, `tests/Feature`), removed stale case-duplicate entries left in the git index, simplified `autoload-dev` to a single `"Tests\\": "tests/"` PSR-4 root, and aligned `phpunit.xml`. The directories now match the PascalCase test namespaces and the scaffolding engine's default output paths, so generated CRUD tests are discovered on case-sensitive filesystems instead of being silently skipped.

## [1.7.1] — 2026-06-05

### Fixed

- **`init.sh`** — added support for equals-form CLI arguments (`--flag=value`) alongside the existing space-form (`--flag value`) for `--docker-container`, `--admin-token`, and `--assign-to-role`; also switched `_sync_args` from a plain string to a bash array so arguments with spaces or special characters are passed to `php spark` without word-splitting.

## [1.7.0] — 2026-06-04

### Changed

- **`domain:sync-permissions` primary registration** — now calls `POST /api/v1/iam/self-permissions` via the domain's own X-App-Key instead of `POST /api/v1/iam/permissions` with a superadmin JWT. `--admin-token` is now only required when `--mirror-to-self` or `--assign-to-role` is also set; domain permissions are registered with the correct `application_id` automatically (KICK-021).
- **`dcardenasl/ci4-api-core` constraint** — bumped from `^0.9.0` to `^1.0`; `dcardenasl/ci4-api-scaffolding` bumped to `^0.7.7`.

### Fixed

- **`HubClient::findRoleByCode` role lookup** — the IAM roles index endpoint returns a paginated collection `{items:[...], meta:{...}}` after `decode()` unwraps the `data` envelope. The method was incorrectly reading `$data[0]` (always null on an object) instead of `$data['items'][0]`. Fixed with a tolerant fallback that works on both list and collection shapes.
- **`SyncPermissions` silent role-link failure** — `syncPermissions()` now tracks a `$roleLinkFailed` flag when `--assign-to-role` is set but the role cannot be found or the attach call throws. The exit code is `≠ 0` in that case, making `install.sh` aware of the failure instead of reporting success.
- **`ci4-api-core` locked to v0.9.3** — `composer.lock` updated to include `HubClient::registerPermission(array, string, ?int)` three-parameter signature. Previously locked to v0.9.2, which discarded the optional `applicationId`, causing `--mirror-to-self` to register all permissions under `application_id = null` instead of `1`.

## [1.6.2] — 2026-06-01


### Added

- **`domain:sync-permissions` spark command** — new CLI command for registering domain permissions in the hub. Idempotent operation supports `--admin-token`, `--assign-to-role`, and `--mirror-to-self` flags for setup automation. Replaces manual permission registration in the hub's IAM after domain deployment.

### Fixed

- **Test infrastructure** — registered `Tests\Support\` namespace in `composer.json` autoload-dev for proper test helper and fixture discovery.

### Changed

- **`init.sh`** — enabled `CI4_FORCE_LOG_TO_FILE` conditional flag for log file handling in containerized/CI environments, improving operational visibility without breaking local development.

## [1.6.1] — 2026-05-31

### Fixed

- **PSR-4 autoload mapping** — corrected namespace-to-path mismatch in `composer.json` that caused class-loading failures in non-standard environments; scaffolding defaults cleaned up accordingly.

## [1.6.0] — 2026-05-30

### Fixed

- **Test directory case sensitivity** — renamed `tests/Unit` → `tests/unit`, `tests/Feature` → `tests/feature`, `tests/Integration` → `tests/integration` for compatibility with case-sensitive CI runners on Linux/GitHub Actions.
- **phpunit.xml configuration** — updated to reference lowercase test paths.

## [1.5.2] — 2026-05-30

### Changed

- **`dcardenasl/ci4-api-core` bumped to `v0.9.2`** — lockfile updated to commit `7c64e19`.
- **Audit doc** (`docs/audits/make-crud-audit.md`) — replaced project-specific container name `teatromuseo_mysql` with the canonical `mysql` reference.

## [1.5.1] — 2026-05-29

### Fixed

- **Health route** — `GET /health` now delegates to the app's own `HealthCheckController` instead of the removed `\dcardenasl\Ci4ApiCore\Http\HealthCheckController`; aligns with `ci4-api-core` v0.9.2 which removed that core controller.

## [1.5.0] — 2026-05-29

### Added

- **`AppExceptionHandler`** (`app/Libraries/Exceptions/`) — app-level exception handler extending `BaseExceptionHandler` from `ci4-api-core`; wired into CI4 via `Config\Exceptions::handler()`.
- **Health route delegation** — `GET /health` in `Routes/v1/system.php` now delegates to `\dcardenasl\Ci4ApiCore\Http\HealthCheckController::index`.

## [1.4.0] — 2026-05-29

### Changed

- **Platform Coherence:**
  - Migrated authentication and client logic to `ci4-api-core` v0.9.0.
  - Refactored hub client and infrastructure to align with v2.x architecture.

## [1.3.0] — 2026-05-27

### Added

- **`php spark domain:doctor`** — diagnostic command that validates hub connectivity, API key validity, JWT introspection, service-token acquisition, and permission sync status. Reports each check as pass/warn/fail with actionable messages. Covered by 141-line unit test suite (`tests/unit/Commands/DoctorTest.php`).
- **Automatic permission-to-role assignment in `domain:sync-permissions`** — after registering permissions with the hub, the command now assigns them to the configured default roles. `HubClient` extended with `assignPermissionToRole()` and `registerPermission()` methods. `init.sh` wired to pass `--admin-token` to the command automatically.
- **Custom validation rules** (`app/Validations/Rules/CustomRules.php`) — extensible base for domain-specific validation logic. Covered by `tests/unit/Validations/CustomRulesTest.php`.
- **Extension guide docs** (`docs/architecture/EXTENSION_GUIDE.md` + `.es.md`) — step-by-step instructions for adding new modules, permissions, and hub integrations.

### Changed

- **`dcardenasl/ci4-api-core` bumped to `^0.8.0`**; `dcardenasl/ci4-api-scaffolding` bumped to `^0.6.0`.
- **Example `ItemService` and Swagger contract aligned** — `ItemService` uses typed generics from `BaseCrudService<ItemEntity>`; `public/swagger.json` regenerated.
- **CodeIgniter 4 updated to v4.7.3**.

### Fixed

- **`HubClient` robustness** — service-token and introspect calls now handle network timeouts and malformed hub responses gracefully; exceptions carry the upstream HTTP status.
- **`init.sh` automation** — `--admin-token` is now forwarded as an explicit positional argument to `domain:sync-permissions`, fixing silent no-op when the flag arrived only as an env var.

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

[unreleased]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.5.0...HEAD
[1.5.0]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/dcardenasl/ci4-domain-starter/compare/v1.0.2...v1.1.0
[1.0.0]: https://github.com/dcardenasl/ci4-domain-starter/releases/tag/v1.0.0
