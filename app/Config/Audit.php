<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Audit extends BaseConfig
{
    /**
     * Enables asynchronous audit logging for non-critical events.
     */
    public bool $asyncEnabled = true;

    /**
     * Queue name for async audit jobs.
     */
    public string $queueName = 'audit';

    /**
     * Actions that should be treated as critical and persisted synchronously.
     *
     * @var array<int, string>
     */
    public array $criticalActions = [
        'authorization_denied_role',
        'api_key_auth_failed',
        'api_key_rate_limit_exceeded',
        'revoked_token_reuse_detected',
    ];

    /**
     * Maximum payload size (bytes) allowed in queue jobs for audit logs.
     */
    public int $maxPayloadBytes = 60000;

    public function __construct()
    {
        parent::__construct();

        $this->asyncEnabled = ENVIRONMENT !== 'testing' && (bool) env('AUDIT_ASYNC_ENABLED', true);
        $this->queueName = (string) env('AUDIT_QUEUE_NAME', 'audit');
        $this->maxPayloadBytes = (int) env('AUDIT_MAX_PAYLOAD_BYTES', 60000);
    }
}
