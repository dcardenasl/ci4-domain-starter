<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Hub\HubClient;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

class Doctor extends BaseCommand
{
    protected $group       = 'Domain';
    protected $name        = 'domain:doctor';
    protected $description = 'Run a diagnostic against the hub auth and IAM flows used by this domain.';
    protected $usage       = 'domain:doctor [--token=<jwt>] [--admin-token=<jwt>]';
    protected $options     = [
        '--token'       => 'User JWT to probe hub introspection. If omitted, introspect is skipped.',
        '--admin-token' => 'Superadmin JWT to probe permission registration. If omitted, the check is skipped.',
    ];

    public function run(array $params)
    {
        $token = $this->resolveOption($params, 'token');
        $adminToken = $this->resolveOption($params, 'admin-token');

        $report = $this->diagnose($token, $adminToken);

        CLI::write('');
        CLI::write('Domain doctor', 'yellow');
        CLI::write('Checking hub connectivity and auth flows...', 'cyan');
        CLI::write('');

        $hasErrors = false;
        foreach ($report['checks'] as $check) {
            $color = match ($check['status']) {
                'ok' => 'green',
                'skipped' => 'yellow',
                default => 'red',
            };

            if ($check['status'] === 'fail') {
                $hasErrors = true;
            }

            CLI::write(sprintf(
                '  [%s] %-20s %s',
                $this->statusBadge($check['status']),
                $check['label'],
                $check['detail'],
            ), $color);
        }

        CLI::write('');

        if ($hasErrors) {
            CLI::error('One or more doctor checks failed.');
            return EXIT_ERROR;
        }

        CLI::write('All requested doctor checks passed.', 'green');

        return EXIT_SUCCESS;
    }

    /**
     * @return array{checks: list<array{label: string, status: 'ok'|'fail'|'skipped', detail: string}>, hasErrors: bool}
     */
    public function diagnose(string $token, string $adminToken): array
    {
        $hub = Services::hubClient();

        $checks = [];
        $checks[] = $this->checkServiceToken($hub);
        $checks[] = $this->checkIntrospect($hub, $token);
        $checks[] = $this->checkRegisterPermission($hub, $adminToken);

        return [
            'checks' => $checks,
            'hasErrors' => in_array('fail', array_column($checks, 'status'), true),
        ];
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            'ok' => 'OK',
            'skipped' => 'SKIP',
            default => 'FAIL',
        };
    }

    /**
     * @return array{label: string, status: 'ok'|'fail'|'skipped', detail: string}
     */
    private function checkServiceToken(HubClient $hub): array
    {
        try {
            $token = $hub->getServiceToken();

            if ($token === '') {
                return [
                    'label' => 'service-token',
                    'status' => 'fail',
                    'detail' => 'hub returned an empty service token',
                ];
            }

            return [
                'label' => 'service-token',
                'status' => 'ok',
                'detail' => sprintf('acquired (%d chars)', strlen($token)),
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'service-token',
                'status' => 'fail',
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{label: string, status: 'ok'|'fail'|'skipped', detail: string}
     */
    private function checkIntrospect(HubClient $hub, string $token): array
    {
        if ($token === '') {
            return [
                'label' => 'introspect',
                'status' => 'skipped',
                'detail' => 'pass --token=<jwt> to probe hub introspection',
            ];
        }

        try {
            $result = $hub->introspect($token);

            if (! $result->valid) {
                return [
                    'label' => 'introspect',
                    'status' => 'fail',
                    'detail' => 'token was rejected by the hub',
                ];
            }

            $permissionCount = count($result->permissions);

            return [
                'label' => 'introspect',
                'status' => 'ok',
                'detail' => sprintf('valid for user %s (%d permissions)', (string) $result->uid, $permissionCount),
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'introspect',
                'status' => 'fail',
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * Probes the permission-registration path with an intentionally invalid
     * payload so the check stays read-only. A non-exception response still
     * proves the hub accepted the request and returned a structured outcome.
     *
     * @return array{label: string, status: 'ok'|'fail'|'skipped', detail: string}
     */
    private function checkRegisterPermission(HubClient $hub, string $adminToken): array
    {
        if ($adminToken === '') {
            return [
                'label' => 'register-permission',
                'status' => 'skipped',
                'detail' => 'pass --admin-token=<jwt> to probe permission registration',
            ];
        }

        try {
            $hub->registerPermission([
                'code'        => '',
                'resource'    => '',
                'action'      => '',
                'description' => 'doctor probe',
            ], $adminToken);

            return [
                'label' => 'register-permission',
                'status' => 'ok',
                'detail' => 'hub returned a structured response',
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'register-permission',
                'status' => 'fail',
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function resolveOption(array $params, string $name): string
    {
        $prefix = '--' . $name . '=';

        foreach ($params as $param) {
            if (is_string($param) && str_starts_with($param, $prefix)) {
                return substr($param, strlen($prefix));
            }
        }

        return '';
    }
}
