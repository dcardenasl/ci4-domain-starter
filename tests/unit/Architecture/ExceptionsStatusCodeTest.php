<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Interfaces\HasStatusCode;
use CodeIgniter\Test\CIUnitTestCase;

final class ExceptionsStatusCodeTest extends CIUnitTestCase
{
    public function testAllExceptionsImplementHasStatusCode(): void
    {
        $exceptionDir = APPPATH . 'Exceptions';
        $files = glob($exceptionDir . '/*.php') ?: [];

        foreach ($files as $file) {
            $className = 'App\\Exceptions\\' . basename($file, '.php');

            if (!class_exists($className)) {
                continue;
            }

            $implements = class_implements($className);

            $this->assertIsArray($implements);
            $this->assertContains(
                HasStatusCode::class,
                $implements,
                sprintf('%s must implement %s', $className, HasStatusCode::class)
            );
        }
    }
}
