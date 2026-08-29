<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit;

enum AuditSeverity
{
    case Error;
    case Warning;
}
