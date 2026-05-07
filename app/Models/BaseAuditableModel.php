<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use Config\Services;

/**
 * Shared base model for auditable resources.
 *
 * Centralizes audit callback bootstrap to avoid repeating constructor wiring
 * across all auditable models.
 */
abstract class BaseAuditableModel extends Model
{
    use \App\Traits\Auditable;

    protected function initialize(): void
    {
        parent::initialize();
        $this->setAuditService(Services::auditService());
        $this->initAuditable();
    }
}
