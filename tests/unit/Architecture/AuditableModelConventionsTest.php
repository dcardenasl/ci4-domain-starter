<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

class AuditableModelConventionsTest extends CIUnitTestCase
{
    public function testAuditableModelsExtendSharedBaseAuditableModel(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $models = [
            'app/Models/UserModel.php',
            'app/Models/FileModel.php',
            'app/Models/ApiKeyModel.php',
        ];

        $violations = [];
        foreach ($models as $relative) {
            $path = $root . DIRECTORY_SEPARATOR . $relative;
            $source = file_get_contents($path);
            if (!is_string($source) || $source === '') {
                $violations[] = "{$relative}: could not read source";
                continue;
            }

            if (!str_contains($source, 'extends BaseAuditableModel')) {
                $violations[] = "{$relative}: must extend BaseAuditableModel";
            }

            if (str_contains($source, 'use App\Traits\Auditable;')) {
                $violations[] = "{$relative}: should not import Auditable directly";
            }
        }

        $this->assertSame([], $violations, "Auditable model convention violations:\n- " . implode("\n- ", $violations));
    }
}
