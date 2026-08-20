# Keep Eloquent Models shared and unsiloed; default cross-domain behavior to direct import, extracting a Contract only on demonstrated pain

**Status:** accepted

Only the HealthCheck domain exists today, so this decision is made ahead of validation and is provisional until a second domain is actually built. It covers three separate cross-domain concerns that #63 originally bundled together: shared data, behavior invocation, and notification.

**Models.** The underlying mariadb schema is not siloed — `users.company_id` is a foreign key that will cross whatever domain line eventually separates a User domain from a Company domain. A Model is a direct reflection of that shared schema, not a domain-owned business concept the way an Action or DTO is. `app/Models/User.php` already lives unsiloed today, and this matches Laravel Jetstream's own architecture (verified against `laravel/jetstream` 5.x: neutral, package-owned Contracts and stub-published Actions, no per-feature Model silos). So Eloquent Models stay in `app/Models/`, shared across all domains; domain-specific `Data/` DTOs remain siloed inside each domain. With Models shared, a domain can query another domain's Model directly for reads — no boundary mechanism is needed there.

**Behavior invocation.** For the narrower case of one domain's Action needing to invoke another domain's behavior (e.g. a future `CreateUser` action needing to touch Company), default to direct import of the target domain's Action or Service. Extract a Contract later only on demonstrated pain — a second consumer appears, testing friction shows up, or the depended-on domain's internals are actually churning — matching this file's existing "add... only when the need is real" principle. Ownership of that future Contract (consumer-owned, provider-owned, or neutral) is decided at extraction time, once there's a real second side to weigh, not now.

**Notification.** Cross-domain "notify" uses plain in-process Laravel events (`Event::`/`dispatch()`). Sync vs. queued is decided per event when a real need exists — no queue worker runs anywhere in this stack today, so mandating async now would be speculative.

`App\Shared\*` (e.g. `ApiErrorData`) stays exempt from all of the above, as it already was in practice. Automated enforcement of any of this — preventing illegal cross-domain imports, for instance — is explicitly out of scope here and is tracked separately in #66.

## Considered Options

- **Provider-owned contract** (Company defines the interface User must implement/extend against) — rejected: inverts control for no benefit yet. The consumer is the side that needs to vary or mock the collaborator, not the provider.
- **Pre-stub a consumer-owned Contract for a not-yet-built domain**, so User never directly imports Company — rejected: the premise doesn't hold. A domain won't reference another domain until that domain actually exists, so there's nothing to abstract against yet.
- **Fully siloed Models** (each domain owns its own Model classes, even for shared tables) — rejected: contradicts the actual database schema, which is not siloed, and would force artificial per-domain Model duplication or cross-domain foreign-key gymnastics for no real encapsulation benefit. Also inconsistent with Jetstream's own precedent of neutral, unsiloed model access.
- **Async (queued) events by default** for all cross-domain notification — rejected for now: no queue worker currently runs in this stack, so mandating async is speculative complexity. Decide sync vs. queued per event once a real consumer exists.

## Consequences

Future domains keep their Eloquent Models in `app/Models/`, not inside their own domain silo — only `Data/`, `Actions/`, `Contracts/`, `Enums/`, `Providers/`, and `Services/` are domain-scoped. Cross-domain behavior calls default to direct import; introducing a Contract is a deliberate, evidence-driven extraction, not an upfront pattern. This is provisional until domain #2 is actually built — if reality doesn't fit, revisit it in that domain's own ticket rather than treating this ADR as final.
