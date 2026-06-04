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

    /** Primary registration uses self-permissions endpoint (no superadmin JWT). */
    public function testRegistersPermissionsViaSelfPermissionsWithoutAdminToken(): void
    {
        $stub = $this->makeHubStub();
        Services::injectMock('hubClient', $stub);

        $exitCode = $this->makeCommand(false)->syncPermissions(false);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $stub->selfCalls, 'Expected exactly one batch self-permissions call');
        $this->assertCount(0, $stub->mirrorCalls, 'Expected no mirror calls when --mirror-to-self is absent');

        $sentCodes = array_column($stub->selfCalls[0], 'code');
        $expectedCodes = array_column(DomainPermissions::PERMISSIONS, 'code');
        sort($sentCodes);
        sort($expectedCodes);
        $this->assertSame($expectedCodes, $sentCodes);
    }

    /** Mirror call uses registerPermission() with application_id=1 (hub self). */
    public function testMirrorsPermissionsToSelfApplicationWhenEnabled(): void
    {
        $stub = $this->makeHubStub();
        Services::injectMock('hubClient', $stub);

        $exitCode = $this->makeCommand(true)->syncPermissions(true, null, 'admin-token');

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $stub->selfCalls, 'Primary batch call should happen once');
        $this->assertCount(count(DomainPermissions::PERMISSIONS), $stub->mirrorCalls, 'Mirror call per permission');

        foreach ($stub->mirrorCalls as $call) {
            $this->assertSame(1, $call['applicationId'], 'Mirror must target application_id=1 (hub self)');
            $this->assertSame('admin-token', $call['bearerToken']);
        }
    }

    /** Primary call never touches registerPermission() — no JWT leaks into the primary path. */
    public function testPrimaryRegistrationNeverCallsRegisterPermission(): void
    {
        $stub = $this->makeHubStub();
        Services::injectMock('hubClient', $stub);

        $this->makeCommand(false)->syncPermissions(false);

        $this->assertCount(0, $stub->mirrorCalls, 'registerPermission() must not be called for primary registration');
    }

    public function testRoleLinkingReturnsErrorExitWhenRoleNotFound(): void
    {
        $stub = $this->makeHubStubWithRoleNotFound();
        Services::injectMock('hubClient', $stub);

        $exitCode = $this->makeCommand(false)->syncPermissions(false, 'nonexistent-role', 'admin-token');

        $this->assertSame(1, $exitCode);
    }

    private function makeHubStub(): object
    {
        return new class () extends HubClient {
            /** @var list<list<array{code: string, resource: string, action: string}>> */
            public array $selfCalls = [];

            /** @var list<array{code: string, applicationId: int|null, bearerToken: string}> */
            public array $mirrorCalls = [];

            public function __construct()
            {
            }

            public function registerSelfPermissions(array $permissions): array
            {
                $this->selfCalls[] = $permissions;

                return ['created' => count($permissions), 'existing' => 0, 'rejected' => 0, 'errors' => []];
            }

            public function registerPermission(array $permission, string $bearerToken, ?int $applicationId = null): bool
            {
                $this->mirrorCalls[] = [
                    'code'          => $permission['code'],
                    'applicationId' => $applicationId,
                    'bearerToken'   => $bearerToken,
                ];

                return true;
            }
        };
    }

    private function makeHubStubWithRoleNotFound(): object
    {
        return new class () extends HubClient {
            public function __construct()
            {
            }

            public function registerSelfPermissions(array $permissions): array
            {
                return ['created' => count($permissions), 'existing' => 0, 'rejected' => 0, 'errors' => []];
            }

            public function registerPermission(array $permission, string $bearerToken, ?int $applicationId = null): bool
            {
                return true;
            }

            public function findRoleByCode(string $code, string $bearerToken): ?array
            {
                return null;
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
