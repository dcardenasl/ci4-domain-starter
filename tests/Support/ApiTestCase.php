<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * ApiTestCase
 *
 * Base class for API feature tests.
 * Automatically handles request state isolation between multiple calls in a single test.
 */
abstract class ApiTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * Database migration settings
     * migrateOnce ensures migrations run only once per suite
     */
    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $basePath    = APPPATH . 'Database';

    /**
     * Reset the request and other services before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        \dcardenasl\Ci4ApiCore\Services\Audit\AuditService::$forceEnabledInTests = false;
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::flush();
        $this->resetCacheState();
        $this->resetState();
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        \dcardenasl\Ci4ApiCore\Services\Audit\AuditService::$forceEnabledInTests = false;
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::flush();
        $this->resetCacheState();
        $this->resetState();
        parent::tearDown();
    }

    /**
     * Resets the request state. Use this between consecutive API calls
     * in the same test method to ensure complete isolation.
     */
    protected function resetRequest(): void
    {
        $this->resetState();
        $this->reapplyTestRequestHeaders();
    }

    /**
     * Resets PHP globals and CodeIgniter shared services to ensure
     * a clean state for the next request.
     */
    protected function resetState(): void
    {
        // Clear PHP globals that CI4's IncomingRequest might use
        $_POST    = [];
        $_GET     = [];
        $_FILES   = [];
        $_REQUEST = [];

        // Reset the shared 'request' service instance
        Services::resetSingle('request');

        // Reset the $request property in FeatureTestTrait to force it
        // to create a new one for the next call
        $this->request = null;

        $this->reapplyTestRequestHeaders();
    }

    protected function resetCacheState(): void
    {
        try {
            Services::cache()->clean();
        } catch (\Throwable $e) {
            // Ignore cache reset issues in tests that do not use cache-backed features.
        }
    }

    /**
     * Helper to get a clean JSON response body as an array.
     */
    protected function getResponseJson($result): array
    {
        return json_decode($result->getJSON(), true) ?? [];
    }

    protected array $testRequestHeaders = [];

    protected function setTestRequestHeaders(array $headers): void
    {
        $this->testRequestHeaders = $headers;
        $this->withHeaders($headers);
    }

    protected function reapplyTestRequestHeaders(): void
    {
        $this->withHeaders($this->testRequestHeaders);
    }

    protected function clearTestRequestHeaders(): void
    {
        $this->testRequestHeaders = [];
        $this->withHeaders([]);
    }
}
