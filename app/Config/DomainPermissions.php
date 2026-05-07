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
 * to register them in the hub. The command is idempotent — pre-existing codes
 * are left untouched.
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
        ['code' => 'items.read',   'resource' => 'items', 'action' => 'read',   'description' => 'Read items'],
        ['code' => 'items.write',  'resource' => 'items', 'action' => 'write',  'description' => 'Create or update items'],
        ['code' => 'items.delete', 'resource' => 'items', 'action' => 'delete', 'description' => 'Delete items'],
    ];
}
