<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\DomainAuthFilter;
use App\Libraries\Hub\HubClient;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use dcardenasl\Ci4ApiCore\Http\ContextHolder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DomainAuthFilter::class)]
final class DomainAuthFilterTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ContextHolder::flush();
        Services::resetSingle('hubClient');
    }

    protected function tearDown(): void
    {
        ContextHolder::flush();
        Services::resetSingle('hubClient');
        parent::tearDown();
    }

    public function testReturns401WhenAuthorizationHeaderMissing(): void
    {
        $request = $this->makeRequest('');
        $filter  = new DomainAuthFilter();

        $response = $filter->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testReturns401WhenAuthorizationFormatInvalid(): void
    {
        $request = $this->makeRequest('Basic abc');
        $filter  = new DomainAuthFilter();

        $response = $filter->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testReturns401WhenHubReportsInvalidToken(): void
    {
        $this->bindHubClient($this->makeHubClientStub(IntrospectResult::invalid()));

        $request = $this->makeRequest('Bearer expired-token');
        $filter  = new DomainAuthFilter();

        $response = $filter->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testInjectsAuthContextWhenHubAccepts(): void
    {
        $this->bindHubClient($this->makeHubClientStub(new IntrospectResult(
            valid: true,
            uid: 42,
            permissions: ['items.read', 'items.write'],
            exp: time() + 3600,
            error: null,
        )));

        $request = $this->makeRequest('Bearer good-token');
        $filter  = new DomainAuthFilter();

        $result = $filter->before($request);

        $this->assertSame($request, $result, 'Filter should return the request on success.');
        $this->assertSame(42, $request->getAuthUserId());
        $this->assertSame(['items.read', 'items.write'], $request->getAuthPermissions());

        $context = ContextHolder::get();
        $this->assertNotNull($context);
        $this->assertSame(42, $context->user_id);
        $this->assertSame(['items.read', 'items.write'], $context->permissions);
    }

    private function makeRequest(string $authorization): ApiRequest
    {
        $request = new ApiRequest(
            new App(),
            \Config\Services::uri(),
            'php://input',
            new \CodeIgniter\HTTP\UserAgent()
        );

        if ($authorization !== '') {
            $request->setHeader('Authorization', $authorization);
        }

        return $request;
    }

    private function makeHubClientStub(IntrospectResult $result): HubClient
    {
        return new class ($result) extends HubClient {
            public function __construct(private readonly IntrospectResult $result)
            {
                // Skip parent constructor — we don't need real wiring for the stub.
            }

            public function introspect(string $token): IntrospectResult
            {
                return $this->result;
            }
        };
    }

    private function bindHubClient(HubClient $client): void
    {
        Services::injectMock('hubClient', $client);
    }
}
