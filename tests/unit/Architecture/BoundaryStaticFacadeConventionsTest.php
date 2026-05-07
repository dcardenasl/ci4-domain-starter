<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrail to prevent static service facade usage in boundary utilities.
 */
final class BoundaryStaticFacadeConventionsTest extends CIUnitTestCase
{
    public function testBaseRequestDtoAvoidsStaticValidationFacade(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/DTO/Request/BaseRequestDTO.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringNotContainsString('\\Config\\Services::validation(', $source);
    }

    public function testAuditableTraitAvoidsStaticAuditFacade(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Traits/Auditable.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringNotContainsString('\\Config\\Services::auditService(', $source);
    }
}
