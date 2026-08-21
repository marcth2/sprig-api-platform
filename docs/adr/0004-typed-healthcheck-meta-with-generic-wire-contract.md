# Type HealthCheck's `meta` field in PHP via per-checker Data classes, keep the OpenAPI wire contract generic

**Status:** accepted

`meta` was documented as the HealthCheck domain's "explicit data contract," but it was actually an untyped `array` — the real shape lived only in prose, and that prose was already wrong: `HealthStatusData`'s doc block described only `app`'s shape, while `mariadb` and `redis` silently produced two more, incompatible shapes that were never documented at all (#65).

Two axes, decided independently:

- **Wire contract stays generic.** `HealthStatusResource.meta` keeps `type: object, additionalProperties: true` — no named schema, no `oneOf`. This follows the JSON:API `meta` convention this field was modeled on: the spec requires an object and explicitly permits any members. No current consumer parses `meta` by specific key — deploy pipelines check only the status code, and the CLI's `flattenMeta()` walks whatever array it's given generically — so a fixed wire schema would guarantee something nothing needs.
- **PHP internals get typed.** A `HealthCheckMetaData` interface (`app/HealthCheck/Contracts/`) plus one Data class per checker — `AppHealthMeta`, `MariadbHealthMeta`, `RedisHealthMeta` (`app/HealthCheck/Data/`) — and `HealthStatusData::$meta` retypes from `array` to `HealthCheckMetaData`. This is the actual fix: the shape now lives in PHPStan-checked code instead of a comment that drifts silently.

Supporting decisions:

- Each meta class carries its own `OA\Schema`/`OA\Property` (per CLAUDE.md's "schema docs belong on the DTO class" rule), but is never `$ref`'d from `HealthStatusResource.meta` — discoverable in Swagger UI as documentation, not a wire guarantee. Verified safe against `AuditOpenApiSpec.php`: its phantom-path check only compares `paths` to real routes, never inspects `components.schemas`, so an unreferenced schema doesn't trip that gate.
- The Down/failure path (`mariadb`, `redis`) and any service with nothing extra to report use one shared `EmptyHealthMeta implements HealthCheckMetaData`, with no properties. (`app` never reaches Down; it only returns `Ok`/`Degraded`.)
- `HealthCheckMetaData` extends Spatie's `TransformableData` contract (not left as a bare marker interface) so callers that need the raw shape — `CheckServiceHealth`'s CLI table renderer — can call `->toArray()` on it without knowing the concrete class.

## Correction to source material

#106 (the implementing ticket) stated `EmptyHealthMeta` "must serialize to `{}`, matching today's wire output exactly." That's incorrect: PHP's `[]` always encodes as a JSON array, and `HealthStatusData::$meta`'s pre-existing default was a plain `[]`, which serializes to `[]` (a JSON array), not `{}`. Verified empirically against the running app both before and after this change. Decision: keep the real current behavior — `EmptyHealthMeta` serializes to `[]`, not `{}`. #106 also referenced "ADR-0003" for this decision; that number was already claimed by [ADR-0003](0003-qa-verifier-independence-via-fresh-context-agent.md) (an unrelated decision written after #65), so this decision is ADR-0004 instead.

## Considered Options

- **Named wire schema for `meta`** (a `$ref` to a `oneOf` of the per-service meta schemas, or a single merged schema) — rejected: no consumer needs it, and a fixed schema would misrepresent `meta` as a stable contract when today's own JSON:API-style convention (and this codebase's actual usage) treats it as free-form.
- **`EmptyHealthMeta` serializing to `{}`** (per #106's literal text) — rejected: doesn't match the real, pre-existing wire behavior, and changing it would be a needless breaking change to an untyped field no consumer parses by key.
- **Leaving `meta` untyped in PHP** — rejected: this was the status quo #65 identified as the actual problem — three checkers already produced three incompatible shapes with no compiler or PHPStan check that any of them stayed consistent.

## Consequences

Each HealthCheck checker's `meta` shape is now enforced by PHPStan instead of documentation prose. Adding a new checker's meta fields means creating a new `HealthCheckMetaData` implementation, not editing an array literal — `app/HealthCheck/CLAUDE.md`'s "How to Add a New Service" step reflects this. The OpenAPI wire contract for `meta` is unchanged from a consumer's perspective (`type: object`, arbitrary members), so this is not a breaking change to the API.
