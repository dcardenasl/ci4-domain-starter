<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Concerns\RateLimitResponseHelpers;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;

class ThrottleFilter implements FilterInterface
{
    use RateLimitResponseHelpers;

    /**
     * Rate limit by IP and (when authenticated by DomainAuthFilter) by user_id.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $cache    = Services::cache();
        $response = Services::response();

        $ip      = $request->getIPAddress();
        $user_id = $request instanceof ApiRequest ? $request->getAuthUserId() : null;

        $apiConfig = config('Api');
        $window    = $apiConfig->rateLimitWindow;
        $ipLimit   = $apiConfig->rateLimitRequests;
        $userLimit = $apiConfig->rateLimitUserRequests;

        $ipKey       = 'rate_limit_ip_' . md5($ip);
        $ipRemaining = $this->checkRateLimit($cache, $ipKey, $ipLimit, $window);

        if ($ipRemaining === false) {
            return $this->rateLimitExceeded($response, $ipLimit, $window);
        }

        if ($user_id !== null) {
            $userKey       = 'rate_limit_user_' . $user_id;
            $userRemaining = $this->checkRateLimit($cache, $userKey, $userLimit, $window);

            if ($userRemaining === false) {
                return $this->rateLimitExceeded($response, $userLimit, $window);
            }
        }

        if ($request instanceof ApiRequest) {
            $request->setRateLimitInfo([
                'limit'     => $ipLimit,
                'remaining' => max(0, $ipRemaining),
                'reset'     => time() + $window,
            ]);
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($request instanceof ApiRequest && $request->getRateLimitInfo() !== null) {
            $info = $request->getRateLimitInfo();
            $this->attachRateLimitHeaders($response, $info);
        }

        return $response;
    }

    private function checkRateLimit(CacheInterface $cache, string $key, int $limit, int $window): int|false
    {
        $requests = $cache->get($key);

        if ($requests === null) {
            $cache->save($key, 1, $window);

            return $limit - 1;
        }

        $requests = (int) $requests;

        if ($requests >= $limit) {
            return false;
        }

        $cache->save($key, $requests + 1, $window);

        return $limit - ($requests + 1);
    }

    private function rateLimitExceeded(ResponseInterface $response, int $maxRequests, int $window): ResponseInterface
    {
        return $this->buildRateLimitExceededResponse(
            $response,
            $maxRequests,
            $window,
            'Api.tooManyRequests',
            [$maxRequests, $window]
        );
    }
}
