<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Hub configuration — coordinates with the central ci4-api-starter ("hub").
 *
 * The hub owns auth, IAM, users, files. Each domain app delegates JWT validation
 * to the hub via POST /api/v1/auth/introspect and obtains its own service token
 * via POST /api/v1/auth/service-token.
 */
class Hub extends BaseConfig
{
    /**
     * Base URL of the hub (no trailing slash). e.g. http://localhost:8080
     */
    public string $url = '';

    /**
     * App-key used in the X-App-Key header for hub calls. Created from the hub
     * with `php spark apps:bootstrap <code>` (which also creates the API Key
     * bound to the application).
     */
    public string $apiKey = '';

    /**
     * Domain app code as registered in the hub (matches the application code).
     */
    public string $appCode = '';

    /**
     * Cache TTL (seconds) for /auth/introspect responses keyed by JTI.
     * Lower = fresher revocation; higher = less load on the hub.
     */
    public int $introspectCacheTtl = 60;

    /**
     * Refresh the cached service token this many seconds before its expiry.
     */
    public int $serviceTokenSafetyMargin = 30;

    /**
     * Hard timeout (seconds) for HTTP calls to the hub.
     */
    public int $httpTimeout = 5;

    /**
     * Optional superadmin JWT for setup-only operations (currently
     * `php spark domain:sync-permissions`). Empty by default. The CLI flag
     * `--admin-token=<jwt>` takes precedence over this value.
     *
     * Permission registration hits `/api/v1/iam/permissions`, which the hub
     * gates on `iam.superadmin-access` — service tokens cannot pass that
     * filter, so a human-issued superadmin JWT is required for setup.
     */
    public string $adminToken = '';

    public function __construct()
    {
        parent::__construct();
        $this->url        = (string) (env('hub.url') ?: $this->url);
        $this->apiKey     = (string) (env('hub.apiKey') ?: $this->apiKey);
        $this->appCode    = (string) (env('hub.appCode') ?: $this->appCode);
        $this->adminToken = (string) (env('hub.adminToken') ?: $this->adminToken);

        $ttl = env('hub.introspectCacheTtl');
        if ($ttl !== null && $ttl !== false && $ttl !== '') {
            $this->introspectCacheTtl = (int) $ttl;
        }
    }
}
