<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\FeatureFlags;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

class FeatureToggleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $flag = is_array($arguments) && isset($arguments[0]) ? (string) $arguments[0] : '';
        if ($flag === '') {
            return $request;
        }

        /** @var FeatureFlags $flags */
        $flags = config(FeatureFlags::class, false);
        $enabled = $flags->isEnabled($flag);

        if ($enabled) {
            return $request;
        }

        $message = match ($flag) {
            'monitoring' => lang('Health.monitoringDisabled'),
            default      => lang('Api.requestFailed'),
        };

        return Services::response()
            ->setJSON(ApiResponse::error([], $message, 503))
            ->setStatusCode(503);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
