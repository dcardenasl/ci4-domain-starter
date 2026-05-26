<?php

declare(strict_types=1);

namespace App\Services\Example;

use App\Entities\ItemEntity;
use App\Interfaces\Example\ItemServiceInterface;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;

/**
 * @extends BaseCrudService<ItemEntity>
 */
class ItemService extends BaseCrudService implements ItemServiceInterface
{
    /**
     * @param RepositoryInterface<ItemEntity> $itemRepository
     */
    public function __construct(
        RepositoryInterface $itemRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($itemRepository, $responseMapper);
    }

    /**
     * Domain Hooks
     *
     * Implement beforeStore, afterStore, beforeUpdate, etc.,
     * to add specific business logic while keeping the service layer clean.
     */
}
