<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Example;

use App\Interfaces\Example\ItemServiceInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Smoke tests for ItemService. Extend with domain-specific assertions
 * as business rules accumulate in the service.
 *
 * @internal
 */
final class ItemServiceTest extends CIUnitTestCase
{
    public function testServiceImplementsItsInterface(): void
    {
        $service = Services::itemService(false);

        $this->assertInstanceOf(ItemServiceInterface::class, $service);
    }
}
