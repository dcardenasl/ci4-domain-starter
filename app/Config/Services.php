<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;

require_once __DIR__ . '/ApiCoreServices.php';
require_once __DIR__ . '/ExampleDomainServices.php';
require_once __DIR__ . '/SystemMonitoringServices.php';
require_once __DIR__ . '/RepositoryModelServices.php';
require_once __DIR__ . '/LocalizationServices.php';

/**
 * Services Configuration file.
 */
class Services extends BaseService
{
    use ApiCoreServices;
    use ExampleDomainServices;
    use SystemMonitoringServices;
    use RepositoryModelServices;
    use LocalizationServices;

    public static function hubClient(bool $getShared = true): \App\Libraries\Hub\HubClient
    {
        if ($getShared) {
            return static::getSharedInstance('hubClient');
        }

        /** @var \Config\Hub $hubConfig */
        $hubConfig = config('Hub');

        $coreHubConfig = new \dcardenasl\Ci4ApiCore\Http\Client\HubClientConfig(
            url: $hubConfig->url,
            apiKey: $hubConfig->apiKey,
            introspectPath: $hubConfig->introspectPath ?? '/api/v1/auth/introspect',
            serviceTokenPath: $hubConfig->serviceTokenPath ?? '/api/v1/auth/service-token',
            permissionsPath: $hubConfig->permissionsPath ?? '/api/v1/iam/permissions',
            introspectCacheTtl: $hubConfig->introspectCacheTtl ?? 60,
            serviceTokenSafetyMargin: $hubConfig->serviceTokenSafetyMargin ?? 30,
            httpTimeout: $hubConfig->httpTimeout ?? 5,
        );

        return new \App\Libraries\Hub\HubClient(
            $coreHubConfig,
            \Config\Services::curlrequest(),
            \Config\Services::cache()
        );
    }

    /**
     * The Request Service
     *
     * @param \Config\App|bool $getShared
     */
    public static function request($getShared = true): \dcardenasl\Ci4ApiCore\Http\ApiRequest
    {
        if (is_bool($getShared) && $getShared) {
            return static::getSharedInstance('request');
        }

        $config = $getShared instanceof \Config\App ? $getShared : config('App');

        return new \dcardenasl\Ci4ApiCore\Http\ApiRequest(
            $config,
            static::uri(),
            'php://input',
            new \CodeIgniter\HTTP\UserAgent()
        );
    }
}
