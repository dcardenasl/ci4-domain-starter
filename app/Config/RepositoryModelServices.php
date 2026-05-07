<?php

declare(strict_types=1);

namespace Config;

trait RepositoryModelServices
{
    public static function auditRepository(bool $getShared = true): \App\Interfaces\System\AuditRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('auditRepository');
        }

        return new \App\Repositories\System\AuditRepository(model(\App\Models\AuditLogModel::class));
    }
}
