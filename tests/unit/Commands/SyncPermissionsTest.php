<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\SyncPermissions;
use App\Libraries\Hub\HubClient;
use CodeIgniter\CLI\Commands;
use Config\DomainPermissions;
use Config\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SyncPermissions::class)]
final class SyncPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        Services::resetSingle('hubClient');
    }

    protected function tearDown(): void
    {
        Services::resetSingle('hubClient');
    }

    public function testRegistersPermissionsOnceWithoutMirrorFlag(): void
    {
        $stub = $this->makeHubStub();
        Services::injectMock('hubClient', $stub);

        $exitCode = $this->makeCommand(false)->syncPermissions('admin-token', false);

        $this->assertSame(0, $exitCode);
        $this->assertCount(count(DomainPermissions::PERMISSIONS), $stub->calls);

        foreach ($stub->calls as $call) {
            $this->assertNull($call['applicationId']);
        }
    }

    public function testMirrorsPermissionsToSelfWhenEnabled(): void
    {
        $stub = $this->makeHubStub();
        Services::injectMock('hubClient', $stub);

        $exitCode = $this->makeCommand(true)->syncPermissions('admin-token', true);

        $this->assertSame(0, $exitCode);

        $expected = count(DomainPermissions::PERMISSIONS);
        $this->assertCount($expected * 2, $stub->calls);

        $primaryCalls = array_slice($stub->calls, 0, $expected);
        $mirrorCalls  = array_slice($stub->calls, $expected);

        foreach ($primaryCalls as $call) {
            $this->assertNull($call['applicationId']);
        }

        foreach ($mirrorCalls as $call) {
            $this->assertSame(1, $call['applicationId']);
        }
    }

    private function makeHubStub(): object
    {
        return new class () extends HubClient {
            /**
             * @var list<array{code: string, applicationId: int|null, bearerToken: string}>
             */
            public array $calls = [];

            public function __construct()
            {
            }

            public function registerPermission(array $permission, string $bearerToken, ?int $applicationId = null): bool
            {
                $this->calls[] = [
                    'code'           => $permission['code'],
                    'applicationId'  => $applicationId,
                    'bearerToken'    => $bearerToken,
                ];

                return true;
            }
        };
    }

    private function makeCommand(bool $mirrorToSelf): SyncPermissions
    {
        $logger   = $this->createMock(LoggerInterface::class);
        $commands = $this->createMock(Commands::class);

        return new class ($logger, $commands, $mirrorToSelf) extends SyncPermissions {
            public function __construct($logger, $commands, private bool $mirrorToSelf)
            {
                parent::__construct($logger, $commands);
            }

            protected function resolveAdminToken(): string
            {
                return 'admin-token';
            }

            protected function shouldMirrorToSelf(): bool
            {
                return $this->mirrorToSelf;
            }

            protected function writeLine(string $message, string $color = 'white'): void
            {
            }

            protected function writeError(string $message): void
            {
            }

            protected function newLine(int $repeat = 1): void
            {
            }
        };
    }
}
