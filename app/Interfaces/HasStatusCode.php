<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Interface for exceptions that provide an HTTP status code.
 */
interface HasStatusCode
{
    public function getStatusCode(): int;
}
