<?php

declare(strict_types=1);

namespace App\Support;

use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;

/**
 * Response DTO Factory
 *
 * Centralizes the transformation of entities/models into Response DTOs
 * using the provided mappers.
 */
class ResponseDtoFactory
{
    /**
     * Map a single entity to a Response DTO
     */
    public function make(ResponseMapperInterface $mapper, object $entity): DataTransferObjectInterface
    {
        return $mapper->map($entity);
    }

    /**
     * Map a collection of entities to an array of Response DTOs
     */
    public function makeCollection(ResponseMapperInterface $mapper, array $entities): array
    {
        return array_map(
            fn ($entity) => $mapper->map($entity),
            $entities
        );
    }
}
