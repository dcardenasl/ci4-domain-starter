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
        if (is_string($roleArg) && $roleArg !== '') {
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

        // Automatic cache clearing for development environments (DX improvement)
        if (ENVIRONMENT === 'development') {
            $this->writeLine('Clearing local caches...', 'cyan');
            @exec('php spark cache:clear');

            $envPath = $this->findHubEnvPath();
            if ($envPath) {
                $hubDir = dirname($envPath);
                if (is_file($hubDir . '/spark')) {
                    @exec('php ' . escapeshellarg($hubDir . '/spark') . ' cache:clear');
                    $this->writeLine('  Hub cache cleared.', 'green');
                }

                $siblings = [
                    $hubDir . '/../ci4-multi-subscription-admin',
                    $hubDir . '/../ci4-admin-starter',
                ];
                foreach ($siblings as $sib) {
                    $sib = realpath($sib);
                    if ($sib && is_file($sib . '/spark')) {
                        @exec('php ' . escapeshellarg($sib . '/spark') . ' cache:clear');
                        $this->writeLine('  Admin cache cleared.', 'green');
                    }
                }
            }
        }

        return ($errors === 0 && $mirrorErrors === 0 && !$roleLinkFailed) ? 0 : 1;
    }

    protected function resolveAdminToken(): string
    {
        $flag = CLI::getOption('admin-token');
        if (is_string($flag) && $flag !== '') {
            return $flag;
        }

        // Try local development auto-generation first
        $localToken = $this->autoMintLocalToken();
        if ($localToken !== null) {
            $this->writeLine('Auto-generated temporary Superadmin token from local Hub database.', 'green');
            return $localToken;
        }

        /** @var HubConfig $hubConfig */
        $hubConfig = config(HubConfig::class);

        return $hubConfig->adminToken ?? '';
    }

    private function findHubEnvPath(): ?string
    {
        $searchPaths = [
            __DIR__ . '/../../../../ci4-multi-subscription-api/.env',
            __DIR__ . '/../../../../ci4-api-starter/.env',
            __DIR__ . '/../../../ci4-multi-subscription-api/.env',
            __DIR__ . '/../../../ci4-api-starter/.env',
            __DIR__ . '/../../ci4-multi-subscription-api/.env',
            __DIR__ . '/../../ci4-api-starter/.env',
        ];

        foreach ($searchPaths as $path) {
            $realPath = realpath($path);
            if ($realPath && is_file($realPath)) {
                $content = file_get_contents($realPath);
                if (str_contains($content, 'JWT_SECRET_KEY') && str_contains($content, 'database.default.database')) {
                    return $realPath;
                }
            }
        }

        return null;
    }

    private function autoMintLocalToken(): ?string
    {
        if (ENVIRONMENT !== 'development') {
            return null;
        }

        $envPath = $this->findHubEnvPath();
        if (!$envPath) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $hubEnv = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $hubEnv[trim($key)] = trim(trim($val), '"\'');
            }
        }

        $secret = $hubEnv['JWT_SECRET_KEY'] ?? null;
        if (!$secret) {
            return null;
        }

        $dbConfig = [
            'hostname' => $hubEnv['database.default.hostname'] ?? '127.0.0.1',
            'username' => $hubEnv['database.default.username'] ?? 'root',
            'password' => $hubEnv['database.default.password'] ?? 'root',
            'database' => $hubEnv['database.default.database'] ?? 'ci4_multi_subscription_hub',
            'DBDriver' => 'MySQLi',
            'port'     => (int) ($hubEnv['database.default.port'] ?? 3306),
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_general_ci',
        ];

        try {
            $db = \Config\Database::connect($dbConfig, false);

            $row = $db->table('user_roles ur')
                ->select('ur.user_id')
                ->join('roles r', 'r.id = ur.role_id')
                ->where('r.code', 'superadmin')
                ->limit(1)
                ->get()?->getRowArray();

            if ($row === null) {
                return null;
            }

            $userId = (int) $row['user_id'];

            $permsQuery = $db->table('user_roles ur')
                ->select('p.code')
                ->distinct()
                ->join('role_permissions rp', 'rp.role_id = ur.role_id')
                ->join('permissions p', 'p.id = rp.permission_id')
                ->where('ur.user_id', $userId)
                ->where('p.application_id', 1)
                ->get();

            $permissions = [];
            if ($permsQuery !== false) {
                foreach ($permsQuery->getResultArray() as $pRow) {
                    $permissions[] = (string) $pRow['code'];
                }
            }

            if (!in_array('iam.superadmin-access', $permissions, true)) {
                $permissions[] = 'iam.superadmin-access';
            }

            return $this->mintToken($secret, $userId, $permissions);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function mintToken(string $secret, int $userId, array $permissions): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'iss' => 'http://localhost:8080',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 3600,
            'jti' => bin2hex(random_bytes(16)),
            'uid' => $userId,
            'scope' => $permissions
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
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
