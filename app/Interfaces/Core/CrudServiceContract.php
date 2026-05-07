<?php

declare(strict_types=1);

namespace App\Interfaces\Core;

use App\DTO\SecurityContext;

/**
 * Modernized CRUD Service Contract
 *
 * Enforces strict typing using Data Transfer Objects (DTOs)
 * for all input data.
 */
interface CrudServiceContract
{
    /**
     * Get a paginated list of resources
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Get a single resource by ID
     */
    public function show(int $id, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Create a new resource
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Update an existing resource
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Remove a resource (soft or hard delete)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;
}
