<?php

declare(strict_types=1);

namespace Tests\Unit\Wiring;

if (! defined('APPPATH')) {
    define('APPPATH', dirname(__DIR__, 3) . '/app/');
}

use dcardenasl\Ci4ApiScaffolding\Config\ScaffoldingConfig;
use dcardenasl\Ci4ApiScaffolding\Core\ResourceSchema;
use dcardenasl\Ci4ApiScaffolding\Wiring\ConfigWireman;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigWireman::class)]
final class ConfigWiremanTest extends TestCase
{
    private string $permissionsFile;
    private string $originalPermissions;

    protected function setUp(): void
    {
        $this->permissionsFile = APPPATH . 'Config/DomainPermissions.php';
        $this->originalPermissions = (string) file_get_contents($this->permissionsFile);
    }

    protected function tearDown(): void
    {
        file_put_contents($this->permissionsFile, $this->originalPermissions);
    }

    public function testRegistersReadWriteDeletePermissionsFromSchema(): void
    {
        $this->makeWireman()->callRegisterPermissions($this->makeSchema('Product'));

        $content = (string) file_get_contents($this->permissionsFile);

        $this->assertStringContainsString("'code' => 'product.read'", $content);
        $this->assertStringContainsString("'resource' => 'products'", $content);
        $this->assertStringContainsString("'description' => 'Read Products'", $content);
        $this->assertStringContainsString("'code' => 'product.create'", $content);
        $this->assertStringContainsString("'code' => 'product.update'", $content);
        $this->assertStringContainsString("'code' => 'product.delete'", $content);
    }

    public function testRegisterPermissionsIsIdempotent(): void
    {
        $wireman = $this->makeWireman();
        $schema = $this->makeSchema('Product');
        $wireman->callRegisterPermissions($schema);
        $wireman->callRegisterPermissions($schema);

        $content = (string) file_get_contents($this->permissionsFile);

        $this->assertSame(1, substr_count($content, "'code' => 'product.read'"));
        $this->assertSame(1, substr_count($content, "'code' => 'product.create'"));
        $this->assertSame(1, substr_count($content, "'code' => 'product.update'"));
        $this->assertSame(1, substr_count($content, "'code' => 'product.delete'"));
    }

    private function makeSchema(string $resource): ResourceSchema
    {
        return new ResourceSchema(
            resource: $resource,
            domain: 'Catalog',
            route: 'products',
            fields: [],
        );
    }

    private function makeWireman(): ConfigWireman
    {
        return new class (ScaffoldingConfig::defaults()) extends ConfigWireman {
            public function callRegisterPermissions(ResourceSchema $schema): void
            {
                $this->registerPermissions($schema);
            }
        };
    }
}
