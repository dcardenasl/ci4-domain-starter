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
     * Base URL of the hub (no trailing slash). e.g. http://localhost:8180
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
     * Lower = fresher revocation; higher = less load on the hub. Default 30s
     * bounds the window in which a revoked token is still accepted; raise via
     * hub.introspectCacheTtl if hub load matters more than revocation latency.
     */
    public int $introspectCacheTtl = 30;

    /**
     * Refresh the cached service token this many seconds before its expiry.
     */
    public int $serviceTokenSafetyMargin = 30;

    /**
     * Hard timeout (seconds) for HTTP calls to the hub.
     */
    public int $httpTimeout = 5;

    /**
     * Hub endpoint paths. Override here to point at a different hub API version
     * without forking the HubClient.
     */
    public string $introspectPath   = '/api/v1/auth/introspect';
    public string $serviceTokenPath = '/api/v1/auth/service-token';
    public string $permissionsPath  = '/api/v1/iam/permissions';

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

        // Hub URL is required for JWT introspection
        $url = env('hub.url');
        if (! is_string($url) || trim($url) === '') {
            throw new \LogicException(
                'Missing hub.url in .env. '
                . 'This domain app delegates JWT validation to a central hub. '
                . 'Set hub.url to the hub API base URL. '
                . 'Example: hub.url=http://localhost:8180'
            );
        }
        $this->url = $url;

        // API key for hub calls (X-App-Key header)
        $apiKey = env('hub.apiKey');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new \LogicException(
                'Missing hub.apiKey in .env. '
                . 'This is the X-App-Key that identifies this app to the hub. '
                . 'Create it via `php spark apps:bootstrap <code>` on the hub. '
                . 'Example: hub.apiKey=apk_xxxx...'
            );
        }
        $this->apiKey = $apiKey;

        // App code as registered in hub
        $appCode = env('hub.appCode');
        if (! is_string($appCode) || trim($appCode) === '') {
            throw new \LogicException(
                'Missing hub.appCode in .env. '
                . 'This is the application code as registered in the hub. '
                . 'Set it to match the app entry in the hub. '
                . 'Example: hub.appCode=domain-1'
            );
        }
        $this->appCode = $appCode;

        // Optional admin token and cache settings
        $this->adminToken = (string) (env('hub.adminToken') ?: '');

        $ttl = env('hub.introspectCacheTtl');
        if ($ttl !== null && $ttl !== false && $ttl !== '') {
            $this->introspectCacheTtl = (int) $ttl;
        }
    }
}
