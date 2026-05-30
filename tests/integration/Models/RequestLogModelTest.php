<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\RequestLogModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class RequestLogModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testGetStatsReturnsSloAndBreakdownMetrics(): void
    {
        $model = new RequestLogModel();
        $now = date('Y-m-d H:i:s');

        $rows = [
            ['response_code' => 200, 'response_time' => 100],
            ['response_code' => 201, 'response_time' => 200],
            ['response_code' => 302, 'response_time' => 300],
            ['response_code' => 404, 'response_time' => 400],
            ['response_code' => 500, 'response_time' => 500],
        ];

        foreach ($rows as $row) {
            $model->insert([
                'method' => 'GET',
                'uri' => '/api/v1/test',
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'response_code' => $row['response_code'],
                'response_time' => $row['response_time'],
                'created_at' => $now,
            ]);
        }

        $stats = $model->getStats('day');

        $this->assertSame(5, $stats['total_requests']);
        $this->assertSame(3, $stats['successful_requests']);
        $this->assertSame(2, $stats['failed_requests']);
        $this->assertSame(300.0, (float) $stats['avg_response_time_ms']);
        $this->assertSame(500.0, (float) $stats['p95_response_time_ms']);
        $this->assertSame(500.0, (float) $stats['p99_response_time_ms']);
        $this->assertSame(40.0, (float) $stats['error_rate_percent']);
        $this->assertSame(60.0, (float) $stats['availability_percent']);
        $this->assertSame(2, $stats['status_code_breakdown']['2xx']);
        $this->assertSame(1, $stats['status_code_breakdown']['3xx']);
        $this->assertSame(1, $stats['status_code_breakdown']['4xx']);
        $this->assertSame(1, $stats['status_code_breakdown']['5xx']);
        $this->assertArrayHasKey('slo', $stats);
        $this->assertArrayHasKey('p95_target_ms', $stats['slo']);
        $this->assertArrayHasKey('p95_target_met', $stats['slo']);
    }
}
