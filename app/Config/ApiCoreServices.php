<?php

declare(strict_types=1);

namespace Config;

/**
 * API Core Services
 *
 * Centralizes essential infrastructure services for the API lifecycle,
 * including request orchestration, DTO factories, and queue management.
 */
trait ApiCoreServices
{
    public static function requestDataCollector(bool $getShared = true): \App\Support\RequestDataCollector
    {
        if ($getShared) {
            return static::getSharedInstance('requestDataCollector');
        }

        return new \App\Support\RequestDataCollector();
    }

    public static function requestDtoFactory(bool $getShared = true): \App\Support\RequestDtoFactory
    {
        if ($getShared) {
            return static::getSharedInstance('requestDtoFactory');
        }

        return new \App\Support\RequestDtoFactory();
    }

    public static function responseDtoFactory(bool $getShared = true): \App\Support\ResponseDtoFactory
    {
        if ($getShared) {
            return static::getSharedInstance('responseDtoFactory');
        }

        return new \App\Support\ResponseDtoFactory();
    }

    public static function queueManager(bool $getShared = true): \App\Libraries\Queue\QueueManager
    {
        if ($getShared) {
            return static::getSharedInstance('queueManager');
        }

        return new \App\Libraries\Queue\QueueManager();
    }
}
