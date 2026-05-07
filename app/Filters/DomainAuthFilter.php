<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;
use dcardenasl\Ci4ApiCore\Http\ContextHolder;

/**
 * DomainAuthFilter — validates Bearer JWTs by delegating to the hub's
 * /api/v1/auth/introspect endpoint.
 *
 * Mirrors the contract that `JwtAuthFilter` implements in ci4-api-starter so
 * downstream `PermissionFilter` reads the same `ApiRequest::getAuth*` API
 * without modification.
 */
class DomainAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if ($authHeader === '') {
            return $this->unauthorized(lang('Api.authRequired'));
        }

        if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $match)) {
            return $this->unauthorized(lang('Api.invalidToken'));
        }

        $token = trim($match[1]);

        $hub = Services::hubClient();
        $result = $hub->introspect($token);

        if (! $result->valid || $result->uid === null) {
            $error = $result->error ?? 'invalid_or_expired';
            $message = $error === 'hub_unreachable'
                ? lang('Api.hubUnreachable')
                : lang('Api.invalidToken');

            return $this->unauthorized($message);
        }

        if ($request instanceof ApiRequest) {
            $request->setAuthContext($result->uid, $result->permissions);
        }

        ContextHolder::set(Services::requestAuditContextFactory()->createContext(
            $request,
            $result->uid,
            [],
            $result->permissions
        ));

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return Services::response()
            ->setJSON(ApiResponse::unauthorized($message))
            ->setStatusCode(401);
    }
}
