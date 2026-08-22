# Architecture — Sprig API Platform

## Design Philosophy

**Actions are named business operations. DTOs are their data contracts.**

Both are written to be readable by Product Owners and AI agents. `app/{Domain}/Actions/` is a product catalogue — each class name maps directly to a business requirement. DTOs make the data contract explicit without reading implementation code.

**Grow as you go.** Start with an Action and a DTO. Add a Service class when an Action needs to orchestrate multiple collaborators — e.g. resolving a tagged collection of implementations via IoC, as `HealthCheckerService` does for the HealthCheck domain's checkers — not as a place to move logic just because an Action feels long. Add repositories, events, and query objects only when the need is real — not in anticipation of it.

---

## Domain Architecture

This project uses a siloed domain architecture. Each domain lives under `app/{Domain}/` and is self-contained, pulling in only the subdirectories it needs:

```
app/{Domain}/
├── Actions/     # One class per use case (lorisleiva/laravel-actions)
├── Checks/      # Interface implementations selected by config/runtime (e.g. HealthCheck's checkers)
├── Data/        # Spatie Data DTOs — request input and response output
├── Contracts/   # Interfaces the domain exposes or depends on
├── Enums/       # Domain enumerations
├── Providers/   # Domain-specific ServiceProvider(s), registered in bootstrap/providers.php
├── Services/    # External service calls, orchestration, data access
└── CLAUDE.md    # Domain documentation (required)
```

Not every domain needs every subdirectory — a simple domain may only have `Actions/` and `Data/`. Add a subdirectory when there's a real class to put in it, not in anticipation of one.

This is the mandatory convention for every domain added to this codebase, including domains introduced later for migrated services.

**Eloquent Models are shared, not siloed** — they live in `app/Models/`, reflecting the underlying database schema, which itself is not siloed (e.g. `users.company_id`). Only `Data/` DTOs are domain-scoped; a domain's Models live outside its silo alongside every other domain's. Cross-domain behavior invocation (one domain's Action calling another's) defaults to a direct import; extract a Contract only once a second consumer, testing friction, or real churn in the depended-on domain makes the coupling costly — decide that Contract's ownership at extraction time, not upfront. Cross-domain notification uses plain in-process Laravel events; sync vs. queued is decided per event when a real need exists. See [ADR-0002](adr/0002-cross-domain-dependency-and-shared-models.md).

**`app/Shared/` is the other exception** — cross-domain artifacts with no single domain owner (e.g. `ApiErrorData`, the reusable OpenAPI error schema/response) live there instead of any one domain's silo. See [`app/Shared/CLAUDE.md`](../app/Shared/CLAUDE.md). Folded in from the former `app/OpenApi/` in [#136](https://github.com/marcth2/sprig-api-platform/issues/136).
