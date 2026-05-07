<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use App\Interfaces\HasStatusCode;
use Exception;

/**
 * Exception Formatter
 *
 * Standardizes how exceptions are converted into API error responses,
 * taking environment-specific security and debugging needs into account.
 */
class ExceptionFormatter
{
    /**
     * Format an exception into a standardized response structure.
     *
     * @param Exception $e The exception to format
     * @return \App\Support\ApiResult
     */
    public static function format(Exception $e): \App\Support\ApiResult
    {
        $status = self::resolveStatusCode($e);

        if ($e instanceof ApiException) {
            return new ApiResult($e->toArray(), $status);
        }

        // For unhandled exceptions, build a generic error response
        $body = [
            'status'  => 'error',
            'message' => self::resolveMessage($e),
            'errors'  => self::resolveDebugInfo($e),
        ];

        return new ApiResult($body, $status);
    }

    /**
     * Determine the HTTP status code for the exception.
     */
    private static function resolveStatusCode(Exception $e): int
    {
        if ($e instanceof HasStatusCode) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Resolve the error message based on the environment.
     */
    private static function resolveMessage(Exception $e): string
    {
        if (ENVIRONMENT === 'production') {
            return lang('Api.serverError');
        }

        return get_class($e) . ': ' . $e->getMessage();
    }

    /**
     * Provide detailed debugging information in non-production environments.
     */
    private static function resolveDebugInfo(Exception $e): array
    {
        if (ENVIRONMENT === 'production') {
            return [];
        }

        return [
            'class' => get_class($e),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => explode("
", $e->getTraceAsString()),
        ];
    }
}
