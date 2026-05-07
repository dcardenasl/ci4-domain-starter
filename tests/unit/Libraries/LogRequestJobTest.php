<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\Queue\Jobs\LogRequestJob;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * LogRequestJob Tests
 */
class LogRequestJobTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testHandleInsertsRequestLogWithAllData(): void
    {
        $job = new LogRequestJob([
            'method' => 'GET',
            'uri' => '/api/v1/users',
            'user_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'response_code' => 200,
            'response_time' => 150,
        ]);

        $job->handle();

        $log = $this->db->table('request_logs')
            ->where('uri', '/api/v1/users')
            ->get()
            ->getRow();

        $this->assertNotNull($log);
        $this->assertEquals('GET', $log->method);
        $this->assertEquals(200, $log->response_code);
        $this->assertEquals(1, $log->user_id);
    }

    public function testHandleInsertsRequestLogWithMinimalData(): void
    {
        $job = new LogRequestJob([
            'uri' => '/api/v1/health',
        ]);

        $job->handle();

        $log = $this->db->table('request_logs')
            ->where('uri', '/api/v1/health')
            ->get()
            ->getRow();

        $this->assertNotNull($log);
        $this->assertEquals('UNKNOWN', $log->method);
        $this->assertEquals(0, $log->response_code);
        $this->assertNull($log->user_id);
    }

    public function testHandleUsesDefaultsForMissingFields(): void
    {
        $job = new LogRequestJob([]);

        $job->handle();

        $count = $this->db->table('request_logs')
            ->countAllResults();

        $this->assertGreaterThan(0, $count);
    }

    public function testJobCanBeConstructedWithData(): void
    {
        $job = new LogRequestJob([
            'method' => 'POST',
            'uri' => '/api/v1/login',
            'response_code' => 200,
        ]);

        $this->assertInstanceOf(LogRequestJob::class, $job);
    }
}
