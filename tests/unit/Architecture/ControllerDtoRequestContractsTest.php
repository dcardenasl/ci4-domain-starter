<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrails for DTO-driven controller request handling.
 */
class ControllerDtoRequestContractsTest extends CIUnitTestCase
{
    /**
     * @return array<string, array<int, string>>
     */
    private function controllerSnippets(): array
    {
        return [
            'app/Controllers/Api/V1/Example/ItemController.php' => [
                "handleRequest('index', ItemIndexRequestDTO::class)",
                "handleRequest('store', ItemCreateRequestDTO::class)",
                "ItemUpdateRequestDTO::class",
                "itemService->show(\$id, \$context)",
                "itemService->destroy(\$id, \$context)",
            ],
        ];
    }

    public function testControllersUseHandleRequestWithRequestDtos(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $violations = [];

        foreach ($this->controllerSnippets() as $relativePath => $requiredSnippets) {
            $path = $root . '/' . $relativePath;
            $source = file_get_contents($path);

            if (! is_string($source) || $source === '') {
                $violations[] = "{$relativePath}: could not read source";
                continue;
            }

            foreach ($requiredSnippets as $snippet) {
                if (! str_contains($source, $snippet)) {
                    $violations[] = "{$relativePath}: missing snippet -> {$snippet}";
                }
            }
        }

        $this->assertSame([], $violations, "Controller DTO pipeline violations:\n- " . implode("\n- ", $violations));
    }
}
