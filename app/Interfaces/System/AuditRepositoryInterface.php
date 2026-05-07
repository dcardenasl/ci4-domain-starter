<?php

declare(strict_types=1);

namespace App\Interfaces\System;

use App\Interfaces\Core\RepositoryInterface;

/**
 * Audit Repository Interface
 */
interface AuditRepositoryInterface extends RepositoryInterface
{
    /**
     * Get audit logs for an entity
     */
    public function getByEntity(string $entityType, int $entityId): array;

    /**
     * Get audit logs for a user
     */
    public function getByUser(int $userId, int $limit = 50): array;

    /**
     * Get recent audit logs
     */
    public function getRecent(int $limit = 100): array;

    /**
     * Get action facets for metrics
     */
    public function getActionFacets(int $windowDays = 90, int $limit = 100): array;

    /**
     * Get entity type facets for metrics
     */
    public function getEntityTypeFacets(int $windowDays = 90, int $limit = 100): array;
}
