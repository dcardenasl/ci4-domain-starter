<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Project metadata (single source of truth).
 */
class Project extends BaseConfig
{
    public const NAME = 'CodeIgniter 4 API Starter';
    public const DESCRIPTION = 'RESTful API built with CodeIgniter 4, featuring JWT authentication, standardized responses, and comprehensive documentation.';
    public const VERSION = '1.0.0';

    public string $name = 'CodeIgniter 4 API Starter';
    public string $description = 'RESTful API built with CodeIgniter 4, featuring JWT authentication, standardized responses, and comprehensive documentation.';
    public string $version = '1.0.0';
}
