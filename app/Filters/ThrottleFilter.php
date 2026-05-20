<?php

declare(strict_types=1);

namespace App\Filters;

use dcardenasl\Ci4ApiCore\Http\Filters\AbstractThrottleFilter;

/**
 * Rate-limiting filter for the domain app. Inherits fixed-window IP + user-id
 * bucketing from {@see AbstractThrottleFilter}; limits come from Config\Api
 * (`rateLimitWindow`, `rateLimitRequests`, `rateLimitUserRequests`).
 */
class ThrottleFilter extends AbstractThrottleFilter
{
}
