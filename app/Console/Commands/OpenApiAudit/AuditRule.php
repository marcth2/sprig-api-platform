<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit;

interface AuditRule
{
    public function name(): string;

    public function severity(): AuditSeverity;

    /** @return array<int, AuditFinding> */
    public function audit(AuditContext $context): array;
}
