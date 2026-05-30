<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\ItemModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Smoke tests for ItemModel. Extend with persistence scenarios as
 * domain behavior solidifies.
 *
 * @internal
 */
final class ItemModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testModelReportsCorrectTable(): void
    {
        $model = new ItemModel();

        $this->assertSame('items', $model->getTable());
    }
}
