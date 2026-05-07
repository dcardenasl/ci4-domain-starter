<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

class TransactionConventionsTest extends CIUnitTestCase
{
    public function testHandlesTransactionsCatchesThrowableForRollbackSafety(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Traits/HandlesTransactions.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString('catch (Throwable $e)', $source);
        $this->assertStringContainsString('$db->transRollback();', $source);
    }
}
