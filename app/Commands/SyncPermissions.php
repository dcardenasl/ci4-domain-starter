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
    protected $usage       = 'domain:sync-permissions [--admin-token=<jwt>]';

    /** @var array<string, string> */
    protected $options = [
        '--admin-token' => 'Superadmin JWT for the hub. Falls back to env hub.adminToken.',
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

        $hub        = Services::hubClient();
        $registered = 0;
        $existed    = 0;
        $errors     = 0;

        foreach (DomainPermissions::PERMISSIONS as $permission) {
            try {
                $created = $hub->registerPermission($permission, $token);
                if ($created) {
                    $registered++;
                    CLI::write(sprintf('[+] %s', $permission['code']), 'green');
                } else {
                    $existed++;
                    CLI::write(sprintf('[=] %s (already registered)', $permission['code']), 'yellow');
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
