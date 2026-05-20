<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Hub;

use App\Libraries\Hub\HubClient;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Hub as HubConfig;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;
use RuntimeException;

/**
 * BFF-107: `HubClient` now extends `AbstractServiceClient`. These tests pin
 * the behaviours the `DomainAuthFilter` and `SyncPermissions` command depend on:
 * service-token caching, introspect downgrade-on-failure, and canonical
 * exception mapping for permission registration.
 */
class HubClientTest extends CIUnitTestCase
{
    private function makeConfig(int $safetyMargin = 30): HubConfig
    {
        $config                           = new HubConfig();
        $config->url                      = 'http://hub.test';
        $config->apiKey                   = 'test-key';
        $config->appCode                  = 'test-app';
        $config->serviceTokenSafetyMargin = $safetyMargin;
        $config->httpTimeout              = 5;
        $config->introspectCacheTtl       = 60;

        return $config;
    }

    public function testServiceTokenCachedHitSkipsHub(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'access_token' => 'cached-token',
            'expires_at'   => time() + 3600,
        ]);

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->never())->method('request');

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->assertSame('cached-token', $client->getServiceToken());
    }

    public function testServiceTokenFetchOnEmptyCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('save');

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->once())
            ->method('request')
            ->with('POST', $this->stringContains('/api/v1/auth/service-token'))
            ->willReturn($this->jsonResponse(200, [
                'data' => ['access_token' => 'fresh-token', 'expires_in' => 1800],
            ]));

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->assertSame('fresh-token', $client->getServiceToken());
    }

    public function testServiceTokenThrowsServiceUnavailableOn5xxAfterRetry(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);

        // AbstractServiceClient retries once on 5xx — expect two calls.
        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturn($this->jsonResponse(500, ['message' => 'hub overloaded']));

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->expectException(ServiceUnavailableException::class);
        $client->getServiceToken();
    }

    public function testIntrospectInvalidTokenReturnsInvalid(): void
    {
        $client = new HubClient(
            $this->makeConfig(),
            $this->createMock(CURLRequest::class),
            $this->createMock(CacheInterface::class),
        );

        $this->assertFalse($client->introspect('')->valid);
    }

    public function testIntrospectCachedHitSkipsHub(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'valid'       => true,
            'uid'         => 7,
            'permissions' => ['items.read'],
        ]);

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->never())->method('request');

        $client = new HubClient($this->makeConfig(), $http, $cache);
        $result = $client->introspect('cached');

        $this->assertTrue($result->valid);
        $this->assertSame(7, $result->uid);
        $this->assertSame(['items.read'], $result->permissions);
    }

    public function testIntrospectDowngradesToInvalidWhenHubUnreachable(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);

        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willThrowException(new RuntimeException('connection refused'));

        $client = new HubClient($this->makeConfig(), $http, $cache);
        $result = $client->introspect('anything');

        $this->assertFalse($result->valid);
        $this->assertSame('hub_unreachable', $result->error);
    }

    public function testRegisterPermissionReturnsTrueOn201(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(201, ['data' => ['id' => 1]]));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->assertTrue($client->registerPermission([
            'code'     => 'items.read',
            'resource' => 'items',
            'action'   => 'read',
        ], 'admin-token'));
    }

    public function testRegisterPermissionReturnsFalseOnConflict(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(409, ['message' => 'already exists']));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->assertFalse($client->registerPermission([
            'code'     => 'items.read',
            'resource' => 'items',
            'action'   => 'read',
        ], 'admin-token'));
    }

    public function testRegisterPermissionPropagatesAuthExceptionOn403(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(403, [
            'message' => 'missing iam.superadmin-access',
        ]));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->expectException(AuthorizationException::class);
        $client->registerPermission([
            'code'     => 'items.read',
            'resource' => 'items',
            'action'   => 'read',
        ], 'underprivileged');
    }

    public function testRegisterPermissionPropagatesAuthExceptionOn401(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(401, ['message' => 'no token']));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->expectException(AuthenticationException::class);
        $client->registerPermission([
            'code'     => 'items.read',
            'resource' => 'items',
            'action'   => 'read',
        ], 'expired');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonResponse(int $status, array $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn(json_encode($body, JSON_THROW_ON_ERROR));

        return $response;
    }
}
