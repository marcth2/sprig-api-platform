# Shared Directory

## Purpose

Cross-domain artifacts with no single domain owner. Currently: DTOs carrying reusable OpenAPI schema/component annotations, e.g. `ApiErrorData` (the `ErrorResponse` schema and the `UnauthorizedError` response component built on it). Add other kinds of ownerless, cross-domain classes here as they arise — this isn't OpenAPI-specific.

Global non-schema OpenAPI annotations (`#[OA\Info]`, `#[OA\Server]`, `#[OA\Tag]`) live on `app/Http/Controllers/ApiController.php`, not here. The `sanctum` security scheme lives in `config/l5-swagger.php`'s `securityDefinitions`, not as an `#[OA\SecurityScheme]` annotation anywhere (see #57).

---

## What does NOT belong here

Domain-specific response shapes. `#[OA\Schema]` belongs on the Data class that owns the data, inside that domain's own `Data/`:

```php
// Correct — schema annotation on the DTO that owns the data
#[OA\Schema(schema: 'HealthStatus', ...)]
class HealthStatusData extends Data { ... }

// Wrong — a separate class in app/Shared/ just to hold the annotation for a domain that already owns the data
class HealthStatusSchema { #[OA\Schema(...)] ... }
```

Only put a class here when there is genuinely no domain DTO to attach it to.

---

## Structure

Follows the domain-silo shape from root `CLAUDE.md`, populated on demand — `Data/` today, other subdirectories (`Enums/`, `Contracts/`, etc.) only once a real ownerless class needs one. Don't pre-create empty subdirectories for anticipated future content.

---

## How to add a schema-bearing class here

1. Confirm no domain DTO can own it — check the domain the data conceptually belongs to first.
2. Add the class under `app/Shared/Data/` with its `#[OA\Schema]` (and, if it wraps a reusable response/parameter, the accompanying `#[OA\Response]`/`#[OA\Parameter]`) attribute.
3. Reference it elsewhere via `ref: '#/components/schemas/{SchemaName}'` or `ref: '#/components/responses/{Name}'`.
4. Run `php artisan l5-swagger:generate` and `php artisan l5-swagger:audit` to verify the schema resolves.

## History

Absorbs the intended purpose of the former `app/OpenApi/` directory, which documented this convention but was never populated — the one real case it anticipated (`ApiErrorData`) landed in `app/Shared/Data/` instead, following the ordinary domain-folder shape. Reconciled in [#136](https://github.com/marcth2/sprig-api-platform/issues/136).
