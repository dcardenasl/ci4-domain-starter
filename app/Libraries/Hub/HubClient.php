<?php

declare(strict_types=1);

namespace App\Libraries\Hub;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\Hub as HubConfig;

/**
 * HTTP client for the central hub (ci4-api-starter).
 *
 * Responsibilities:
 *  - Validate user JWTs via POST /api/v1/auth/introspect (cached by token hash)
 *  - Obtain a service token via POST /api/v1/auth/service-token (cached until exp)
 *  - Register this domain app's permissions via POST /api/v1/iam/permissions
 *
 * The service token is reused across requests until it nears expiry (controlled
 * by `Hub::serviceTokenSafetyMargin`). Introspection results are cached by token
 * hash for `Hub::introspectCacheTtl` seconds.
 */
class HubClient
{
    private const SERVICE_TOKEN_CACHE_KEY = 'hub_service_token';
    private const INTROSPECT_CACHE_PREFIX = 'hub_introspect_';

    public function __construct(
        private readonly HubConfig $config,
        private readonly CURLRequest $http,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Validate a JWT against the hub. Cached by SHA-256(token).
     */
    public function introspect(string $token): IntrospectResult
    {
        if ($token === '') {
            return IntrospectResult::invalid();
        }

        $cacheKey = self::INTROSPECT_CACHE_PREFIX . hash('sha256', $token);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return IntrospectResult::fromArray($cached);
        }

        try {
            $response = $this->http->post($this->endpoint('/api/v1/auth/introspect'), [
                'headers' => $this->appKeyHeaders(),
                'json'    => ['token' => $token],
                'timeout' => $this->config->httpTimeout,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[HubClient] introspect failed: ' . $e->getMessage());
            return IntrospectResult::invalid('hub_unreachable');
        }

        if ($response->getStatusCode() !== 200) {
            return IntrospectResult::invalid('hub_unreachable');
        }

        $payload = $this->decodeData((string) $response->getBody());
        $result  = IntrospectResult::fromArray($payload);

        // Cache only positive results — invalid tokens shouldn't poison the cache.
        if ($result->valid) {
            $this->cache->save($cacheKey, $payload, $this->config->introspectCacheTtl);
        }

        return $result;
    }

    /**
     * Return a valid service token for this domain app, refreshing if near expiry.
     */
    public function getServiceToken(): string
    {
        /** @var array{access_token: string, expires_at: int}|null $cached */
        $cached = $this->cache->get(self::SERVICE_TOKEN_CACHE_KEY);
        if (is_array($cached) && isset($cached['access_token'], $cached['expires_at'])) {
            $remaining = $cached['expires_at'] - time();
            if ($remaining > $this->config->serviceTokenSafetyMargin) {
                return (string) $cached['access_token'];
            }
        }

        try {
            $response = $this->http->post($this->endpoint('/api/v1/auth/service-token'), [
                'headers' => $this->appKeyHeaders(),
                'timeout' => $this->config->httpTimeout,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Hub unreachable during service-token request: ' . $e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'Hub returned %d for /auth/service-token: %s',
                $response->getStatusCode(),
                substr((string) $response->getBody(), 0, 200)
            ));
        }

        $payload = $this->decodeData((string) $response->getBody());
        $token   = (string) ($payload['access_token'] ?? '');
        $ttl     = (int)    ($payload['expires_in']   ?? 0);

        if ($token === '' || $ttl <= 0) {
            throw new \RuntimeException('Hub returned malformed service-token payload.');
        }

        $expiresAt = time() + $ttl;
        $this->cache->save(self::SERVICE_TOKEN_CACHE_KEY, [
            'access_token' => $token,
            'expires_at'   => $expiresAt,
        ], max(60, $ttl - $this->config->serviceTokenSafetyMargin));

        return $token;
    }

    /**
     * Register a single permission in the hub. Idempotent: returns false if the
     * permission already existed (HTTP 409 / 422-on-duplicate), true on create.
     *
     * Requires a superadmin JWT — the hub gates `/api/v1/iam/permissions` on
     * `iam.superadmin-access`, which service tokens cannot satisfy. Pass the
     * token explicitly; callers obtain it out-of-band (CLI flag / env var).
     *
     * @param array{code: string, resource: string, action: string, description?: string} $permission
     */
    public function registerPermission(array $permission, string $bearerToken): bool
    {
        $body = [
            'code'           => $permission['code'],
            'resource'       => $permission['resource'],
            'action'         => $permission['action'],
            'description'    => $permission['description'] ?? null,
            'application_id' => null,
        ];

        try {
            $response = $this->http->post($this->endpoint('/api/v1/iam/permissions'), [
                'headers' => array_merge($this->appKeyHeaders(), [
                    'Authorization' => 'Bearer ' . $bearerToken,
                ]),
                'json'    => $body,
                'timeout' => $this->config->httpTimeout,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Hub unreachable during register-permission: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status === 201 || $status === 200) {
            return true;
        }

        if ($status === 409 || $status === 422) {
            // Treat as already-registered (idempotent).
            return false;
        }

        if ($status === 401 || $status === 403) {
            throw new \RuntimeException(sprintf(
                'Hub rejected admin token: HTTP %d — token missing iam.superadmin-access (or expired/invalid).',
                $status
            ));
        }

        throw new \RuntimeException(sprintf(
            'Hub returned %d for register-permission %s: %s',
            $status,
            $permission['code'],
            substr((string) $response->getBody(), 0, 200)
        ));
    }

    /**
     * @return array<string, string>
     */
    private function appKeyHeaders(): array
    {
        return [
            'X-App-Key' => $this->config->apiKey,
            'Accept'    => 'application/json',
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->config->url, '/') . $path;
    }

    /**
     * Decode an envelope of shape `{success, data, ...}` and return data, or
     * the raw body if it isn't wrapped.
     *
     * @return array<string, mixed>
     */
    private function decodeData(string $body): array
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return [];
        }

        if (array_key_exists('data', $decoded) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        return $decoded;
    }
}
