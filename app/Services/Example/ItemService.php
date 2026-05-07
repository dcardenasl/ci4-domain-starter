<?php

declare(strict_types=1);

namespace App\Services\Example;

use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Example\ItemServiceInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Services\Core\BaseCrudService;

class ItemService extends BaseCrudService implements ItemServiceInterface
{
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
