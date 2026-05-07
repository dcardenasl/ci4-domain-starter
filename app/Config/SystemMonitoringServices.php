<?php

declare(strict_types=1);

namespace Config;

/**
 * System Monitoring Services
 *
 * Audit logging infrastructure used by BaseAuditableModel and the
 * Auditable trait so that domain CRUDs persist a local audit log.
 */
trait SystemMonitoringServices
{
    public static function auditService(bool $getShared = true): \dcardenasl\Ci4ApiCore\Services\AuditServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        return new \App\Services\System\AuditService(
            static::auditRepository(),
            static::auditResponseMapper(),
            static::auditWriter(),
            static::queueManager(),
            config('Audit'),
            ENVIRONMENT !== 'testing',
            '127.0.0.1',
            'system',
            static::auditPayloadSanitizer()
        );
    }

    public static function auditWriter(bool $getShared = true): \App\Services\System\AuditWriter
    {
        if ($getShared) {
            return static::getSharedInstance('auditWriter');
        }

        return new \App\Services\System\AuditWriter(
            static::auditRepository()
        );
    }

    public static function auditPayloadSanitizer(bool $getShared = true): \App\Services\System\AuditPayloadSanitizer
    {
        if ($getShared) {
            return static::getSharedInstance('auditPayloadSanitizer');
        }

        return new \App\Services\System\AuditPayloadSanitizer();
    }

    public static function requestAuditContextFactory(bool $getShared = true): \App\Support\RequestAuditContextFactory
    {
        if ($getShared) {
            return static::getSharedInstance('requestAuditContextFactory');
        }

        return new \App\Support\RequestAuditContextFactory();
    }

    public static function auditResponseMapper(bool $getShared = true): \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('auditResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Audit\AuditResponseDTO::class
        );
    }
}
