# TASKS — ci4-domain-starter

> Source of truth for work in this repo.
> Cross-repo coordination lives in `../TASKS.md`.
> Last updated: 2026-05-07 (CORE-005)

---

## 🔴 En progreso

*(empty — DOM-003 cerrado 2026-05-07)*

---

## 🟡 Próximo

*(empty)*

---

## ⚪ Backlog

- **[DOM-101]** Smoke tests: `DomainAuthFilterTest`, `HubClientTest`, `CreateItemTest` end-to-end with mocked HubClient. (Tracked in plan; deferred while the integration with a live hub is validated.)
- **[DOM-102]** ADR-001 documenting the hub-domain split (auth delegation, permission ownership, no users table here).
- **[DOM-103]** `php spark domain:doctor` — diagnostic command that reaches the hub and reports introspect / service-token / register-permission round-trip status.
- **[DOM-105]** Strip orphan `app/Documentation/Common/AuthTokenSchema.php` (DOM-001 leftover from the api-starter clone — references missing `UserResponse` schema, unreachable in this repo, currently breaks `composer swagger-validate`).

(DOM-104 resolved upstream — see CORE-005 below.)

---

## ✅ Completadas recientes

- **[CORE-005] Consumir base classes desde `dcardenasl/ci4-api-core`** (2026-05-07) — `composer.json` ahora declara `dcardenasl/ci4-api-core: dev-main` (path repo `../ci4-api-core`, sustituye al difunto `ci4-api-crud-maker`). Eliminados 24 archivos base inline de `app/`: HTTP/ApiRequest, Libraries/{ApiResponse,ContextHolder}, Controllers/ApiController, DTO/{Request/BaseRequestDTO,SecurityContext,Response/Common/PaginatedResponseDTO}, Exceptions base (ApiException + 3), Models/BaseAuditableModel, Traits/{Auditable,HandlesTransactions}, Support/{ApiResult,OperationResult,ExceptionFormatter}, Interfaces base (5) + Interfaces/System/AuditServiceInterface, Services/Core/BaseCrudService. 75 `use App\…` reescritos a `dcardenasl\Ci4ApiCore\…` vía sed batch + casos especiales (extends/implements en exceptions de dominio, ItemController/ItemService/ItemModel, BaseRepository, DtoResponseMapper, FQCNs hardcoded en `Config/Services.php` y `Config/SystemMonitoringServices.php`). Tests de arquitectura: 3 pure-core eliminados (ApiControllerConventions, BoundaryStaticFacadeConventions, TransactionConventions), FileModelConventions eliminado (no aplica), 6 recortados a artefactos reales del domain (AuditableModel→ItemModel only, ControllerDtoRequestContracts→ItemController only, CrudIndexContracts→domain interfaces, ServiceModelDependency→empty allowlist, ServicesContainerModularity→domain traits). FeatureToggleFilterTest podado (referenciaba `MetricsServiceInterface` inexistente; el filter no usa metrics). PHPStan L8 limpio, 202 tests verdes (1 skipped, vs 31 errores pre-fix), CS-Fixer limpio, smoke `bash vendor/bin/make-crud.sh Widget Demo …` genera código con `use dcardenasl\Ci4ApiCore\…` y `module:check` pasa, server `/health` responde 200. Pre-existente fuera de scope: `swagger-validate` (DOM-105) y `i18n-check` (`Auth.rateLimitExceeded`) ya estaban rojos en HEAD. Plan: `~/.claude/plans/sigue-con-la-planificacion-staged-ocean.md`.
- **[DOM-106] Mejorar README y limpiar stale clones en docs/** (2026-05-07) — Reescritos `README.md` (de 41 a ~170 líneas: quickstart, diagrama hub↔dominio, tabla What's-in-the-box / NOT-in-the-box, comandos comunes, env vars, links a `docs/`) y `README.es.md` (paridad EN/ES). `docs/README.md` y `docs/README.es.md` reescritos: título correcto (domain-starter, no API kit), eliminado link roto a `../GETTING_STARTED.md`, sección dedicada de "Hub integration" al inicio, listas alineadas con archivos que existen realmente. Borrados como stale clones (features que viven en el hub): `docs/tech/{password-reset,email-verification,email,file-storage,refresh-tokens,token-revocation}.{md,es.md}` (12 archivos). Reescritos como punteros al hub: `docs/tech/jwt-auth.{md,es.md}` y `docs/architecture/AUTHENTICATION.{md,es.md}` describen `DomainAuthFilter` + `HubClient` introspect, qué vive dónde, errores comunes. Sin cambios de código. CHANGELOG `[Unreleased]` actualizado.
- **[DOM-003] Rediseñar `domain:sync-permissions`** (2026-05-07) — Opción (a) implementada: el comando exige un JWT de superadmin (los service tokens no satisfacen `iam.superadmin-access` en el hub). Token resuelto por orden `--admin-token=<jwt>` flag → `hub.adminToken` env. `HubClient::registerPermission(array $permission, string $bearerToken)` ya no llama `getServiceToken()`; agrega rama dedicada para 401/403 ("Hub rejected admin token: token missing iam.superadmin-access"). El loop en `SyncPermissions` corta-corto al primer fallo de auth para no spammear el hub. `init.sh` ahora pide pegar el JWT (o saltarse con guía). PHPStan level 8 + cs-check limpios; `swagger-validate` falla por leftover pre-existente del clone (ver DOM-105). Plan: `~/.claude/plans/planifica-dom-003-wiggly-trinket.md`.
- **[DOM-002] End-to-end integración con hub** (2026-05-07) — Tras cerrar API-005 y API-006 en api-starter, validada la flow positiva: login en hub `POST /auth/login` (user `dom002-tester@example.com` con role `superadmin` que tiene `items.read/write/delete` para `application_id=3` example-domain) → JWT issued con scope `self` (apikeys, audit, files, iam.superadmin-access, etc.) → `POST http://localhost:8090/api/v1/example/items` con `Authorization: Bearer {JWT}` → **201 Created** con item persistido. Negative check confirmado: user `dom002-noperms@example.com` (role `user`, sin permisos del dominio) → **403 Forbidden** "Insufficient permissions". El bridge funciona porque `DomainAuthFilter` llama `/auth/introspect` con el `X-App-Key` del domain, y el hub re-resuelve scope vía `EffectivePermissionsResolver(uid, application_id=3)` → devuelve `[items.read, items.write, items.delete]` para superadmin (no el scope verbatim del JWT). Cross-repo `../TASKS.md` actualizado.
- **[DOM-001] Scaffold ci4-domain-starter base** (2026-05-07) — Cloned ci4-api-starter, stripped Auth/IAM/Users/Files/Identity/Admin (controllers, services, DTOs, models, entities, repositories, interfaces, migrations, seeds, commands, tests, lang files); kept BaseAuditableModel + Auditable trait + audit infra (audit_logs migration, AuditService write path) so generated CRUDs persist a local audit log. Added `Config\Hub`, `Config\DomainPermissions`, `App\Libraries\Hub\HubClient`, `App\Filters\DomainAuthFilter` (alias `domainauth`, registered in `Config\Filters`), `App\Commands\SyncPermissions` (`domain:sync-permissions`). `Config\Scaffolding` overrides `protectedRouteFilters` → `['domainauth', 'permission:items.read', 'throttle']`. PHPStan level 8 clean. composer.json switched to `ci4/domain-starter`, removed jwt/google/aws/flysystem/mailer dependencies, repointed `dcardenasl/ci4-api-core` to a path repo. New `init.sh` prompts hub URL + X-App-Key + DB and runs `composer install → migrate → domain:sync-permissions`. Cross-repo tracker (`../TASKS.md`) updated: DOM-001 → ✅, DOM-002 → 🟡.

---

## 🏗️ Architecture contracts (non-negotiable)

- **DTO-First:** every Controller in/out uses DTOs. Request DTOs extend `BaseRequestDTO`. Never raw arrays.
- **Pure services:** services don't know about HTTP. They take DTOs and return DTOs (or throw domain exceptions).
- **Thin controllers:** use `ApiController::handleRequest()`. No business logic in the controller.
- **Permission separator is `.`** (NOT `:`). Reason: `Filters::getCleanName()` uses `explode(':')` and silently truncates.
- **Hub delegation:** never validate JWTs locally. Always call `HubClient::introspect()`.
- **No users table:** if you find yourself adding a `users` migration, stop — that data lives in the hub.
- **Routes per domain:** `app/Config/Routes/v1/<domain>.php`. The system/health endpoint stays at root level.
- **Tests:** every new endpoint needs at least one Feature test (or an explicit waiver in TASKS.md).
- **Run `composer cs-fix` before committing.** Do not bypass the pre-commit hook with `--no-verify`.
