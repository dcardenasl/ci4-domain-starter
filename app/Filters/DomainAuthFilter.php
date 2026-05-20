<?php

declare(strict_types=1);

namespace App\Filters;

use Config\Services;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use dcardenasl\Ci4ApiCore\Http\Filters\AbstractIntrospectionFilter;

/**
 * DomainAuthFilter — validates Bearer JWTs by delegating to the hub's
 * /api/v1/auth/introspect endpoint.
 *
 * Mirrors the contract that `JwtAuthFilter` implements in ci4-api-starter so
 * downstream `PermissionFilter` reads the same `ApiRequest::getAuth*` API
 * without modification.
 */
class DomainAuthFilter extends AbstractIntrospectionFilter
{
    protected function introspect(string $token): IntrospectResult
    {
        return Services::hubClient()->introspect($token);
    }
}
