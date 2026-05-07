<?php

namespace Config;

use App\Filters\CorsFilter;
use App\Filters\FeatureToggleFilter;
use App\Filters\LocaleFilter;
use App\Filters\PermissionFilter;
use App\Filters\RequestLoggingFilter;
use App\Filters\SecurityHeadersFilter;
use App\Filters\ThrottleFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;

class Filters extends BaseFilters
{
    public function __construct()
    {
        parent::__construct();

        if (ENVIRONMENT === 'production') {
            array_unshift($this->required['before'], 'forcehttps');
        }
    }

    /**
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'               => CSRF::class,
        'toolbar'            => DebugToolbar::class,
        'honeypot'           => Honeypot::class,
        'invalidchars'       => InvalidChars::class,
        'secureheaders'      => SecurityHeadersFilter::class,
        'cors'               => CorsFilter::class,
        'forcehttps'         => ForceHTTPS::class,
        'pagecache'          => PageCache::class,
        'performance'        => PerformanceMetrics::class,
        'domainauth'         => \App\Filters\DomainAuthFilter::class,
        'throttle'           => ThrottleFilter::class,
        'permission'         => PermissionFilter::class,
        'requestLogging'    => RequestLoggingFilter::class,
        'locale'             => LocaleFilter::class,
        'featureToggle'      => FeatureToggleFilter::class,
        'deprecationheaders' => \App\Filters\DeprecationHeadersFilter::class,
        'idempotency'        => \App\Filters\IdempotencyFilter::class,
        'correlationid'      => \App\Filters\CorrelationIdFilter::class,
        'maintenance'        => \App\Filters\MaintenanceFilter::class,
    ];

    /**
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'toolbar',
        ],
    ];

    /**
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            'maintenance',
            'correlationid',
            'locale',
            'cors',
            'invalidchars',
        ],
        'after' => [
            'cors',
            'secureheaders',
            'deprecationheaders',
            'correlationid',
            'requestLogging' => ['except' => ['health', 'ping', 'ready', 'live']],
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}
