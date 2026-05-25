# TASKS — ci4-domain-starter

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md`.
> Última actualización: 2026-05-16 (BFF-107 ✅ completado — `HubClient` ahora extiende `AbstractServiceClient` del core, drift con el BFF eliminado)

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío)*

---

## ✅ Completadas

### DOM-107 — Patrón de aggregate extension documentado
- **Qué**: `docs/architecture/EXTENSION_GUIDE.{md,es.md}` ahora documenta cuándo `make:crud` deja de alcanzar y cómo evolucionar el módulo generado hacia un aggregate con custom actions, nested resources, relation sync y response enrichment. `README.md` y `docs/README.md` enlazan explícitamente ese patrón.
- **Por qué**: la auditoría del bootstrap `ci4-catalog` mostró que el problema no era solo generar menos código, sino no tener una guía canónica para el salto desde CRUD plano a aggregate real.
- **Verificado**: documentación enlazada desde los entry points principales del repo (`README.md`, `docs/README.md`) y alineada con el playbook de scaffolding existente.

### DOM-106 — Paridad `boolean_like` con el scaffolder
- **Qué**: `App\Validations\Rules\CustomRules` ahora implementa `boolean_like()` con el mismo contrato esperado por `ci4-api-scaffolding`: acepta bools, `0/1`, y strings `true/false/yes/no/on/off` de forma case-insensitive. Se añadieron los strings de validación en `app/Language/en/Validation.php` y `app/Language/es/Validation.php`.
- **Por qué**: el scaffolder emite `boolean_like` para fields `bool`, pero `ci4-domain-starter` no exponía esa regla. Eso rompía CRUDs generados con booleanos y obligaba a parchear DTOs/modelos a mano.
- **Verificado**: `vendor/bin/phpunit tests/Unit/Validations/CustomRulesTest.php --configuration=phpunit.xml --no-coverage --testdox` ✅ (10 tests, 28 assertions).

### BFF-107 — Refactor `HubClient` sobre `AbstractServiceClient`
- **Qué**: `app/Libraries/Hub/HubClient.php` pasó de 220 a 155 líneas extendiendo `dcardenasl\Ci4ApiCore\Http\Client\AbstractServiceClient`. Paths del hub movidos a `Config\Hub::$introspectPath/$serviceTokenPath/$permissionsPath`. `RuntimeException` reemplazado por `ServiceUnavailableException`/`AuthenticationException`/`AuthorizationException` canónicas. `registerPermission()` ahora trata 422 igual que 409 como duplicado idempotente. Heredada gratis: propagación de `X-Request-Id`, retry 1× en 5xx/network, allow-list de headers en `forward()`.
- **Por qué**: eliminar drift entre los dos `HubClient.php` (BFF-102 hizo el mismo refactor en el BFF). Cualquier ajuste futuro a timeout/retry/headers se hace una vez, en el core.
- **Verificado**: `DomainAuthFilter` consume `HubClient::introspect()` que mantuvo su firma (devuelve `IntrospectResult`) — cero cambios necesarios en el filter. `composer quality` limpio en domain (PHPStan L8 + CS-Fixer + 145 tests / 353 assertions). 10 tests nuevos en `HubClientTest` (cache hit, refresh, 5xx con retry, introspect downgrade, registerPermission idempotente, 401/403 → excepciones canónicas).
- **Cross-repo**: ver `../TASKS.md` milestone "ci4-bff-starter v1.1".

---

## ⚪ Backlog

- **[DOM-101]** Smoke tests: `DomainAuthFilterTest`, `HubClientTest`, `CreateItemTest` end-to-end con HubClient mockeado.
- **[DOM-102]** ADR-001 documentando el hub-domain split (auth delegation, permission ownership, no users table aquí).
- **[DOM-103]** `php spark domain:doctor` — comando diagnóstico que alcanza el hub y reporta status de introspect / service-token / register-permission.
- **[DOM-105]** Strip `app/Documentation/Common/AuthTokenSchema.php` (leftover del clone de api-starter — referencia `UserResponse` schema inexistente, rompe `composer swagger-validate`).

---

## 🏗️ Contratos de arquitectura

- **DTO-First:** todo Controller in/out usa DTOs. Request DTOs extienden `BaseRequestDTO`. Nunca arrays raw.
- **Services puros:** no conocen HTTP. Reciben DTOs, devuelven DTOs o lanzan excepciones de dominio.
- **Controllers delgados:** usar `ApiController::handleRequest()`. Sin lógica de negocio.
- **Separador de permisos:** punto `.` (NO `:`).
- **Hub delegation:** nunca validar JWTs localmente. Siempre `HubClient::introspect()`.
- **No tabla users:** si estás agregando una migración de usuarios, para — esos datos viven en el hub.
- **Rutas por dominio:** `app/Config/Routes/v1/<dominio>.php`.
- **Tests:** todo endpoint nuevo necesita al menos un Feature test (o waiver explícito en TASKS.md).
- **`composer cs-fix` antes de commitear.** No bypasear el pre-commit hook con `--no-verify`.

### 🚧 Technical Debt (Orchestration)
- [ ] **Clean .env Management**: Migrate init.sh from appending to .env to using bootstrap_env.php to prevent duplicate keys.
- [ ] **Permission Assignment**: Add --assign-to-role=superadmin option to domain:sync-permissions to automate linking new permissions.
