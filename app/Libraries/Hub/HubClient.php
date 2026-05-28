<?php

declare(strict_types=1);

namespace App\Libraries\Hub;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\Hub as HubConfig;
use dcardenasl\Ci4ApiCore\Contracts\HubClientInterface;
use dcardenasl\Ci4ApiCore\Exceptions\ApiException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\ConflictException;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Http\Client\AbstractServiceClient;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;

/**
 * HTTP client for the central hub (ci4-api-starter).
 *
 * Responsibilities:
 *  - Validate user JWTs via POST `Config\Hub::$introspectPath` (cached by token hash)
 *  - Obtain a service token via POST `Config\Hub::$serviceTokenPath` (cached until exp)
 *  - Register this domain app's permissions via POST `Config\Hub::$permissionsPath`
 *
 * Inherits retry, timeout, header forwarding and canonical-error mapping from
 * {@see AbstractServiceClient}. Endpoint paths live in `Config\Hub` so a hub
 * API version bump is a one-config change.
 */
class HubClient extends AbstractServiceClient implements HubClientInterface
{
    private const SERVICE_TOKEN_CACHE_KEY = 'hub_service_token';
    private const INTROSPECT_CACHE_PREFIX = 'hub_introspect_';

    public function __construct(
        private readonly HubConfig $config,
        CURLRequest $http,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct(
            http: $http,
            baseUrl: $this->config->url,
            timeoutSeconds: $this->config->httpTimeout,
        );
    }

    /**
     * Validate a JWT against the hub. Cached by SHA-256(token). Any upstream
     * failure (network, 4xx, 5xx) is downgraded to an `invalid` result — the
     * domain app never propagates introspect errors as exceptions; a 401 to
     * the client is the correct user-facing outcome.
     */
    public function introspect(string $token): IntrospectResult
    {
        if ($token === '') {
            return IntrospectResult::invalid();
        }

        $cacheKey = self::INTROSPECT_CACHE_PREFIX . hash('sha256', $token);
        $cached   = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return IntrospectResult::fromArray($cached);
        }

        try {
            $payload = $this->request('POST', $this->config->introspectPath, [
                'headers' => $this->appKeyHeaders(),
                'json'    => ['token' => $token],
            ]);
        } catch (ApiException) {
            return IntrospectResult::invalid('hub_unreachable');
        }

        $result = IntrospectResult::fromArray($payload);

        if ($result->valid) {
            $this->cache->save($cacheKey, $payload, $this->config->introspectCacheTtl);
        }

        return $result;
    }

    /**
     * Return a valid service token for this domain app, refreshing if near expiry.
     *
     * @throws ServiceUnavailableException When the hub is unreachable or returns a malformed payload.
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

        $payload = $this->request('POST', $this->config->serviceTokenPath, [
            'headers' => $this->appKeyHeaders(),
        ]);

        $token = is_string($payload['access_token'] ?? null) ? $payload['access_token'] : '';
        $ttl   = is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : 0;

        if ($token === '' || $ttl <= 0) {
            throw new ServiceUnavailableException('Hub returned malformed service-token payload.');
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
     * permission already existed (HTTP 409 or 422-on-duplicate), true on create.
     *
     * Requires a superadmin JWT — the hub gates `permissionsPath` on
     * `iam.superadmin-access`, which service tokens cannot satisfy. Pass the
     * token explicitly; callers obtain it out-of-band (CLI flag / env var).
     *
     * @param array{code: string, resource: string, action: string, description?: string} $permission
     *
     * @throws AuthenticationException When the admin token is missing or invalid.
     * @throws AuthorizationException When the admin token lacks `iam.superadmin-access`.
     * @throws ServiceUnavailableException When the hub is unreachable or fails unexpectedly.
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
            $this->request('POST', $this->config->permissionsPath, [
                'headers' => array_merge($this->appKeyHeaders(), [
                    'Authorization' => 'Bearer ' . $bearerToken,
                ]),
                'json' => $body,
            ]);

            return true;
        } catch (ConflictException | ValidationException) {
            return false;
        }
    }

    /**
     * Find a role by its unique code in the hub.
     *
     * @return array<string, mixed>|null
     */
    public function findRoleByCode(string $code, string $bearerToken): ?array
    {
        $payload = $this->request('GET', '/api/v1/iam/roles', [
            'headers' => array_merge($this->appKeyHeaders(), [
                'Authorization' => 'Bearer ' . $bearerToken,
            ]),
            'query' => ['filter[code]' => $code, 'per_page' => 1],
        ]);

        $data = $payload['data'] ?? [];

        return $data[0] ?? null;
    }

    /**
     * Attach a list of permissions (by code) to a role (by ID) in the hub.
     *
     * @param list<string> $permissionCodes
     */
    public function attachPermissionsToRole(int $roleId, array $permissionCodes, string $bearerToken): void
    {
        if (empty($permissionCodes)) {
            return;
        }

        $this->request('POST', "/api/v1/iam/roles/{$roleId}/permissions/attach", [
            'headers' => array_merge($this->appKeyHeaders(), [
                'Authorization' => 'Bearer ' . $bearerToken,
            ]),
            'json' => ['permission_codes' => $permissionCodes],
        ]);
    }

    /**
     * Fetch a user profile from the hub. Caller forwards a valid bearer token.
     *
     * @return array<string, mixed>
     */
    public function getUser(int $userId, string $bearerToken): array
    {
        return $this->request('GET', '/api/v1/users/' . $userId, [
            'headers' => array_merge($this->appKeyHeaders(), [
                'Authorization' => 'Bearer ' . $bearerToken,
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function appKeyHeaders(): array
    {
        return [
            'X-App-Key' => $this->config->apiKey,
        ];
    }
}
