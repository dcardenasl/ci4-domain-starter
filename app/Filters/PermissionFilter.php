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
 * Permission-Based Access Control Filter
 *
 * Reads the actor id and permission set populated by DomainAuthFilter (from the
 * hub introspection response) into ApiRequest / ContextHolder, and enforces a
 * required permission code argument such as `permission:items.write`.
 *
 * Permission codes use `.` as the resource/action separator (not `:`)
 * because CodeIgniter splits filter strings on `:`.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $required = is_array($arguments) ? (string) ($arguments[0] ?? '') : '';

        $context = ContextHolder::get();
        $actorId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;
        $actorId ??= $context?->user_id;

        $permissions = $request instanceof ApiRequest ? $request->getAuthPermissions() : [];
        if ($permissions === [] && $context !== null) {
            $permissions = $context->permissions;
        }

        if ($actorId === null) {
            return Services::response()
                ->setJSON(ApiResponse::unauthorized(lang('Api.authRequired')))
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if ($required === '' || ! in_array($required, $permissions, true)) {
            return Services::response()
                ->setJSON(ApiResponse::forbidden(lang('Api.insufficientPermissions')))
                ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return $response;
    }
}
