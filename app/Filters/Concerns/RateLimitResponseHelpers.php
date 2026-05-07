<?php

declare(strict_types=1);

namespace App\Filters\Concerns;

use App\Libraries\ApiResponse;
use CodeIgniter\HTTP\ResponseInterface;

trait RateLimitResponseHelpers
{
    /**
     * @param array{limit:int,remaining:int,reset:int} $info
     */
    private function attachRateLimitHeaders(ResponseInterface $response, array $info): void
    {
        $response->setHeader('X-RateLimit-Limit', (string) $info['limit']);
        $response->setHeader('X-RateLimit-Remaining', (string) $info['remaining']);
        $response->setHeader('X-RateLimit-Reset', (string) $info['reset']);
    }

    /**
     * Build a standardized 429 response body + headers.
     *
     * @param array<int|string, mixed> $errorParams
     */
    private function buildRateLimitExceededResponse(
        ResponseInterface $response,
        int $maxRequests,
        int $window,
        string $errorMessage,
        array $errorParams = []
    ): ResponseInterface {
        $retryAfter = $window;

        $body = array_merge(
            ApiResponse::error(
                ['rate_limit' => lang($errorMessage, $errorParams)],
                lang('Auth.rateLimitExceeded'),
                429
            ),
            ['retry_after' => $retryAfter]
        );

        $response->setStatusCode(429);
        $response->setHeader('Retry-After', (string) $retryAfter);
        $this->attachRateLimitHeaders($response, [
            'limit' => $maxRequests,
            'remaining' => 0,
            'reset' => time() + $retryAfter,
        ]);
        $response->setJSON($body);

        return $response;
    }
}
