<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;

require_once __DIR__ . '/ApiCoreServices.php';
require_once __DIR__ . '/ExampleDomainServices.php';
require_once __DIR__ . '/SystemMonitoringServices.php';
require_once __DIR__ . '/RepositoryModelServices.php';

/**
 * Services Configuration file.
 */
class Services extends BaseService
{
    use ApiCoreServices;
    use ExampleDomainServices;
    use SystemMonitoringServices;
    use RepositoryModelServices;

    public static function hubClient(bool $getShared = true): \App\Libraries\Hub\HubClient
    {
        if ($getShared) {
            return static::getSharedInstance('hubClient');
        }

        return new \App\Libraries\Hub\HubClient(
            config('Hub'),
            \Config\Services::curlrequest(),
            \Config\Services::cache()
        );
    }

    /**
     * The Request Service
     *
     * @param \Config\App|bool $getShared
     */
    public static function request($getShared = true): \App\HTTP\ApiRequest
    {
        if (is_bool($getShared) && $getShared) {
            return static::getSharedInstance('request');
        }

        $config = $getShared instanceof \Config\App ? $getShared : config('App');

        return new \App\HTTP\ApiRequest(
            $config,
            static::uri(),
            'php://input',
            new \CodeIgniter\HTTP\UserAgent()
        );
    }
}
