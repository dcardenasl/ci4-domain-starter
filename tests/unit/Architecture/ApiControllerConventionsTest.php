<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Prevent regressions in ApiController response semantics.
 */
class ApiControllerConventionsTest extends CIUnitTestCase
{
    public function testApiControllerDoesNotInferAcceptedStatusFromMessageText(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Controllers/ApiController.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);

        // Heuristic based on message text created false positives and hidden coupling.
        $this->assertStringNotContainsString("str_contains(\$msg, 'pending')", $source);
        $this->assertStringNotContainsString("str_contains(\$msg, 'pendiente')", $source);
    }

    public function testApiControllerRequiresExplicitServiceResolutionContract(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Controllers/ApiController.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString('abstract protected function resolveDefaultService(): object;', $source);
        $this->assertStringNotContainsString('protected function getService(): object', $source);
    }
}
