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
    protected $usage       = 'domain:sync-permissions [--admin-token=<jwt>] [--assign-to-role=<ID|code>]';

    /** @var array<string, string> */
    protected $options = [
        '--admin-token'    => 'Superadmin JWT for the hub. Falls back to env hub.adminToken.',
        '--assign-to-role' => 'Automatically link new permissions to this role ID or code (e.g. superadmin).',
    ];

    public function run(array $params)
    {
        $token = $this->resolveAdminToken();
        if ($token === '') {
            CLI::error('No admin token provided.');
            CLI::write('Pass --admin-token=<jwt> or set hub.adminToken in .env.', 'yellow');
            CLI::write('Obtain one by logging in to the hub as a superadmin:', 'cyan');
            CLI::write('  POST {hub.url}/api/v1/auth/login', 'cyan');

            return EXIT_ERROR;
        }

        $hub            = Services::hubClient();
        $registered     = 0;
        $existed        = 0;
        $errors         = 0;
        $processedCodes = [];

        foreach (DomainPermissions::PERMISSIONS as $permission) {
            try {
                $created = $hub->registerPermission($permission, $token);
                if ($created) {
                    $registered++;
                    CLI::write(sprintf('[+] %s', $permission['code']), 'green');
                    $processedCodes[] = $permission['code'];
                } else {
                    $existed++;
                    CLI::write(sprintf('[=] %s (already registered)', $permission['code']), 'yellow');
                    $processedCodes[] = $permission['code'];
                }
            } catch (\Throwable $e) {
                $errors++;
                $message = $e->getMessage();
                CLI::error(sprintf('[!] %s — %s', $permission['code'], $message));

                // Short-circuit on auth failure: every subsequent call would hit
                // the same wall, no point spamming the hub.
                if (str_contains($message, 'Hub rejected admin token')) {
                    CLI::newLine();
                    CLI::write('Aborting: token is invalid or lacks iam.superadmin-access.', 'red');

                    return EXIT_ERROR;
                }
            }
        }

        // Automatic assignment to role
        $roleArg = CLI::getOption('assign-to-role');
        if (is_string($roleArg) && $roleArg !== '' && !empty($processedCodes)) {
            CLI::newLine();
            CLI::write(sprintf('Linking permissions to role: %s', $roleArg), 'cyan');

            try {
                $roleId = is_numeric($roleArg) ? (int) $roleArg : null;
                if ($roleId === null) {
                    $role = $hub->findRoleByCode($roleArg, $token);
                    if ($role === null) {
                        CLI::error(sprintf('Role not found by code: %s', $roleArg));
                    } else {
                        $roleId = (int) $role['id'];
                    }
                }

                if ($roleId !== null) {
                    $hub->attachPermissionsToRole($roleId, $processedCodes, $token);
                    CLI::write(sprintf('Successfully linked %d permissions to role ID %d.', count($processedCodes), $roleId), 'green');
                }
            } catch (\Throwable $e) {
                CLI::error(sprintf('Failed to link permissions to role: %s', $e->getMessage()));
            }
        }

        CLI::newLine();
        CLI::write(sprintf(
            'Done. Registered: %d, existed: %d, errors: %d.',
            $registered,
            $existed,
            $errors
        ), $errors === 0 ? 'green' : 'yellow');

        return $errors === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function resolveAdminToken(): string
    {
        $flag = CLI::getOption('admin-token');
        if (is_string($flag) && $flag !== '') {
            return $flag;
        }

        /** @var HubConfig $hubConfig */
        $hubConfig = config(HubConfig::class);

        return $hubConfig->adminToken;
    }
}
