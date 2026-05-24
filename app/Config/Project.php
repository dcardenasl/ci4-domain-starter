<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Project metadata (single source of truth).
 */
class Project extends BaseConfig
{
    public const NAME = 'CodeIgniter 4 Domain Starter';
    public const DESCRIPTION = 'CodeIgniter 4 domain app starter that delegates authentication and IAM to a central hub.';
    public const VERSION = '1.1.1';

    public string $name = 'CodeIgniter 4 Domain Starter';
    public string $description = 'CodeIgniter 4 domain app starter that delegates authentication and IAM to a central hub.';
    public string $version = '1.1.1';
}
