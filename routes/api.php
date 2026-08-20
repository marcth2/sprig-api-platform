<?php

declare(strict_types=1);

/**
 * API route entry point — loads domain route files.
 *
 * Each domain registers its own routes in routes/api/{domain}.php.
 *
 * @see https://laravelactions.com
 * @see docs/architecture/README.md
 */

require base_path('routes/api/health.php');
