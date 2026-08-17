# OpenApi Directory

## Purpose

Global OpenAPI annotation artefacts that have no single-domain DTO owner. Classes here exist solely to carry `#[OA\Schema]` or other OpenAPI annotations that cannot be attached to any specific domain's data class.

Global non-schema annotations (`#[OA\Info]`, `#[OA\Server]`, `#[OA\SecurityScheme]`, `#[OA\Tag]`) live on `app/Http/Controllers/ApiController.php`, not here.

---

## What belongs in `Common/`

Cross-domain, framework-level schemas with no single domain owner:

- Security scheme definitions (if moved off `ApiController`)
- Pagination wrapper schemas (e.g. a generic `PaginatedResponse` envelope)
- Global error envelopes **only if** no domain DTO owns the data

---

## What does NOT belong here

Domain-specific response shapes. `#[OA\Schema]` belongs on the Data class that owns the data:

```php
// Correct — schema annotation on the DTO that owns the data
#[OA\Schema(schema: 'HealthStatus', ...)]
class HealthStatusData extends Data { ... }

// Wrong — a separate class in app/OpenApi/Schemas/ just to hold the annotation
class HealthStatusSchema { #[OA\Schema(...)] ... }
```

---

## Current inventory

`Common/` is empty. Every schema so far (`HealthAggregateData`, `HealthStatusData`, `ApiErrorData`) already has a domain-owning DTO, so nothing has needed a home here yet.

---

## How to add a schema correctly

1. Put `#[OA\Schema]` on the `Data` class that owns the data.
2. Reference it in path annotations via `ref: '#/components/schemas/{SchemaName}'`.
3. Only create a class in `Common/` if there is genuinely no domain DTO to attach the annotation to.
4. Run `php artisan l5-swagger:generate` and `php artisan l5-swagger:audit` to verify the schema resolves.
