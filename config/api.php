<?php

declare(strict_types=1);

return [
    'version' => env('API_VERSION', '1.0.0'),
    'supported_versions' => [explode('.', env('API_VERSION', '1.0.0'))[0]],
];
