# TASKS — ci4-domain-starter

> Source of truth for work in this repo.
> Cross-repo coordination lives in `../TASKS.md`.
> Last updated: 2026-05-07

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
- **[DOM-104]** Extract base classes (`ApiController`, `BaseCrudService`, `BaseRequestDTO`, `BaseAuditableModel`) to `dcardenasl/ci4-api-core` once SEÑAL-001 in `../TASKS.md` activates (silent divergence between hub and domain base classes).
- **[DOM-105]** Strip orphan `app/Documentation/Common/AuthTokenSchema.php` (DOM-001 leftover from the api-starter clone — references missing `UserResponse` schema, unreachable in this repo, currently breaks `composer swagger-validate`).

---

## ✅ Completadas recientes

- **[DOM-003] Rediseñar `domain:sync-permissions`** (2026-05-07) — Opción (a) implementada: el comando exige un JWT de superadmin (los service tokens no satisfacen `iam.superadmin-access` en el hub). Token resuelto por orden `--admin-token=<jwt>` flag → `hub.adminToken` env. `HubClient::registerPermission(array $permission, string $bearerToken)` ya no llama `getServiceToken()`; agrega rama dedicada para 401/403 ("Hub rejected admin token: token missing iam.superadmin-access"). El loop en `SyncPermissions` corta-corto al primer fallo de auth para no spammear el hub. `init.sh` ahora pide pegar el JWT (o saltarse con guía). PHPStan level 8 + cs-check limpios; `swagger-validate` falla por leftover pre-existente del clone (ver DOM-105). Plan: `~/.claude/plans/planifica-dom-003-wiggly-trinket.md`.
- **[DOM-002] End-to-end integración con hub** (2026-05-07) — Tras cerrar API-005 y API-006 en api-starter, validada la flow positiva: login en hub `POST /auth/login` (user `dom002-tester@example.com` con role `superadmin` que tiene `items.read/write/delete` para `application_id=3` example-domain) → JWT issued con scope `self` (apikeys, audit, files, iam.superadmin-access, etc.) → `POST http://localhost:8090/api/v1/example/items` con `Authorization: Bearer {JWT}` → **201 Created** con item persistido. Negative check confirmado: user `dom002-noperms@example.com` (role `user`, sin permisos del dominio) → **403 Forbidden** "Insufficient permissions". El bridge funciona porque `DomainAuthFilter` llama `/auth/introspect` con el `X-App-Key` del domain, y el hub re-resuelve scope vía `EffectivePermissionsResolver(uid, application_id=3)` → devuelve `[items.read, items.write, items.delete]` para superadmin (no el scope verbatim del JWT). Cross-repo `../TASKS.md` actualizado.
- **[DOM-001] Scaffold ci4-domain-starter base** (2026-05-07) — Cloned ci4-api-starter, stripped Auth/IAM/Users/Files/Identity/Admin (controllers, services, DTOs, models, entities, repositories, interfaces, migrations, seeds, commands, tests, lang files); kept BaseAuditableModel + Auditable trait + audit infra (audit_logs migration, AuditService write path) so generated CRUDs persist a local audit log. Added `Config\Hub`, `Config\DomainPermissions`, `App\Libraries\Hub\HubClient`, `App\Filters\DomainAuthFilter` (alias `domainauth`, registered in `Config\Filters`), `App\Commands\SyncPermissions` (`domain:sync-permissions`). `Config\Scaffolding` overrides `protectedRouteFilters` → `['domainauth', 'permission:items.read', 'throttle']`. PHPStan level 8 clean. composer.json switched to `ci4/domain-starter`, removed jwt/google/aws/flysystem/mailer dependencies, repointed `dcardenasl/ci4-api-crud-maker` to a path repo. New `init.sh` prompts hub URL + X-App-Key + DB and runs `composer install → migrate → domain:sync-permissions`. Cross-repo tracker (`../TASKS.md`) updated: DOM-001 → ✅, DOM-002 → 🟡.

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
