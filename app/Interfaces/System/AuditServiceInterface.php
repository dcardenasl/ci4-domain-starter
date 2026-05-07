<?php

declare(strict_types=1);

namespace App\Interfaces\System;

use App\DTO\SecurityContext;

/**
 * Audit Service Interface
 */
interface AuditServiceInterface
{
    /**
     * Log an audit event (Internal)
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?SecurityContext $context = null,
        string $result = 'success',
        string $severity = 'info',
        array $metadata = [],
        ?string $requestId = null
    ): void;

    /**
     * Log structured events (Internal)
     */
    public function logCreate(string $entityType, int $entityId, array $data, ?SecurityContext $context = null): void;
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null): void;
    public function logDelete(string $entityType, int $entityId, array $data, ?SecurityContext $context = null): void;

    /**
     * List audit logs (API)
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Get single log (API)
     */
    public function show(int $id, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Get logs by entity (Internal/API)
     */
    public function byEntity(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;
}
