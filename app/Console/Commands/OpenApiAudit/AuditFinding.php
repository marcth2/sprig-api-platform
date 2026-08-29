<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit;

final readonly class AuditFinding
{
    /** @param string[] $issues */
    public function __construct(
        public string $subject,
        public array $issues,
    ) {}
}
