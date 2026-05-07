<?php

declare(strict_types=1);

namespace App\Libraries\Queue\Jobs;

use App\Libraries\Queue\Job;
use Config\Services;

class WriteAuditLogJob extends Job
{
    public function handle(): void
    {
        $payload = $this->data['audit'] ?? null;

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Missing required audit payload');
        }

        /** @var \App\Services\System\AuditWriter $auditWriter */
        $auditWriter = Services::auditWriter();
        $auditWriter->write($payload);
    }
}
