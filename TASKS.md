# TASKS — ci4-domain-starter

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md`.
> Última actualización: 2026-06-04 (DOM-109 completado — sync-permissions fail-loud + HubClient fix)

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío)*

---

## ✅ Completadas

### DOM-109 — `domain:sync-permissions`: fail-loud + HubClient role lookup fix (KICK-027)
- **Qué**: (1) `HubClient::findRoleByCode` ahora parsea `{items:[...]}` en vez de `$data[0]` — el API devuelve una colección paginada, no un array plano; (2) `SyncPermissions` ahora termina con `exit≠0` cuando `--assign-to-role` está seteado pero el rol no se encontró/enlazó (`$roleLinkFailed` flag); (3) composer.lock actualizado a ci4-api-core v0.9.3 que incluye `registerPermission(3 params)` para reenviar `applicationId` correctamente; (4) tests añadidos en `HubClientTest` y `SyncPermissionsTest` para los dos behaviors corregidos.
- **Por qué**: en el POC E2E (2026-06-03) `domain:sync-permissions --assign-to-role superadmin` reportaba éxito pero no enlazaba nada al rol: (a) `findRoleByCode` retornaba null porque intentaba `$data[0]` sobre un `{items:[...]}` paginado, y (b) la firma de 2 parámetros en la versión bloqueada de api-core descartaba el `application_id` del mirror.
- **Verificado**: PHP lint limpio, bash -n limpio, tests pasan.

### DOM-103 — `php spark domain:doctor` diagnóstico del hub
- **Qué**: se añadió `php spark domain:doctor` para auditar el enlace del domain starter con el hub. El comando reporta tres checks: `service-token`, `introspect` cuando se pasa `--token`, y `register-permission` cuando se pasa `--admin-token`. El probe de registro usa un payload inválido a propósito para mantenerse read-only y solo validar reachability/autenticación.
- **Por qué**: la tarea pedía un diagnóstico operativo que ayudara a detectar problemas de conectividad y auth sin tener que lanzar manualmente varios comandos de setup.
- **Verificado**: `vendor/bin/phpunit tests/Unit/Commands/DoctorTest.php --testdox --no-coverage` ✅ (2 tests, 17 assertions).

### DOM-105 — Strip `AuthTokenSchema` leftover (2026-05-26)
- **Qué**: se eliminó `app/Documentation/Common/AuthTokenSchema.php`, un leftover heredado del clone de `ci4-api-starter` que ya no correspondía al contrato actual del domain starter. Durante la verificación también se tipó `app/Services/Example/ItemService.php` con el genérico `ItemEntity` y se regeneró `public/swagger.json` para aceptar la salida real del generador OpenAPI.
- **Por qué**: el archivo referenciaba un schema inexistente y hacía más frágil la validación OpenAPI del repo sin aportar valor funcional. El ajuste de generics cerró un drift de PHPStan que apareció al correr `composer quality`.
- **Verificado**: `composer quality` limpio en el repo (PHPStan, CS-Fixer, OpenAPI y PHPUnit).

### DOM-108 — Onboarding desatendido y vinculación de roles (2026-05-25)
- **Qué**: `init.sh` ahora acepta `--assign-to-role=ID|code` y lo pasa a `domain:sync-permissions`. `HubClient` captura `ValidationException` para tratar 422 como éxito idempotente. `init.sh` corre `php spark core:install` automáticamente.
- **Por qué**: (Bulletproof V2) Permitir despliegues 100% automáticos desde el orquestador, vinculando nuevos permisos al rol `superadmin` sin intervención manual. Garantizar que el runtime del core esté listo tras el bootstrap.
- **Verificado**: `php -l` limpio. Scripts probados en flujo de kickstart.

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

### DOM-102 — ADR-001: Hub-Domain Split Architecture (2026-05-26)
- **Qué**: Documentación centralizada en `TASKS.md` y `README.md` sobre la delegación de autenticación, propiedad de permisos y la prohibición explícita de tablas de usuarios en dominios.
- **Por qué**: Establecer la arquitectura canónica para evitar deuda técnica al escalar dominios.
- **Verificado**: Arquitectura documentada en "Contratos de arquitectura".

### DOM-101 — Suite de Smoke tests (2026-05-26)
- **Qué**: Implementación de tests críticos (`DomainAuthFilterTest`, `HubClientTest`, `CreateItemTest`) garantizando la integridad del flujo principal.
- **Por qué**: Asegurar que la delegación de auth y la comunicación con el hub son robustas antes de despliegue.
- **Verificado**: Suite de 145 tests / 353 assertions activa y pasando en `composer quality`.

---

## ⚪ Backlog

*(vacío)*

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
- [x] **Clean .env Management**: Migrate init.sh from appending to .env to using bootstrap_env.php to prevent duplicate keys. ✅ (Verificado en Bulletproof V2)
- [x] **Permission Assignment**: Add --assign-to-role=superadmin option to domain:sync-permissions to automate linking new permissions. ✅ 2026-05-25
