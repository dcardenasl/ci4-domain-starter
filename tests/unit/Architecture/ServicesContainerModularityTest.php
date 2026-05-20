<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

class ServicesContainerModularityTest extends CIUnitTestCase
{
    public function testServicesClassDelegatesDomainFactoriesToTraits(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Config/Services.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString('use ApiCoreServices;', $source);
        $this->assertStringContainsString('use ExampleDomainServices;', $source);
        $this->assertStringContainsString('use SystemMonitoringServices;', $source);
        $this->assertStringContainsString('use RepositoryModelServices;', $source);

        // Domain factories should live in traits, not in the root Services class.
        $this->assertStringNotContainsString('public static function itemService(', $source);
        $this->assertStringNotContainsString('public static function auditService(', $source);
    }
}
