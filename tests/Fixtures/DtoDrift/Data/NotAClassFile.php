<?php

declare(strict_types=1);

// Intentionally declares no class — exercises the class_exists() guard in
// AuditOpenApiSpec::getSchemaDtoClasses(), which must skip a file that doesn't
// resolve to the class its path implies.
