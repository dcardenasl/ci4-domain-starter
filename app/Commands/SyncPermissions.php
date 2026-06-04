<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\DomainPermissions;
use Config\Hub as HubConfig;
use Config\Services;

/**
 * php spark domain:sync-permissions [--admin-token=<jwt>] [--assign-to-role=<ID|code>] [--mirror-to-self]
 *
 * Registers every permission listed in DomainPermissions::PERMISSIONS in the
 * hub's IAM using the domain's own X-App-Key (POST /api/v1/iam/self-permissions).
 * No superadmin JWT required for the primary registration.
 *
 * --admin-token is only required when:
 *   - --mirror-to-self is set (registers under hub app self, ID=1, for admin UI access)
 *   - --assign-to-role is set (links permissions to a role)
 */
class SyncPermissions extends BaseCommand
{
    protected $group       = 'Domain';
    protected $name        = 'domain:sync-permissions';
    protected $description = 'Register this domain app\'s permissions in the hub via its own API key (idempotent).';
    protected $usage       = 'domain:sync-permissions [--admin-token=<jwt>] [--assign-to-role=<ID|code>] [--mirror-to-self]';

    /** @var array<string, string> */
    protected $options = [
        '--admin-token'    => 'Superadmin JWT. Required only for --mirror-to-self or --assign-to-role.',
        '--assign-to-role' => 'Automatically link new permissions to this role ID or code (e.g. superadmin).',
        '--mirror-to-self' => 'Also register the same permissions under hub app self (ID=1) for admin UI access.',
    ];

    private const SELF_APPLICATION_ID = 1;

    public function run(array $params): int
    {
        $mirrorToSelf = $this->shouldMirrorToSelf();
        $roleArg      = CLI::getOption('assign-to-role');
        $roleArg      = is_string($roleArg) && $roleArg !== '' ? $roleArg : null;

        $needsToken = $mirrorToSelf || $roleArg !== null;
        $token      = $this->resolveAdminToken();

        if ($needsToken && $token === '') {
            $this->writeError('--admin-token is required when using --mirror-to-self or --assign-to-role.');
            $this->writeLine('Pass --admin-token=<jwt> or set hub.adminToken in .env.', 'yellow');
            $this->writeLine('Obtain one via: POST {hub.url}/api/v1/auth/login', 'cyan');

            return 1;
        }

        return $this->syncPermissions($mirrorToSelf, $roleArg, $token);
    }

    /**
     * @return int EXIT_SUCCESS|EXIT_ERROR
     */
    public function syncPermissions(bool $mirrorToSelf, ?string $roleArg = null, string $token = ''): int
    {
        $hub            = Services::hubClient();
        $permissions    = DomainPermissions::PERMISSIONS;
        $mirrorErrors   = 0;

        // Primary registration: domain registers its own permissions via X-App-Key.
        // The hub assigns application_id from the key — no superadmin JWT needed.
        $this->writeLine(sprintf('Syncing %d permission(s) via self-permissions endpoint...', count($permissions)), 'cyan');

        try {
            $result         = $hub->registerSelfPermissions($permissions);
            $registered     = (int) ($result['created'] ?? 0);
            $existed        = (int) ($result['existing'] ?? 0);
            $errors         = (int) ($result['rejected'] ?? 0);
            $processedCodes = array_column($permissions, 'code');
        } catch (\Throwable $e) {
            $this->writeError(sprintf('Self-permissions sync failed: %s', $e->getMessage()));

            return 1;
        }

        if ($mirrorToSelf) {
            $this->newLine();
            $this->writeLine(sprintf('Mirroring permissions to hub app self (ID %d)...', self::SELF_APPLICATION_ID), 'cyan');

            foreach ($permissions as $permission) {
                try {
                    $created = $hub->registerPermission($permission, $token, self::SELF_APPLICATION_ID);
                    if ($created) {
                        $this->writeLine(sprintf('[+] %s (self)', $permission['code']), 'green');
                    } else {
                        $this->writeLine(sprintf('[=] %s (self already registered)', $permission['code']), 'yellow');
                    }
                } catch (\Throwable $e) {
                    $mirrorErrors++;
                    $this->writeError(sprintf('[!] %s (self) — %s', $permission['code'], $e->getMessage()));
                }
            }
        }

        // Automatic assignment to role
        $roleLinkFailed = false;
        if (is_string($roleArg) && $roleArg !== '' && !empty($processedCodes)) {
            $this->newLine();
            $this->writeLine(sprintf('Linking permissions to role: %s', $roleArg), 'cyan');

            try {
                $roleId = is_numeric($roleArg) ? (int) $roleArg : null;
                if ($roleId === null) {
                    $role = $hub->findRoleByCode($roleArg, $token);
                    if ($role === null) {
                        $this->writeError(sprintf('Role linking failed: %s not found — nothing attached.', $roleArg));
                        $roleLinkFailed = true;
                    } else {
                        $roleId = (int) $role['id'];
                    }
                }

                if ($roleId !== null) {
                    $hub->attachPermissionsToRole($roleId, $processedCodes, $token);
                    $this->writeLine(sprintf('Successfully linked %d permissions to role ID %d.', count($processedCodes), $roleId), 'green');
                }
            } catch (\Throwable $e) {
                $this->writeError(sprintf('Role linking failed: %s', $e->getMessage()));
                $roleLinkFailed = true;
            }
        }

        $this->newLine();
        if ($mirrorToSelf) {
            $this->writeLine(sprintf(
                'Self mirror: errors %d.',
                $mirrorErrors
            ), $mirrorErrors === 0 ? 'green' : 'yellow');
        }
        $this->writeLine(sprintf(
            'Done. Registered: %d, existed: %d, rejected: %d.',
            $registered,
            $existed,
            $errors
        ), ($errors === 0 && $mirrorErrors === 0) ? 'green' : 'yellow');

        return ($errors === 0 && $mirrorErrors === 0 && !$roleLinkFailed) ? 0 : 1;
    }

    protected function resolveAdminToken(): string
    {
        $flag = CLI::getOption('admin-token');
        if (is_string($flag) && $flag !== '') {
            return $flag;
        }

        /** @var HubConfig $hubConfig */
        $hubConfig = config(HubConfig::class);

        return $hubConfig->adminToken;
    }

    protected function shouldMirrorToSelf(): bool
    {
        return CLI::getOption('mirror-to-self') !== null;
    }

    protected function writeLine(string $message, string $color = 'white'): void
    {
        CLI::write($message, $color);
    }

    protected function writeError(string $message): void
    {
        CLI::error($message);
    }

    protected function newLine(int $repeat = 1): void
    {
        CLI::newLine($repeat);
    }
}
