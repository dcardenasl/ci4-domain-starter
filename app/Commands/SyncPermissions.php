<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\DomainPermissions;
use Config\Hub as HubConfig;
use Config\Services;

/**
 * php spark domain:sync-permissions --admin-token=<jwt>
 *
 * Registers every permission listed in DomainPermissions::PERMISSIONS in the
 * hub's IAM. Idempotent — already-registered codes are skipped without error.
 *
 * Setup-only operation: the hub gates `/api/v1/iam/permissions` on
 * `iam.superadmin-access`, which service tokens cannot satisfy. The operator
 * must supply a superadmin JWT obtained out-of-band, either via the
 * `--admin-token` flag or the `hub.adminToken` env var.
 */
class SyncPermissions extends BaseCommand
{
    protected $group       = 'Domain';
    protected $name        = 'domain:sync-permissions';
    protected $description = 'Register this domain app\'s permissions in the hub (idempotent). Requires a superadmin JWT.';
    protected $usage       = 'domain:sync-permissions [--admin-token=<jwt>] [--assign-to-role=<ID|code>] [--mirror-to-self]';

    /** @var array<string, string> */
    protected $options = [
        '--admin-token'    => 'Superadmin JWT for the hub. Falls back to env hub.adminToken.',
        '--assign-to-role' => 'Automatically link new permissions to this role ID or code (e.g. superadmin).',
        '--mirror-to-self' => 'Also register the same permissions under hub app self (ID=1) for admin UI access.',
    ];

    private const SELF_APPLICATION_ID = 1;

    public function run(array $params)
    {
        $token = $this->resolveAdminToken();
        if ($token === '') {
            $this->writeError('No admin token provided.');
            $this->writeLine('Pass --admin-token=<jwt> or set hub.adminToken in .env.', 'yellow');
            $this->writeLine('Obtain one by logging in to the hub as a superadmin:', 'cyan');
            $this->writeLine('  POST {hub.url}/api/v1/auth/login', 'cyan');

            return 1;
        }

        $roleArg = CLI::getOption('assign-to-role');
        $roleArg = is_string($roleArg) && $roleArg !== '' ? $roleArg : null;

        return $this->syncPermissions($token, $this->shouldMirrorToSelf(), $roleArg);
    }

    /**
     * @return int EXIT_SUCCESS|EXIT_ERROR
     */
    public function syncPermissions(string $token, bool $mirrorToSelf, ?string $roleArg = null): int
    {
        $hub              = Services::hubClient();
        $registered       = 0;
        $existed          = 0;
        $errors           = 0;
        $processedCodes   = [];
        $mirrorRegistered = 0;
        $mirrorExisted    = 0;
        $mirrorErrors     = 0;

        foreach (DomainPermissions::PERMISSIONS as $permission) {
            try {
                $created = $hub->registerPermission($permission, $token);
                if ($created) {
                    $registered++;
                    $this->writeLine(sprintf('[+] %s', $permission['code']), 'green');
                    $processedCodes[] = $permission['code'];
                } else {
                    $existed++;
                    $this->writeLine(sprintf('[=] %s (already registered)', $permission['code']), 'yellow');
                    $processedCodes[] = $permission['code'];
                }
            } catch (\Throwable $e) {
                $errors++;
                $message = $e->getMessage();
                $this->writeError(sprintf('[!] %s — %s', $permission['code'], $message));

                // Short-circuit on auth failure: every subsequent call would hit
                // the same wall, no point spamming the hub.
                if (str_contains($message, 'Hub rejected admin token')) {
                    $this->newLine();
                    $this->writeLine('Aborting: token is invalid or lacks iam.superadmin-access.', 'red');

                    return 1;
                }
            }
        }

        if ($mirrorToSelf) {
            $this->newLine();
            $this->writeLine(sprintf('Mirroring permissions to hub app self (ID %d)', self::SELF_APPLICATION_ID), 'cyan');

            foreach (DomainPermissions::PERMISSIONS as $permission) {
                try {
                    $created = $hub->registerPermission($permission, $token, self::SELF_APPLICATION_ID);
                    if ($created) {
                        $mirrorRegistered++;
                        $this->writeLine(sprintf('[+] %s (self)', $permission['code']), 'green');
                    } else {
                        $mirrorExisted++;
                        $this->writeLine(sprintf('[=] %s (self already registered)', $permission['code']), 'yellow');
                    }
                } catch (\Throwable $e) {
                    $mirrorErrors++;
                    $this->writeError(sprintf('[!] %s (self) — %s', $permission['code'], $e->getMessage()));
                }
            }
        }

        // Automatic assignment to role
        if (is_string($roleArg) && $roleArg !== '' && !empty($processedCodes)) {
            $this->newLine();
            $this->writeLine(sprintf('Linking permissions to role: %s', $roleArg), 'cyan');

            try {
                $roleId = is_numeric($roleArg) ? (int) $roleArg : null;
                if ($roleId === null) {
                    $role = $hub->findRoleByCode($roleArg, $token);
                    if ($role === null) {
                        $this->writeError(sprintf('Role not found by code: %s', $roleArg));
                    } else {
                        $roleId = (int) $role['id'];
                    }
                }

                if ($roleId !== null) {
                    $hub->attachPermissionsToRole($roleId, $processedCodes, $token);
                    $this->writeLine(sprintf('Successfully linked %d permissions to role ID %d.', count($processedCodes), $roleId), 'green');
                }
            } catch (\Throwable $e) {
                $this->writeError(sprintf('Failed to link permissions to role: %s', $e->getMessage()));
            }
        }

        $this->newLine();
        if ($mirrorToSelf) {
            $this->writeLine(sprintf(
                'Self mirror: registered %d, existed %d, errors %d.',
                $mirrorRegistered,
                $mirrorExisted,
                $mirrorErrors
            ), $mirrorErrors === 0 ? 'green' : 'yellow');
        }
        $this->writeLine(sprintf(
            'Done. Registered: %d, existed: %d, errors: %d.',
            $registered,
            $existed,
            $errors
        ), ($errors === 0 && $mirrorErrors === 0) ? 'green' : 'yellow');

        return ($errors === 0 && $mirrorErrors === 0) ? 0 : 1;
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
