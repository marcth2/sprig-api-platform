# Http Directory

## Controllers/ApiController.php

**Global OpenAPI annotation host — not a request handler.**

This class carries the annotations that l5-swagger needs to generate the spec preamble:

- `#[OA\Info]` — API title, version, description
- `#[OA\Server]` — server URL
- `#[OA\Tag]` — tag descriptions for grouping operations

The Sanctum bearer token security scheme is **not** defined here — it's defined in `config/l5-swagger.php`'s `securityDefinitions.securitySchemes.sanctum`. l5-swagger's config-driven security definitions take precedence over an `#[OA\SecurityScheme]` annotation on this class, so an annotated copy would be silently dead code (removed in #57 after it drifted from the config's text).

It contains no methods and handles no routes. Do not add request-handling logic here.

**Where annotations go:**
- Global spec annotations (`Info`, `Server`, `Tag`) → `ApiController`
- The `sanctum` security scheme → `config/l5-swagger.php`
- Path/operation annotations (`Get`, `Post`, `Put`, `Delete`) → Action class (`asController` method)
- Schema annotations (`Schema`, `Property`) → DTO class

---

## Middleware

### ApiVersion

Resolves the API version from the incoming request and validates it against `config('api.supported_versions')`.

**Resolution order:**
1. `X-API-Version` header (e.g. `X-API-Version: 1`)
2. Vendor media type in `Accept` header (e.g. `application/vnd.api.v1+json`)
3. Major version from `config('api.version')` (fallback)

Returns **406 Not Acceptable** for unrecognised versions.

Stores the resolved version as a request attribute: `$request->attributes->get('api.version')`. This is Octane-safe (request-scoped, not process-scoped).

**Must run BEFORE `ForceJsonResponse`** — see ordering constraint below.

### ForceJsonResponse

Overwrites the `Accept` header to `application/json` before the request reaches route handlers, ensuring all API responses are JSON-encoded.

**Must run AFTER `ApiVersion`** — if it runs first, it destroys the vendor media type (`application/vnd.api.v1+json`) before `ApiVersion` can read it, silently killing the vendor content-negotiation path.

---

## Middleware ordering constraint

In `bootstrap/app.php`, the `prepend` array for the `api` middleware group must list `ApiVersion` before `ForceJsonResponse`:

```php
$middleware->api(
    prepend: [
        ApiVersion::class,       // reads Accept first
        ForceJsonResponse::class, // then overwrites it
    ],
);
```

Reversing this order breaks vendor content negotiation with no visible error — requests with `application/vnd.api.v1+json` silently fall through to the default version instead of being negotiated correctly.
