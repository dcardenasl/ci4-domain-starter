<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

/**
 * Guardrails for paginated CRUD index return contracts.
 */
class CrudIndexContractsTest extends CIUnitTestCase
{
    /**
     * @return array<int, class-string>
     */
    private function indexedContracts(): array
    {
        return [
            \App\Interfaces\Core\CrudServiceContract::class,
            \App\Interfaces\Tokens\ApiKeyServiceInterface::class,
            \App\Interfaces\System\AuditServiceInterface::class,
            \App\Interfaces\Files\FileServiceInterface::class,
            \App\Interfaces\Users\UserServiceInterface::class,
            \App\Services\Core\BaseCrudService::class,
        ];
    }

    public function testIndexContractsReturnDataTransferObjectInterface(): void
    {
        $violations = [];

        foreach ($this->indexedContracts() as $class) {
            $method = new ReflectionMethod($class, 'index');
            $returnType = $method->getReturnType();
            $typeName = $returnType !== null ? $returnType->getName() : '';

            if ($typeName !== \App\Interfaces\DataTransferObjectInterface::class) {
                $violations[] = "{$class}::index must return " . \App\Interfaces\DataTransferObjectInterface::class;
            }
        }

        $this->assertSame([], $violations, "CRUD index return contract violations:\n- " . implode("\n- ", $violations));
    }

    public function testBaseCrudServiceBuildsPaginatedResponseDto(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Services/Core/BaseCrudService.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString('PaginatedResponseDTO::fromArray', $source);
    }
}
