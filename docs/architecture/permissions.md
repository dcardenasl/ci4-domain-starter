# Permissions Architecture and RBAC Authentication Flow

This document details the security structure and behavior in the `ci4-platform`-based ecosystem.

## Cross-App Permission Flow

The security architecture separates the **admin interface** (Admin/BFF) from the **domain service** (Domain App). This introduces a fundamental distinction in how permissions are registered:

1. **`self` application (ID 1 — Hub/Admin UI)**:
   - Controls whether the logged-in user is authorized to view or navigate the various admin modules (e.g. rendering the "Email Templates" link in the sidebar).
   - UI guards use `has_permission('my.permission')`, which resolves against this application (ID 1).

2. **Domain application (ID > 1 — e.g. `newsletter` / `catalog`)**:
   - Protects the actual API that the BFF or Admin calls.
   - The `DomainAuthFilter` intercepts the JWT and introspects it against the Hub, requesting the permissions specific to that domain application (e.g. `newsletter`).

> [!IMPORTANT]
> For a domain module to work end-to-end, its permissions (e.g. `newsletter.emailtemplates.read`) **must be registered under both applications** (ID 1 for the Admin UI and the domain's own ID for the API) and linked to the appropriate user roles.

---

## Automatic Dev Sync (DX)

The `domain:sync-permissions` console command handles this complexity locally:

```bash
php spark domain:sync-permissions --mirror-to-self --assign-to-role=superadmin
```

### Command Behaviour

1. **Domain registration** — registers all permissions defined in `Config\DomainPermissions::PERMISSIONS` for its own application using its `hub.apiKey` via `POST /api/v1/iam/self-permissions`.
2. **Mirror to `self`** — registers the same permissions under the `self` application (ID 1) in the Hub so they are available in the Admin UI.
3. **Auto-mint token (development only)** — in `development` environment, the command locates the Hub's `.env` file in sibling directories, reads its database credentials and `JWT_SECRET_KEY`, and **autonomously generates a temporary superadmin JWT** to complete the role-linking step without requiring manual token capture.
4. **Cache flush** — after a successful sync, the command automatically clears caches in the domain, the Hub, and the Admin project if their directories are detected.

---

## Introspection Cache Management

To optimize performance, the Hub caches permission resolution per user under the key `iam_eff_perms_{userId}_{appId}` for **60 seconds**.

### Development implications

- If you register a new permission via the sync command, you may not see it reflected immediately in the browser.
- **Solution**: the sync command flushes this cache automatically in development. If you change roles or permissions manually, force a flush by running `php spark cache:clear` inside the Hub project.
