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
            'app/Controllers/Api/V1/Users/UserController.php' => [
                "handleRequest('index', UserIndexRequestDTO::class)",
                "handleRequest('store', UserCreateRequestDTO::class)",
                "UserUpdateRequestDTO::class",
            ],
            'app/Controllers/Api/V1/Admin/ApiKeyController.php' => [
                "handleRequest('index', ApiKeyIndexRequestDTO::class)",
                "handleRequest('store', ApiKeyCreateRequestDTO::class)",
                "ApiKeyUpdateRequestDTO::class",
            ],
            'app/Controllers/Api/V1/Admin/AuditController.php' => [
                "handleRequest('index', AuditIndexRequestDTO::class)",
                "handleRequest(\n            'byEntity',\n            AuditByEntityRequestDTO::class",
            ],
            'app/Controllers/Api/V1/Files/FileController.php' => [
                "handleRequest('index', FileIndexRequestDTO::class)",
                "handleRequest('upload', FileUploadRequestDTO::class)",
                "fileService->destroy(\$id, \$context)",
            ],
            'app/Controllers/Api/V1/Admin/MetricsController.php' => [
                "handleRequest('getOverview', MetricsQueryRequestDTO::class)",
                "handleRequest('getRequestStats', MetricsQueryRequestDTO::class)",
                "handleRequest('getSlowRequests', SlowRequestsQueryRequestDTO::class)",
                "handleRequest(\n            'getCustomMetric',\n            CustomMetricQueryRequestDTO::class",
                "handleRequest('record', RecordMetricRequestDTO::class)",
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
