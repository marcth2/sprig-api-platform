# Standardize the API error envelope on `status`/`code`, resolving the naming collision with HealthStatusData

**Status:** accepted

This codebase's only wire-facing error body, `ApiErrorData`, was just `{message}` — every current exception (`AuthenticationException`, `CheckServiceHealth`'s `\InvalidArgumentException`) rendered through Laravel's default handler instead, so there was no consistent API error envelope (#64). Designing that envelope raised a naming question: what to call the "HTTP status mirrored in the body" field, since `HealthStatusData` already had a field named `$status` — a `ServiceStatus` enum (`ok`/`degraded`/`down`), a different concept from an HTTP status code (#133).

Two axes, decided together:

- **Naming convention.** `status` = the HTTP status code (integer), `code` = a reserved-for-future app-specific error code (nothing produces one yet). This follows RFC 9457 (Problem Details for HTTP APIs, the current IETF standard) and the JSON:API error-object spec — both define `status` as the HTTP status. Google's internal JSON API style guide inverts this pairing, but it's a single-vendor outlier, not a competing standard; RFC 9457 and JSON:API agreeing was enough to treat this as the real ecosystem convention.
- **Collision fix.** Force-changing `HealthStatusData::$status`'s meaning to "HTTP status" would silently repurpose a field its own consumers (deploy pipelines, `HealthCheck/CLAUDE.md`) already read for pass/fail. Instead: rename that field to `$state` and the enum `ServiceStatus` → `ServiceState`, freeing `$status` on the same DTO for the HTTP-status int (previously named `$code`). This is a breaking wire change to `/api/health`'s response shape (`status` → `state` for the health-state field), accepted pre-1.0 (v0.1.2).

Supporting decision — **exception scope.** `AuthenticationException` (401), `\InvalidArgumentException` (404, picks up `CheckServiceHealth`'s "unknown service" case), `ValidationException` (422, with field errors), and a generic `\Throwable` fallback (500) are now centralized as `render()` closures in `bootstrap/app.php`, each returning the `ApiErrorData` envelope, scoped to `$request->expectsJson()` (true for all `api/*` routes via `ForceJsonResponse`). This replaces `CheckServiceHealth::asController()`'s local try/catch — one path for API errors instead of one per Action. `ValidationException` and the generic fallback are speculative: nothing in the codebase throws either today, but every future domain will hit both, so the envelope is defined once now rather than re-derived per domain later.

## Considered Options

- **Keep `HealthStatusData::$code` as-is, let `ApiErrorData` use `status`** (accepted inconsistency between two DTOs) — rejected: two different names for the same concept (HTTP status) across the API is exactly the kind of drift #65/ADR-0004 already fixed once for `meta`.
- **Drop the `status`/`code` split entirely, use `code` everywhere** — rejected: abandons RFC 9457/JSON:API precedent for no compensating benefit; `code` alone can't distinguish "the HTTP status" from "an app-specific error code" once the latter is eventually implemented.
- **Scope the rename to `ApiErrorData` only, leave `HealthStatusData::$status`/`$code` untouched** — rejected: perpetuates the exact naming collision this ADR exists to resolve, and leaves `HealthStatusData` speaking a different status/code vocabulary than every other API response.

## Consequences

`/api/health`'s response shape changes: the service-state field is now `state` (was `status`), and the HTTP-status mirror is now `status` (was `code`). Any external consumer of the health endpoints — deploy pipelines checking pass/fail, the CLI table renderer — must read `state` instead of `status` for health outcome. `app/HealthCheck/CLAUDE.md`'s documented response shape reflects the new fields. All 4xx/5xx JSON API responses now share one envelope (`message`, `status`, optional `errors`) produced from one place, so a new domain's exceptions get consistent error rendering for free instead of needing their own try/catch.
