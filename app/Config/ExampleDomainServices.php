<?php

declare(strict_types=1);

namespace Config;

trait ExampleDomainServices
{
    public static function itemResponseMapper(bool $getShared = true): \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('itemResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Example\ItemResponseDTO::class
        );
    }

    public static function itemService(bool $getShared = true): \App\Interfaces\Example\ItemServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('itemService');
        }

        return new \App\Services\Example\ItemService(
            new \App\Repositories\GenericRepository(model(\App\Models\ItemModel::class)),
            static::itemResponseMapper()
        );
    }
}
