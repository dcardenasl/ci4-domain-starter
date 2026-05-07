<?php

declare(strict_types=1);

namespace App\Repositories\System;

use App\Interfaces\System\AuditRepositoryInterface;
use App\Repositories\BaseRepository;

/**
 * Audit Repository Implementation
 */
class AuditRepository extends BaseRepository implements AuditRepositoryInterface
{
    public function getByEntity(string $entityType, int $entityId): array
    {
        return $this->model->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->model->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }

    public function getRecent(int $limit = 100): array
    {
        return $this->model->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }

    public function getActionFacets(int $windowDays = 90, int $limit = 100): array
    {
        /** @var \App\Models\AuditLogModel $model */
        $model = $this->model;
        return $model->getActionFacets($windowDays, $limit);
    }

    public function getEntityTypeFacets(int $windowDays = 90, int $limit = 100): array
    {
        /** @var \App\Models\AuditLogModel $model */
        $model = $this->model;
        return $model->getEntityTypeFacets($windowDays, $limit);
    }
}
