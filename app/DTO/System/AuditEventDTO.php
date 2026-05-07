<?php

declare(strict_types=1);

namespace App\DTO\System;

use App\DTO\SecurityContext;

/**
 * Internal normalized representation of an audit event before persistence.
 */
readonly class AuditEventDTO
{
    public function __construct(
        public string $action,
        public string $entity_type,
        public ?int $entity_id,
        public array $old_values,
        public array $new_values,
        public ?SecurityContext $context,
        public string $result,
        public string $severity,
        public array $metadata,
        public ?string $request_id
    ) {
    }
}
