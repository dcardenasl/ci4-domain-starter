<?php

declare(strict_types=1);

namespace App\Interfaces\Mappers;

use App\Interfaces\DataTransferObjectInterface;

interface ResponseMapperInterface
{
    public function map(object $entity): DataTransferObjectInterface;
}
