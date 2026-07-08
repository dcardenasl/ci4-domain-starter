<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Hub;

use App\Libraries\Hub\HubClient;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
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
    private function makeConfig(int $safetyMargin = 30): \dcardenasl\Ci4ApiCore\Http\Client\HubClientConfig
    {
        return new \dcardenasl\Ci4ApiCore\Http\Client\HubClientConfig(
            url: 'http://hub.test',
            apiKey: 'test-key',
            introspectCacheTtl: 60,
            serviceTokenSafetyMargin: $safetyMargin,
            httpTimeout: 5
        );
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

    public function testFindRoleByCodeReturnsFirstItemFromHubResponse(): void
    {
        $roleData = ['id' => 5, 'code' => 'superadmin', 'name' => 'Super Admin'];
        $http     = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(200, [
            'data' => ['items' => [$roleData], 'meta' => ['total' => 1]],
        ]));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));
        $result = $client->findRoleByCode('superadmin', 'admin-token');

        $this->assertSame($roleData, $result);
    }

    public function testFindRoleByCodeReturnsNullOnEmptyItems(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(200, [
            'data' => ['items' => [], 'meta' => ['total' => 0]],
        ]));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));
        $result = $client->findRoleByCode('nonexistent', 'admin-token');

        $this->assertNull($result);
    }

    public function testQueueEmailReturnsJobIdOnSuccess(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->once())
            ->method('request')
            ->with('POST', $this->stringContains('/api/v1/internal/email/queue'))
            ->willReturn($this->jsonResponse(200, [
                'status' => 'success',
                'data'   => ['job_id' => 128],
            ]));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->assertSame(128, $client->queueEmail('user@example.com', 'Subject', '<p>Hi</p>', 'Hi'));
    }

    public function testQueueEmailSendsExpectedPayloadAndAppKeyHeader(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('/api/v1/internal/email/queue'),
                $this->callback(function (array $options): bool {
                    $this->assertSame('test-key', $options['headers']['X-App-Key'] ?? null);
                    $this->assertSame([
                        'to'           => 'user@example.com',
                        'subject'      => 'Subject',
                        'message'      => '<p>Hi</p>',
                        'text_message' => 'Hi',
                    ], $options['json'] ?? null);

                    return true;
                })
            )
            ->willReturn($this->jsonResponse(200, ['data' => ['job_id' => 128]]));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $client->queueEmail('user@example.com', 'Subject', '<p>Hi</p>', 'Hi');
    }

    public function testQueueEmailReturnsZeroAndLogsOnHubError(): void
    {
        $http = $this->createMock(CURLRequest::class);
        // AbstractServiceClient retries once on 5xx — expect two attempts.
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturn($this->jsonResponse(500, ['message' => 'hub overloaded']));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->assertSame(0, $client->queueEmail('user@example.com', 'Subject', '<p>Hi</p>'));
    }

    public function testQueueEmailReturnsZeroAndLogsOnUnreachableHub(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willThrowException(new RuntimeException('connection refused'));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->assertSame(0, $client->queueEmail('user@example.com', 'Subject', '<p>Hi</p>'));
    }

    public function testQueueEmailReturnsZeroOnMalformedResponse(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(200, [
            'status' => 'success',
            'data'   => ['unexpected' => 'shape'],
        ]));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->assertSame(0, $client->queueEmail('user@example.com', 'Subject', '<p>Hi</p>'));
    }

    public function testQueueEmailReturnsZeroOnValidationError(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(422, [
            'message' => 'The subject field is required.',
        ]));

        $client = new HubClient($this->makeConfig(), $http, $this->createMock(CacheInterface::class));

        $this->assertSame(0, $client->queueEmail('user@example.com', '', '<p>Hi</p>'));
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
