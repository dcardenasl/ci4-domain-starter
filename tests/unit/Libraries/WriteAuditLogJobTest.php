<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Interfaces\System\AuditRepositoryInterface;
use App\Libraries\Queue\Jobs\WriteAuditLogJob;
use App\Services\System\AuditWriter;
use CodeIgniter\Test\CIUnitTestCase;

final class WriteAuditLogJobTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        \Config\Services::resetSingle('auditWriter');
        parent::tearDown();
    }

    public function testHandleThrowsWhenPayloadIsMissing(): void
    {
        $job = new WriteAuditLogJob([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required audit payload');

        $job->handle();
    }

    public function testHandleWritesAuditPayload(): void
    {
        $payload = [
            'user_id' => 1,
            'action' => 'create',
            'entity_type' => 'users',
            'entity_id' => 1,
            'old_values' => null,
            'new_values' => '{"email":"test@example.com"}',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'result' => 'success',
            'severity' => 'info',
            'request_id' => 'req-1',
            'metadata' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $repository = $this->createMock(AuditRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('insert')
            ->with($payload);

        $writer = new AuditWriter($repository);
        \Config\Services::injectMock('auditWriter', $writer);

        $job = new WriteAuditLogJob(['audit' => $payload]);
        $job->handle();
    }
}
