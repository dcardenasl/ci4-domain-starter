<?php

declare(strict_types=1);

namespace Config;

/**
 * Source of truth for the permissions exposed by this domain app.
 *
 * Add an entry here, then run:
 *
 *     php spark domain:sync-permissions
 *
 * to register them in the hub and attach them to superadmin. The command is
 * idempotent — pre-existing codes are left untouched.
 *
 * Permission codes use `.` as separator (NOT `:`) because CodeIgniter splits
 * filter arguments on `:` (`permission:foo:bar` would be parsed as filter=foo,
 * arg=[bar], silently dropping the rest).
 */
class DomainPermissions
{
    /**
     * @var list<array{code: string, resource: string, action: string, description?: string}>
     */
    public const PERMISSIONS = [
        // Item module (Example domain) — granular permissions compatible with v1.1.0
        ['code' => 'items.read', 'resource' => 'items', 'action' => 'read', 'description' => 'Read items'],
        ['code' => 'items.create', 'resource' => 'items', 'action' => 'create', 'description' => 'Create item'],
        ['code' => 'items.update', 'resource' => 'items', 'action' => 'update', 'description' => 'Update item'],
        ['code' => 'items.delete', 'resource' => 'items', 'action' => 'delete', 'description' => 'Delete item'],
    ];
}
