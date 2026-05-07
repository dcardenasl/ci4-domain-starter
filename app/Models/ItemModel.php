<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\ItemEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;
use dcardenasl\Ci4ApiCore\Models\BaseAuditableModel;

class ItemModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $returnType = ItemEntity::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected $allowedFields = ['name', 'description'];

    /** @var array<int, string> */
    protected array $searchableFields = ['name'];

    /** @var array<int, string> */
    protected array $filterableFields = ['id'];

    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'created_at', 'name'];

    protected $validationRules = [
        'name' => 'required|string|max_length[255]',
        'description' => 'permit_empty|string',
    ];
}
