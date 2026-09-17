<?php

declare(strict_types=1);

return [

    /*
     * `docs` is present so the Swagger UI container can fetch the OpenAPI spec:
     * the UI is served from http://localhost:8081 but reads the spec from the app
     * on http://localhost:8000/docs, which is a cross-origin request. `api/*` and
     * `sanctum/csrf-cookie` are the framework defaults — `api/*` is also what lets
     * the UI's "Try it out" requests reach the API.
     */
    'paths' => ['api/*', 'docs', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * Framework default, kept deliberately. The API authenticates with Sanctum
     * Bearer tokens rather than cookies and `supports_credentials` stays false, so
     * a wildcard origin grants a browser nothing it could not already get from
     * curl. Narrow this to specific origins if the API ever adopts cookie auth.
     */
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
