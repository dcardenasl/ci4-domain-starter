<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Data Transfer Object Interface
 *
 * Base interface for all Request and Response DTOs.
 */
interface DataTransferObjectInterface
{
    /**
     * Convert the DTO to an associative array.
     * Useful for persistence or standard API responses.
     */
    public function toArray(): array;
}
