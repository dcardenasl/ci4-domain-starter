# CLAUDE.md

Guidance for Claude Code when working in this repository.

## ⚡ Workflow — read this first

**Before touching any code, read `TASKS.md` in this directory.**

1. Take the first task from `## 🟡 Próximo`
2. Move it to `## 🔴 En progreso`
3. Work exclusively on that task — if anything is unclear, ask before implementing
4. When done: move it to `## ✅ Completadas` with one line of notes (what you did and why)
5. Never work on tasks not defined in TASKS.md without explicit confirmation

For cross-repo context, read `../TASKS.md`.

---

## What this is

`ci4-domain-starter` is a CodeIgniter 4 **domain app** template. It owns its own
business logic and database tables, but **delegates auth and IAM to a central
hub** — a separate `ci4-api-starter` instance that stores users, applications,
roles and permissions.

The split:

```
Browser/SPA → Domain App (here)        → Database (this app's tables)
                ↓ JWT validation
              Hub (ci4-api-starter)    → Database (users, roles, perms)
                ↑ Service token (M2M)
              Domain App  ─────────────┘
```

**Boundaries:**

- Domain app **never issues JWTs**. The hub does.
- Domain app **validates JWTs** by calling `POST /api/v1/auth/introspect` on the hub.
- Domain app **registers its permissions** in the hub via `POST /api/v1/iam/self-permissions`
  using its own X-App-Key (`hub.apiKey`). No superadmin JWT required for the primary registration.
  `--admin-token` is only needed when `--mirror-to-self` (**deprecated**, see below) or
  `--assign-to-role` is also set.
- Domain app **does not store users**. There is no `users` table here.

## Essential commands

```bash
# Dev server (default port 8190 to avoid colliding with hub on :8180 / admin on :8182)
# IMPORTANT: CI4 spark serve requires a SPACE before the port — equals sign is silently ignored:
#   php spark serve --port 8190   ✅
#   php spark serve --port=8190   ❌ (starts on :8180 without warning, collides with hub)
php spark serve --port 8190

# Tests
vendor/bin/phpunit
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Integration
vendor/bin/phpunit tests/Feature

# Quality gates
composer quality          # phpstan + cs-check + phpunit
composer cs-fix           # auto-fix style

# Database
php spark migrate         # idempotency_keys, audit_logs, request_logs, metrics, jobs

# Hub permission sync (idempotent — safe to rerun). Needs a superadmin JWT.
php spark domain:sync-permissions --admin-token=<jwt>     # or set hub.adminToken in .env

# CRUD scaffolding (always use the shell wrapper)
bash vendor/bin/make-crud.sh ResourceName DomainName 'field1:type,field2:type' yes
```

## Architecture cheat sheet

The DTO-first layered pattern is identical to ci4-api-starter:

```
Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO]
```

Base classes live in `dcardenasl/ci4-api-core` (path repo `../ci4-api-core`,
declared in `composer.json` and consumed under `vendor/dcardenasl/ci4-api-core/`).
Generated and hand-written code imports them directly from the package namespace:

- `dcardenasl\Ci4ApiCore\Http\ApiController` — declarative `handleRequest()` orchestration
- `dcardenasl\Ci4ApiCore\Services\BaseCrudService` — pure, transactional service layer
- `dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO` — auto-validating DTOs
- `dcardenasl\Ci4ApiCore\Models\BaseAuditableModel` — local audit logging via `Auditable` trait

What's **different** here:

- `App\Filters\DomainAuthFilter` (alias `domainauth`) replaces `JwtAuthFilter`. It
  calls `HubClient::introspect()` and injects `(uid, permissions[])` into
  `ApiRequest::setAuthContext()` and `ContextHolder` so `PermissionFilter` works
  unchanged.
- `App\Libraries\Hub\HubClient` is the only place that talks to the hub. It
  handles introspection caching (TTL configurable via `hub.introspectCacheTtl`)
  and service-token caching (refreshed `serviceTokenSafetyMargin` seconds before
  expiry).
- `App\Commands\SyncPermissions` (`php spark domain:sync-permissions`) registers
  every permission in `Config\DomainPermissions::PERMISSIONS` using the domain's
  own `X-App-Key` via `POST /api/v1/iam/self-permissions` — no superadmin JWT needed
  for the primary registration. `--mirror-to-self --admin-token=<jwt>` additionally
  registers the permissions under hub app `self` (application_id=1) for admin UI gating,
  but this flag is **[DEPRECATED]**: the hub now resolves permissions across every
  registered application via `resolveAll()`, so permissions registered here under this
  app's own X-App-Key are already picked up without mirroring — the flag will be
  removed in a future release. The CRUD scaffolder appends the standard
  `{resource}.read/write/delete` entries automatically.
- `Config\Scaffolding` overrides `protectedRouteFilters` to
  `['domainauth', 'permission:items.read', 'throttle']` — generated CRUDs are
  protected by `domainauth` automatically.

## Adding a new permission

1. Edit `app/Config/DomainPermissions.php` only for manual/legacy permissions —
   the CRUD scaffold appends the standard `{resource}.read/write/delete`
   entries automatically for new resources.
2. Run `php spark domain:sync-permissions` — registers in the hub (idempotent).
3. In the hub admin panel, attach the new permission to the role(s) that should
   carry it.
4. Use the code in your filter argument: `permission:items.archive`.

## Adding a new CRUD module

```bash
bash vendor/bin/make-crud.sh Item Example 'name:string:required|searchable,description:text' yes
php spark migrate
pkill -f 'spark serve'; php spark serve --port 8190 &
```

The generator emits routes already wrapped in `domainauth + permission:items.read + throttle`
— no manual filter wiring needed. Update the route filters per HTTP verb if your
module needs distinct read/write codes.

## Required environment variables

| Variable | Purpose |
|---|---|
| `hub.url` | Base URL of the hub (e.g. `http://localhost:8180`) |
| `hub.apiKey` | X-App-Key bound to this domain app's `applications` row in the hub |
| `hub.appCode` | Application code as registered in the hub |
| `hub.introspectCacheTtl` | (optional) TTL in seconds for cached introspect responses, default 60 |
| `hub.adminToken` | (optional) Superadmin JWT. Only needed when running `domain:sync-permissions --mirror-to-self` (**deprecated**) or `--assign-to-role`. |
| `database.default.*` | Domain app's own MySQL connection |
| `encryption.key` | CI4 encryption key (32 bytes after `hex2bin:` decode) |

## Setup prerequisite

`init.sh` runs `php spark domain:sync-permissions --admin-token=<jwt>` against the hub.
The primary permission registration uses the domain's own X-App-Key (`hub.apiKey`) via
`POST /api/v1/iam/self-permissions` — **no superadmin JWT required** for this step.

The call requires:

1. The hub is running and reachable.
2. An entry in the hub's `applications` table with `code = hub.appCode`.
3. An API key in the hub bound to that application (see `php spark apps:bootstrap`
   on the hub side). Kickstart sets this automatically during `DOMAIN_BOOTSTRAP`.
4. `--admin-token` is required only when `--mirror-to-self` or `--assign-to-role`
   is set. The hub gates `POST /api/v1/iam/permissions` (used for the mirror) on
   `iam.superadmin-access`. Obtain via `POST /api/v1/auth/login` as superadmin.

`--mirror-to-self` (registers permissions under hub app `self`, `application_id = 1`,
for admin UI gating; Kickstart controls this via `mirror_to_hub: true` in
`template.json`) is **[DEPRECATED]**. The hub now resolves a user's effective
permissions across every registered application via `resolveAll()`, so permissions
this domain registers under its own X-App-Key are already reflected in issued JWTs —
mirroring them into the hub's self namespace is no longer necessary. The flag still
works but will be removed in a future release.

You can re-run `domain:sync-permissions` at any time — it is idempotent.

## Static analysis

PHPStan runs at level 8 with a `phpstan-baseline.neon` that tracks historical type-debt.
**Rule:** the baseline entry count can only decrease. New code must not introduce new errors
against the level-8 ruleset. Current count: **0 baselined entries** — the historical type-debt
was fully drained. New code must not add any.

Run before pushing:

```bash
composer quality
```

## Common pitfalls

- ❌ Issuing JWTs from this app — that's the hub's job, always.
- ❌ Calling `Services::userModel()` or any IAM service — those only exist in the hub.
- ❌ Storing tokens in cookies/localStorage on the client side — use PHP sessions
  (admin/web layer) or pass via Authorization header (SPAs).
- ❌ Hardcoding permission strings — use `DomainPermissions::PERMISSIONS` and
  `domain:sync-permissions` so the hub stays in sync.
- ❌ **Running `php spark migrate` (or any migration/refresh command) without checking which
  database you're targeting.** With no `-g` flag it targets `database.default` — the persistent
  **dev** database — not `database.tests`. Test-only workflows must use
  `php spark tests:prepare-db` (which explicitly connects to the `tests` group) or pass
  `-g tests` yourself. Running an untargeted `migrate --all`/`migrate:refresh` against a database
  you didn't mean to touch can drop and recreate its schema empty. This template ships with no
  seed/demo data (see "Required vs. optional bootstrap" in README.md), so recovering just means
  re-running `php spark migrate` and rebuilding through the app's own CRUD screens — there is no
  seeder to fall back on.

## Architectural patterns for future growth

This template currently has no N:M relations, no service over a few hundred lines, and
no Spark commands with extra collaborators — so none of the code below exists here yet.
These are lessons backported from a **child project** (a CMS domain app built on this
same template) that grew large enough to hit these problems. Apply them **when** this
template grows to the point they apply, not preemptively.

### 1. Batch-resolve pattern for N+1 avoidance

**What:** When a list/paginated endpoint needs to attach related or pivot (N:M) data
to each row — categories, tags, translations, anything joined through a pivot table —
resolve it in one batch pass over the whole page, not per-row inside the loop.

**When it applies:** As soon as you add a `belongsToMany`-style relation (a pivot table)
and expose a paginated list endpoint that needs to embed the related data in each item.

**Shape:**

```php
// 1. Run the paginated query first, collect just the IDs.
$rows = $model->where(...)->findAll($perPage, $offset);
$ids  = array_map(fn ($r) => (int) $r->id, $rows);

// 2. One whereIn() query per related-entity type, keyed by parent ID.
$pivotMap = $this->batchResolvePivot($ids); // returns [parentId => [related...]]

// 3. Map back onto each row in PHP — zero extra queries per row.
foreach ($rows as $row) {
    $item = $row->toArray();
    $item['related'] = $pivotMap[$row->id] ?? [];
}
```

**Why:** The naive version — loop over rows, query the pivot table per row — is a
classic N+1: a page of 50 items becomes 50+ queries instead of 2-3. This scales with
`per_page`, so it degrades silently until someone raises the page size. Reference
implementation: `PublicEntryReader::batchResolveCategoryPivot()` /
`batchResolveTagPivot()` / `batchResolveEntryTranslations()` in the child project
(`app/Services/Cms/PublicEntryReader.php`) — one `whereIn()`-based query per relation
type, results mapped back onto rows by parent ID after the fact.

### 2. Service decomposition discipline

**What:** A size/complexity trigger for splitting an overgrown service class, plus the
verification discipline to do it safely.

**When it applies:** A service class crosses roughly **600-800 lines**, or you notice
it mixing more than one responsibility (e.g. the read/list path with its N+1-safe
batch resolution, versus a write-path transactional workflow) — that's the signal to
extract, not a hard line count.

**Shape:** Pull the cohesive sub-responsibility into its own class, composed by the
original service rather than inherited:

```php
class EntryService
{
    public function __construct(
        private PublicEntryReader $reader,               // read path, N+1-safe
        private EntryBlockTemplateInitializer $init,      // transactional write path
    ) {}
}
```

**Discipline used by the child project when doing this:**
1. Extract one cohesive concern at a time (e.g. "everything the public read path
   needs" or "everything the audit-diff logic needs") — not an arbitrary line split.
2. Run the full test suite + PHPStan **after each extraction**, not just at the end.
3. Before deleting anything from the original class, `grep` for call sites —
   including **test fixtures and test doubles**, not just application code. A method
   can look dead in `app/` and still be referenced from a test mock.

**Why:** A 1000+ line service is hard to review, hard to reason about for N+1 safety,
and tends to accumulate unrelated responsibilities over time. The child project split
a 1109-line `EntryService` into a 356-line `PublicEntryReader` (read/N+1 concerns) and
a 172-line `EntryBlockTemplateInitializer` (transactional init), and separately split a
1234-line `TranslationAuditService` into a support/diff class and a
`BlockInstanceTranslationAuditor`. Each half is now independently testable and easier
to keep N+1-safe.

### 3. Batch+cache idiom for avoiding N+1-over-HTTP

**What:** The same N+1 problem shows up when a domain app calls **out to the Hub**
for per-ID metadata (e.g. resolving file URLs for a batch of `file_id`s embedded in
content) — except now each "query" is an HTTP round-trip, which is far more expensive
than a SQL query. Split the requested IDs into already-cached vs. missing, issue
**one** HTTP call for the missing subset, cache each result individually.

**When it applies:** Any time a domain app needs to enrich a list of local rows with
metadata that only the Hub knows about (file URLs, user display names, anything not
replicated locally) and that enrichment would otherwise happen per-row in a loop.

**Shape:**

```php
public function resolvePublicFileMeta(array $fileIds, int $cacheTtl = 300): array
{
    $cache = service('cache');
    $result = [];
    $miss = [];

    foreach ($fileIds as $id) {
        $cached = $cache->get($this->cacheKey($id));
        $cached !== null ? $result[$id] = $cached : $miss[] = $id;
    }

    if (empty($miss)) {
        return $result;
    }

    // ONE HTTP call for everything that wasn't cached.
    $data = $this->request('GET', '/api/v1/internal/files/batch-meta', ['query' => ['ids' => $miss]]);
    foreach ($data as $id => $meta) {
        $result[$id] = $meta;
        $cache->save($this->cacheKey($id), $meta, $cacheTtl);
    }

    return $result;
}
```

**Why:** Calling the Hub once per file ID inside a render loop turns a page load into
N HTTP round-trips — each with its own network latency, far worse than N extra SQL
queries. Reference implementation: `HubClient::resolvePublicFileMeta()` in the child
project (`app/Libraries/Hub/HubClient.php`) — cache-first, one batched HTTP call for
the miss set, individual per-item caching so a later request with a different ID mix
still gets partial cache hits. If you add a similar Hub-batch endpoint, wire it through
`HubClient` (this template's existing single point of contact with the Hub) using the
same split-then-batch-then-cache shape.

### 4. Setter injection for Spark Commands with extra dependencies

**What:** When a Spark Command needs app-specific collaborators beyond what
`BaseCommand` provides (e.g. a resolver + a synchronizer for a one-off backfill
script), expose them via public setters with `service()`/`new`-resolved defaults in
`run()` — do not override `BaseCommand`'s constructor.

**When it applies:** Any new maintenance/backfill Spark Command whose logic needs
collaborators that should be swappable in tests (mocked resolvers, fake HTTP clients,
etc.).

**Shape:**

```php
class BackfillSomething extends BaseCommand
{
    private ?SomeResolver $resolver = null;

    public function setResolver(SomeResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function run(array $params): void
    {
        $resolver = $this->resolver ?? new SomeResolver(Database::connect());
        // ... use $resolver
    }
}
```

The test then does:

```php
$command = new BackfillSomething();
$command->setResolver($mockResolver);
$command->run([]);
```

**Why:** `BaseCommand` has its own constructor signature (wired by Spark's command
runner) that's easy to break by overriding — get the signature wrong and the command
silently fails to register. Setter injection with a lazy default in `run()` sidesteps
that entirely: production code gets the real collaborator by default, tests inject a
mock after construction, and `BaseCommand`'s contract is never touched. Reference
implementation: `BackfillCmsFileReferences::setResolver()` /
`setSynchronizer()` in the child project (`app/Commands/BackfillCmsFileReferences.php`).

## Security & caching patterns for future growth

Like the section above, none of this exists in the template yet — this app has no
caching layer, no downstream consumer to notify on writes, no public-but-app-gated
route tier, and no rich-text/free-form-HTML fields. These four patterns are backported
from the same **child project** (the CMS domain app + its sibling public-website app,
both built on this template) for when this template grows into those shapes. Apply
**when** relevant, not preemptively — with one exception below where the guidance
includes a bug fix that must be applied on adoption, not left for later.

### 1. Scoped cache-key naming for selective invalidation

**What:** Build cache keys as `{prefix}_{scope}_{hash}` (e.g. `web_api_v2_pages_a3f9...`)
instead of an unscoped `{prefix}_{hash}`, so a targeted invalidation can glob-match and
clear only the affected scope's keys.

**When it applies:** If this template ever grows a caching layer with scoped
invalidation (e.g. a downstream public-facing app caching this domain's API responses
and needing to invalidate just `pages` or just `menus` on a content change) — build the
key convention in from day one. Retrofitting a scope segment into an already-populated
cache key space later is unnecessary churn (every existing key has to expire or be
migrated before `deleteMatching()` on the new pattern is trustworthy).

**Shape:**

```php
// Write path — key includes the scope segment up front.
$keySuffix = $scope . '_' . md5($url . '|' . $locale);
$cacheKey  = 'web_api_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
$cache->save($cacheKey, $result, $cacheTtl);

// Invalidation path — glob-match on the scope segment only.
$deleted = $cache->deleteMatching('web_api_*_' . $scope . '_*');
```

**Why:** An unscoped key (`{prefix}_{hash}`) forces a choice between flushing the whole
cache on any write (wasteful — throws away unrelated, still-valid entries) or trying to
reconstruct the exact original key to delete it one at a time (fragile — a hash built
from `url + locale` isn't reproducible from the invalidation call site). A scope segment
makes `CacheInterface::deleteMatching()` precise: one glob clears exactly the affected
scope and nothing else. Reference implementation: `ci4-website-builder-web`'s
`app/Libraries/WebApiClient.php` (key construction) and `app/Libraries/CacheInvalidator.php`
(`deleteMatching('web_api_*_' . $scope . '_*')`) — a sibling app in the child project,
not this domain repo, but the domain app is what triggers these invalidations (see
pattern 2 below).

### 2. Fire-and-forget webhook notifier for downstream cache invalidation

**What:** A small client whose job is to notify a downstream consumer that something
changed, without ever letting that notification's failure affect the write path that
triggered it: constructor takes a URL + shared secret from env, a `notify(array $payload)`
(or domain-specific equivalent, e.g. `invalidate(array $scopes)`) method that **never
throws**, a short cURL timeout, and a shared-secret header checked with `hash_equals()`
on the receiving side.

**When it applies:** Any time a domain app built on this template needs to push an
event to a downstream consumer (a public website invalidating its cache, a webhook
subscriber, anything outside this app's own transaction) and that consumer's uptime
must not become a dependency of this app's write path succeeding.

**Shape:**

```php
class DownstreamNotifierClient
{
    private string $url;
    private string $sharedSecret;

    public function __construct(string $url = '', string $sharedSecret = '')
    {
        $this->url          = rtrim($url ?: (string) env('DOWNSTREAM_NOTIFY_URL', ''), '/');
        $this->sharedSecret = $sharedSecret ?: (string) env('DOWNSTREAM_NOTIFY_KEY', '');
    }

    public function notify(array $payload): void
    {
        if ($this->url === '' || $this->sharedSecret === '') {
            return; // not configured — silently no-op, never block the caller
        }

        $ch = curl_init($this->url);
        if ($ch === false) {
            log_message('error', '[DownstreamNotifierClient] curl_init() failed.');
            return;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['X-Notify-Key: ' . $this->sharedSecret],
        ]);

        $raw = curl_exec($ch);
        // ... check status, log on failure, but ALWAYS return — never throw.
        curl_close($ch);
    }
}
```

Call sites wrap this in a try/catch anyway as defense-in-depth, but the class itself is
built so that isn't strictly required — every failure path (bad URL, cURL error,
non-2xx response, JSON encode failure) logs and returns rather than throwing.

**Why:** Coupling a write's success to a downstream consumer's availability turns "the
cache-invalidation webhook is down" into "content saves are down" — a reliability
regression with no upside. A short timeout (5s) bounds the worst-case latency added to
the write path; the shared-secret header + `hash_equals()` on the receiving side
prevents unauthenticated third parties from triggering the downstream action (e.g.
forcing cache flushes as a DoS vector). Reference implementation:
`app/Libraries/Cms/CacheInvalidationClient.php` in the child project
(`ci4-website-builder-domain`) — not portable as code today (no downstream consumer
exists in this template yet), so treat the above as the reusable shape, not a file to
copy in as-is.

### 3. App-key gate for a public-but-app-gated route tier — ⚠️ port the fix, not the file

**What:** A filter that gates a route tier meant to be callable only by a known,
trusted frontend (not end users, not third parties) via a shared `X-App-Key`-style
header checked with `hash_equals()` — useful for endpoints that must stay unauthenticated
(no JWT, no user session) but shouldn't be open to the public internet.

**When it applies:** If this template ever grows a "public but app-gated" route tier —
endpoints intentionally exposed without `domainauth`, but that should only ever be
called by one specific consuming frontend that holds a shared secret.

**⚠️ The reference implementation has a real fail-open bug — do not copy it verbatim.**
In the child project's `app/Filters/WebAppKeyRequiredFilter.php`:

```php
$configuredKey = (string) env('WEB_API_KEY', '');
if ($configuredKey === '') {
    return null;   // BUG: empty/unset config key → filter allows the request through
}
```

If `WEB_API_KEY` is empty or unset in the environment — a misconfiguration, not an
intentional "disable the gate" signal — the filter's `before()` returns `null`, which in
a CodeIgniter filter means *allow the request to proceed*. An unconfigured app-key gate
silently becomes no gate at all. This directly contradicts the fail-closed pattern this
same codebase already uses elsewhere for exactly this class of problem: `Config\Hub`'s
constructor (see `app/Config/Hub.php` in this repo, hardened under WBS-BP-12) throws a
`LogicException` at boot when `hub.url`/`hub.apiKey`/`hub.appCode` are missing, rather
than falling back to a silently-permissive default.

**Corrected shape — the empty-key branch must deny, not allow:**

```php
public function before(RequestInterface $request, $arguments = null): ResponseInterface|null
{
    $configuredKey = (string) env('WEB_API_KEY', '');
    if ($configuredKey === '') {
        // Fail closed: an unconfigured gate is a misconfiguration, not "no gate".
        // Prefer failing at boot (LogicException in the owning Config class, same
        // pattern as Config\Hub) over a runtime 401/403 discovered only when a
        // request comes in — but either is correct as long as it denies.
        return \Config\Services::response()
            ->setStatusCode(403)
            ->setJSON(['status' => 'error', 'messages' => ['App key gate is not configured']]);
    }

    $incomingKey = (string) $request->getHeaderLine('X-App-Key');
    if ($incomingKey === '' || ! hash_equals($configuredKey, $incomingKey)) {
        return \Config\Services::response()->setStatusCode(401)
            ->setJSON(['status' => 'error', 'messages' => ['Unauthorized']]);
    }

    return null;
}
```

**Why:** A gate that fails open on missing config is worse than no gate at all, because
it looks protected in code review and CI, and only reveals the gap in production when
the env var is accidentally dropped (a bad deploy, a `.env` that didn't carry over, a
new environment bootstrapped without it). Fail-closed makes the failure loud (403 in
the worst case, boot-time crash in the best case) instead of silent. If you implement
this pattern, apply the corrected behavior above from day one — the bug in
`ci4-website-builder-domain`'s `WebAppKeyRequiredFilter.php` is not something to
reproduce and fix later.

### 4. HTMLPurifier wrapper for rich-text sanitization

**What:** A thin, singleton wrapper around HTMLPurifier with a strict element/attribute
allowlist (no `script`/`style`/`form`/`input`), forced `rel="noopener noreferrer"` +
`target="_blank"` handling on links, and a restricted URI scheme list
(`http`/`https`/`mailto` only) — called in the **write path** before persisting any
user-supplied HTML.

**When it applies:** If a future domain app built from this template accepts free-form
HTML from any field (a rich-text editor, a CMS block, a WYSIWYG content area) — sanitize
on write, not just on output.

**Shape:**

```php
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(string $html): string
    {
        return self::getPurifier()->purify($html);
    }

    private static function getPurifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', WRITEPATH . 'htmlpurifier');
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'small',
            'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'h2', 'h3', 'h4',
            'a[href|title|target|rel]', 'img[src|alt|width|height]', 'hr',
        ]));
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('HTML.TargetNoopener', true);

        return self::$purifier = new HTMLPurifier($config);
    }
}
```

**Why:** Output escaping alone (e.g. `esc()` at render time) protects the page that
renders the content, but not every consumer of that data — an API response, an export,
a different template that renders the field with `raw`/`noEscape` by mistake. Sanitizing
in the write path means the stored value is safe regardless of how or where it's later
rendered, and a strict allowlist (no `script`/`style`/`form`/`input`, restricted URI
schemes, forced `noopener noreferrer` on links) closes the standard stored-XSS and
tabnabbing vectors for free-form HTML. Reference implementation:
`app/Libraries/Cms/HtmlSanitizer.php` in the child project
(`ci4-website-builder-domain`) — not needed today (no rich-text fields in this
template), so this is guidance to apply when that changes, not a file to add now.

## Where to read next

- `../ci4-api-starter/CLAUDE.md` — the hub's API patterns + service-token / introspect contracts.
- `vendor/dcardenasl/ci4-api-core/docs/ARCHITECTURE_CONTRACT.md` — DTO-first patterns enforced by the scaffolding engine (or `../ci4-api-core/docs/ARCHITECTURE_CONTRACT.md` while the path repo is symlinked).
